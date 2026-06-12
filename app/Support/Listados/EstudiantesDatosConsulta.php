<?php

namespace App\Support\Listados;

use App\Models\Curso;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consultas para el export Excel «ESTUDIANTES DATOS» (plantilla nocturna).
 */
final class EstudiantesDatosConsulta
{
    /** Nombres legacy posibles de la columna en `legajos` (orden de preferencia). */
    private const CANDIDATAS_GRUPO_SANGUIENO = ['grupsang', 'gruposang', 'gruposanguineo', 'grupo_sanguineo', 'gsang'];

    /** @return Collection<int, Curso> */
    public static function cursosEnContexto(): Collection
    {
        $ctx = schoolCtx();

        return Curso::query()
            ->with('turnoClase')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'orden', 'c', 's', 'idTurnoClase', 'idCurPlan']);
    }

    /**
     * @param  list<int>  $cursoIds
     * @return Collection<int, object{
     *   matricula_id: int,
     *   id_curso: int,
     *   curso_nombre: string,
     *   apellido: string,
     *   nombre: string,
     *   dni: string,
     *   fechnaci: mixed,
     *   domicilio: string,
     *   grupo_sanguineo: string,
     *   nombremad: string,
     *   dnimad: string,
     *   telemad: string
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

        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;

        $permitidos = self::cursosEnContexto()
            ->pluck('Id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $cursoIds = array_values(array_filter($cursoIds, fn (int $id) => $permitidos->has($id)));
        if ($cursoIds === []) {
            return collect();
        }

        $cursosPorId = self::cursosEnContexto()->keyBy(fn (Curso $c) => (int) $c->Id);

        $select = [
            'matricula.id as matricula_id',
            'matricula.idCursos as id_curso',
            'legajos.apellido',
            'legajos.nombre',
            'legajos.dni',
            'legajos.fechnaci',
            'legajos.callenum',
            'legajos.barrio',
            'legajos.localidad',
            'legajos.nombremad',
            'legajos.dnimad',
            'legajos.telemad',
            'legajos.nombrepad',
            'legajos.dnipad',
            'legajos.telepad',
            'legajos.nombretut',
            'legajos.dnitut',
            'legajos.teletut',
        ];

        $exprGs = self::expresionSqlGrupoSanguineo();
        if ($exprGs !== null) {
            $select[] = DB::raw($exprGs.' as grupo_sanguineo');
        }

        $rows = DB::table('matricula')
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->whereIn('matricula.idCursos', $cursoIds)
            ->where('matricula.idTerlec', $idTerlec)
            ->where('matricula.idNivel', $idNivel)
            ->where('matricula.idCondiciones', 1)
            ->whereNull('matricula.fechaBaja')
            ->orderBy('matricula.idCursos')
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre')
            ->orderBy('matricula.id')
            ->select($select)
            ->get();

        return $rows->map(function (object $row) use ($cursosPorId) {
            /** @var Curso|null $curso */
            $curso = $cursosPorId->get((int) $row->id_curso);
            $row->curso_nombre = $curso?->nombreParaListado() ?? '';
            $row->domicilio = self::formatearDomicilio(
                (string) ($row->callenum ?? ''),
                (string) ($row->barrio ?? ''),
                (string) ($row->localidad ?? ''),
            );
            if (! property_exists($row, 'grupo_sanguineo')) {
                $row->grupo_sanguineo = '';
            }

            return $row;
        });
    }

    /**
     * @param  list<int>  $matriculaIds
     * @return list<int>
     */
    public static function filtrarMatriculaIdsEnContexto(array $matriculaIds): array
    {
        $matriculaIds = collect($matriculaIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($matriculaIds === []) {
            return [];
        }

        $ctx = schoolCtx();

        return DB::table('matricula')
            ->whereIn('matricula.id', $matriculaIds)
            ->where('matricula.idTerlec', (int) $ctx->idTerlec)
            ->where('matricula.idNivel', (int) $ctx->idNivel)
            ->where('matricula.idCondiciones', 1)
            ->whereNull('matricula.fechaBaja')
            ->orderBy('matricula.id')
            ->pluck('matricula.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Clave de orden apellido + nombre (insensible a mayúsculas). */
    public static function claveOrdenAlfabetico(object $row): string
    {
        $apellido = mb_strtolower(trim((string) ($row->apellido ?? '')), 'UTF-8');
        $nombre = mb_strtolower(trim((string) ($row->nombre ?? '')), 'UTF-8');

        return $apellido."\0".$nombre;
    }

    /**
     * @param  Collection<int, object>  $filas
     * @return Collection<int, object>
     */
    public static function ordenarFilasAlfabeticamente(Collection $filas): Collection
    {
        return $filas
            ->sortBy(fn (object $row) => self::claveOrdenAlfabetico($row), SORT_STRING)
            ->values();
    }

    public static function formatearApellidoNombre(string $apellido, string $nombre): string
    {
        $texto = trim($apellido.' '.$nombre);

        return $texto !== '' ? mb_strtoupper($texto, 'UTF-8') : '';
    }

    public static function formatearFechaNacimiento(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        try {
            return Carbon::parse((string) $valor)->format('d/m/Y');
        } catch (\Throwable) {
            return trim((string) $valor);
        }
    }

    public static function formatearDomicilio(string $callenum, string $barrio, string $localidad): string
    {
        $partes = collect([$callenum, $barrio, $localidad])
            ->map(fn ($v) => trim($v))
            ->filter()
            ->values();

        if ($partes->isEmpty()) {
            return '';
        }

        return mb_strtoupper($partes->implode(' — '), 'UTF-8');
    }

    /**
     * Madre → padre → tutor según nombre cargado en el legajo.
     *
     * @return array{nombre: string, dni: string, tel: string}
     */
    public static function datosAdultoResponsableLegajo(object $legajo): array
    {
        $candidatos = [
            ['nombremad', 'dnimad', 'telemad'],
            ['nombrepad', 'dnipad', 'telepad'],
            ['nombretut', 'dnitut', 'teletut'],
        ];

        foreach ($candidatos as [$colNombre, $colDni, $colTel]) {
            $nombre = trim((string) ($legajo->{$colNombre} ?? ''));
            if ($nombre !== '') {
                return [
                    'nombre' => $nombre,
                    'dni' => trim((string) ($legajo->{$colDni} ?? '')),
                    'tel' => trim((string) ($legajo->{$colTel} ?? '')),
                ];
            }
        }

        return ['nombre' => '', 'dni' => '', 'tel' => ''];
    }

    public static function formatearTelDniResponsableDesdeLegajo(object $legajo): string
    {
        $datos = self::datosAdultoResponsableLegajo($legajo);

        return self::formatearTelDniResponsable($datos['nombre'], $datos['dni'], $datos['tel']);
    }

    public static function formatearTelDniResponsable(string $nombremad, string $dnimad, string $telemad): string
    {
        $nombre = trim($nombremad);
        $dni = trim($dnimad);
        $tel = trim($telemad);

        $partes = [];
        if ($nombre !== '') {
            $partes[] = mb_strtoupper($nombre, 'UTF-8');
        }
        if ($tel !== '') {
            $partes[] = 'Tel: '.$tel;
        }
        if ($dni !== '') {
            $partes[] = 'DNI: '.$dni;
        }

        return implode(' — ', $partes);
    }

    public static function nombreArchivo(?Carbon $momento = null, string $extension = 'xlsx'): string
    {
        $momento ??= now();
        $extension = ltrim(strtolower(trim($extension)), '.');

        return 'ESTUDIANTES DATOS '.$momento->format('d-m-Y H-i-s').'.'.$extension;
    }

    public static function nombreArchivoPdf(?Carbon $momento = null): string
    {
        return self::nombreArchivo($momento, 'pdf');
    }

    /**
     * @return list<string>
     */
    public static function columnasGrupoSanguineoExistentes(): array
    {
        static $columnas = null;

        if ($columnas !== null) {
            return $columnas;
        }

        $columnas = [];
        if (! Schema::hasTable('legajos')) {
            return $columnas;
        }

        foreach (self::CANDIDATAS_GRUPO_SANGUIENO as $candidata) {
            if (Schema::hasColumn('legajos', $candidata)) {
                $columnas[] = $candidata;
            }
        }

        return $columnas;
    }

    /** Primera columna existente (compatibilidad con consultas simples). */
    public static function columnaGrupoSanguineo(): ?string
    {
        return self::columnasGrupoSanguineoExistentes()[0] ?? null;
    }

    /** Valor no vacío del legajo, probando todas las columnas legacy conocidas. */
    public static function valorGrupoSanguineo(object $legajo): string
    {
        foreach (self::columnasGrupoSanguineoExistentes() as $columna) {
            $valor = trim((string) ($legajo->{$columna} ?? ''));
            if ($valor !== '') {
                return $valor;
            }
        }

        return '';
    }

    /** COALESCE sobre columnas existentes (para SELECT en listados). */
    public static function expresionSqlGrupoSanguineo(string $alias = 'legajos'): ?string
    {
        $columnas = self::columnasGrupoSanguineoExistentes();
        if ($columnas === []) {
            return null;
        }

        $partes = array_map(
            fn (string $columna) => "NULLIF(TRIM({$alias}.{$columna}), '')",
            $columnas
        );

        return 'COALESCE('.implode(', ', $partes).", '')";
    }
}
