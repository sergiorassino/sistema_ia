<?php

namespace App\Support\Cuotas;

use App\Models\Matricula;
use App\Support\Cuotas\GeneracionCuotaEstudianteService;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Support\Collection;

/**
 * Matrículas del ciclo activo para asignación de becas (matricula.idCuotasbecas).
 */
final class AsignacionBecasConsulta
{
    /**
     * @param  list<int>  $cursoIds
     * @return Collection<int, array{
     *     idMatricula: int,
     *     idLegajo: int,
     *     idCurso: int,
     *     alumno: string,
     *     dni: string,
     *     cursoLabel: string,
     *     idCuotasbecas: int
     * }>
     */
    public static function matriculasParaAsignacion(array $cursoIds, string $terminoBusqueda = ''): Collection
    {
        $cursoIds = self::validarIdsCursos($cursoIds);
        $termino = trim($terminoBusqueda);

        if ($cursoIds === [] && $termino === '') {
            return collect();
        }

        $idTerlec = (int) schoolCtx()->idTerlec;

        $query = Matricula::query()
            ->select([
                'matricula.id',
                'matricula.idLegajos',
                'matricula.idCursos',
                'matricula.idCuotasbecas',
            ])
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->where('matricula.idTerlec', $idTerlec)
            ->where(function ($q) {
                $q->whereNull('matricula.fechaBaja')
                    ->orWhere('matricula.fechaBaja', '0000-00-00')
                    ->orWhere('matricula.fechaBaja', '');
            })
            ->with([
                'legajo:id,apellido,nombre,dni',
                'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
                'curso.curplan:id,curPlanCurso',
                'curso.turnoClase:id,nombre',
            ]);

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'matricula.idNivel');

        if ($cursoIds !== []) {
            $query->whereIn('matricula.idCursos', $cursoIds);
        }

        if ($termino !== '') {
            $query->whereHas('legajo', fn ($q) => $q->buscar($termino));
        }

        return $query
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre')
            ->orderBy('matricula.id')
            ->get()
            ->map(fn (Matricula $mat) => self::filaDesdeMatricula($mat))
            ->values();
    }

    /** @return list<int> */
    public static function validarIdsCursos(array $cursoIds): array
    {
        $permitidos = GeneracionMasivaCuotasConsulta::cursosEnContexto()
            ->pluck('Id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        return collect($cursoIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $permitidos->has($id))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     idMatricula: int,
     *     idLegajo: int,
     *     idCurso: int,
     *     alumno: string,
     *     dni: string,
     *     cursoLabel: string,
     *     idCuotasbecas: int
     * }
     */
    public static function filaDesdeMatricula(Matricula $mat): array
    {
        $legajo = $mat->legajo;
        $apellido = mb_strtoupper(trim((string) ($legajo?->apellido ?? '')));
        $nombre = mb_strtoupper(trim((string) ($legajo?->nombre ?? '')));
        $alumno = match (true) {
            $apellido !== '' && $nombre !== '' => $apellido.', '.$nombre,
            $apellido !== '' => $apellido,
            default => $nombre,
        };

        return [
            'idMatricula' => (int) $mat->id,
            'idLegajo' => (int) $mat->idLegajos,
            'idCurso' => (int) ($mat->idCursos ?? 0),
            'alumno' => $alumno,
            'dni' => CuotasFormato::formatearDni($legajo?->dni ?? ''),
            'cursoLabel' => mb_strtoupper(trim((string) ($mat->curso?->nombreParaListado() ?? ''))),
            'idCuotasbecas' => (int) ($mat->idCuotasbecas ?? 0) < 1
                ? GeneracionCuotaEstudianteService::BECA_CUOTA_ENTERA
                : (int) $mat->idCuotasbecas,
        ];
    }
}
