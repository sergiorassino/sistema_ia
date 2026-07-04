<?php

namespace App\Support\Cuotas;

use App\Models\Curso;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cursos y alumnos regulares para generación masiva de cuotas (Administración).
 */
final class GeneracionMasivaCuotasConsulta
{
    /** @return Collection<int, Curso> */
    public static function cursosEnContexto(): Collection
    {
        $idTerlec = (int) schoolCtx()->idTerlec;

        $query = Curso::query()
            ->with(['curplan', 'turnoClase', 'nivel:id,nivel'])
            ->join('niveles', 'niveles.id', '=', 'cursos.idNivel')
            ->where('cursos.idTerlec', $idTerlec);

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'cursos.idNivel');

        return $query
            ->orderBy('niveles.nivel')
            ->orderByRaw('COALESCE(cursos.orden, 9999) asc')
            ->orderBy('cursos.cursec')
            ->orderBy('cursos.Id')
            ->get([
                'cursos.Id',
                'cursos.cursec',
                'cursos.orden',
                'cursos.c',
                'cursos.s',
                'cursos.idTurnoClase',
                'cursos.idCurPlan',
                'cursos.idNivel',
                'cursos.idTerlec',
            ]);
    }

    public static function etiquetaCursoConNivel(Curso $curso): string
    {
        $nivel = trim((string) ($curso->nivel?->nivel ?? ''));
        $nombre = $curso->nombreParaListado();

        if ($nivel === '') {
            return $nombre;
        }

        return $nombre.' ('.$nivel.')';
    }

    /**
     * @param  list<int>  $cursoIds
     * @return Collection<int, object{
     *   id_legajo: int,
     *   id_curso: int,
     *   curso_nombre: string,
     *   apellido: string,
     *   nombre: string,
     *   dni: string
     * }>
     */
    public static function alumnosRegularesPorCursos(array $cursoIds): Collection
    {
        $cursoIds = collect($cursoIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($cursoIds === []) {
            return collect();
        }

        $permitidos = self::cursosEnContexto()
            ->pluck('Id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $cursoIds = array_values(array_filter($cursoIds, fn (int $id) => $permitidos->has($id)));
        if ($cursoIds === []) {
            return collect();
        }

        $idTerlec = (int) schoolCtx()->idTerlec;
        $cursosPorId = self::cursosEnContexto()->keyBy(fn (Curso $c) => (int) $c->Id);

        $query = DB::table('matricula')
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->join('condiciones', 'condiciones.id', '=', 'matricula.idCondiciones')
            ->whereIn('matricula.idCursos', $cursoIds)
            ->where('matricula.idTerlec', $idTerlec)
            ->where('matricula.idCondiciones', 1)
            ->where('condiciones.proteg', '!=', 99)
            ->whereNull('matricula.fechaBaja');

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'matricula.idNivel');

        $rows = $query
            ->orderBy('matricula.idCursos')
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre')
            ->orderBy('matricula.id')
            ->select([
                'matricula.idLegajos as id_legajo',
                'matricula.idCursos as id_curso',
                'legajos.apellido',
                'legajos.nombre',
                'legajos.dni',
            ])
            ->get();

        return $rows->map(function (object $row) use ($cursosPorId) {
            /** @var Curso|null $curso */
            $curso = $cursosPorId->get((int) $row->id_curso);
            $row->curso_nombre = $curso?->nombreParaListado() ?? '';

            return $row;
        });
    }

    public static function etiquetaAlumno(object $row): string
    {
        $apellido = mb_strtoupper(trim((string) ($row->apellido ?? '')));
        $nombre = mb_strtoupper(trim((string) ($row->nombre ?? '')));
        $dni = CuotasFormato::formatearDni($row->dni ?? '');

        $texto = trim($apellido.' '.$nombre);
        if ($dni !== '') {
            $texto .= ' — DNI '.$dni;
        }

        return $texto;
    }

    /**
     * Fila de alumno para facturación AFIP a partir de un legajo (selección individual).
     */
    public static function filaAlumnoDesdeLegajo(int $idLegajo): ?object
    {
        if ($idLegajo <= 0) {
            return null;
        }

        $legajo = GestionAranceles::legajoParaGestion($idLegajo);
        if ($legajo === null) {
            return null;
        }

        $matricula = GestionAranceles::matriculaCicloActivo($idLegajo)
            ?? GestionAranceles::matriculaReferenciaListado($legajo);

        $cursoNombre = '';
        $idCurso = 0;
        if ($matricula !== null) {
            $idCurso = (int) ($matricula->idCursos ?? 0);
            $matricula->loadMissing(['curso.curplan', 'curso.turnoClase']);
            $cursoNombre = trim((string) ($matricula->curso?->nombreParaListado() ?? ''));
        }

        return (object) [
            'id_legajo' => $idLegajo,
            'id_curso' => $idCurso,
            'curso_nombre' => $cursoNombre,
            'apellido' => (string) ($legajo->apellido ?? ''),
            'nombre' => (string) ($legajo->nombre ?? ''),
            'dni' => (string) ($legajo->dni ?? ''),
        ];
    }
}
