<?php

namespace App\Support\Listados;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consulta y formateo de filas para el PDF Libro de Matrícula.
 */
final class LibroMatriculaExporter
{
    /** Filas vacías en la hoja manual (solo plantilla para anotar; el listado de alumnos pagina en forma dinámica). */
    public const FILAS_HOJA_MANUAL = 28;

    /** @deprecated El PDF ya no pagina por cantidad fija; ver {@see self::FILAS_HOJA_MANUAL}. */
    public const FILAS_POR_HOJA = self::FILAS_HOJA_MANUAL;

    /**
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   totales: array{total: int, varones: int, mujeres: int, otros: int}
     * }
     */
    public static function datosParaPdf(int $idNivel, int $idTerlec, Carbon $inscriptosAl, ?int $anoCiclo = null): array
    {
        $corte = $inscriptosAl->copy()->endOfDay();

        $rows = DB::table('matricula')
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->join('cursos', 'cursos.Id', '=', 'matricula.idCursos')
            ->where('matricula.idTerlec', $idTerlec)
            ->where('matricula.idNivel', $idNivel)
            ->where('matricula.idCondiciones', 1)
            ->where('matricula.fechaMatricula', '<=', $corte)
            ->where(function ($q) use ($corte) {
                $q->whereNull('matricula.fechaBaja')
                    ->orWhere('matricula.fechaBaja', '>', $corte);
            })
            ->orderBy('cursos.orden')
            ->orderBy('cursos.c')
            ->orderBy('cursos.s')
            ->orderBy('cursos.cursec')
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('legajos.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('legajos.nombre'))
            ->select([
                'matricula.fechaMatricula',
                'legajos.apellido',
                'legajos.nombre',
                'legajos.fechnaci',
                'legajos.dni',
                'legajos.callenum',
                'legajos.ln_ciudad',
                'legajos.ln_provincia',
                'legajos.nombrepad',
                'legajos.nombremad',
                'legajos.sexo',
                'cursos.cursec',
            ])
            ->get();

        $totales = [
            'total' => 0,
            'varones' => 0,
            'mujeres' => 0,
            'otros' => 0,
        ];

        $filas = [];
        foreach ($rows as $row) {
            $grupo = LibroMatriculaSexoGrupo::clasificar($row->sexo);
            $totales['total']++;
            match ($grupo) {
                LibroMatriculaSexoGrupo::VARON => $totales['varones']++,
                LibroMatriculaSexoGrupo::MUJER => $totales['mujeres']++,
                default => $totales['otros']++,
            };

            $filas[] = self::formatearFila($row, $anoCiclo);
        }

        return [
            'filas' => $filas,
            'totales' => $totales,
        ];
    }

    /**
     * @return list<Collection<int, array<string, mixed>>>
     */
    public static function paginarFilas(array $filas, int $porHoja = self::FILAS_POR_HOJA): array
    {
        if ($filas === []) {
            return [collect()];
        }

        return collect($filas)->chunk($porHoja)->values()->all();
    }

    private static function formatearFila(object $row, ?int $anoCiclo = null): array
    {
        $fechaMatricula = self::parseFecha($row->fechaMatricula ?? null);
        $fechaNac = self::parseFecha($row->fechnaci ?? null);

        return [
            'fecha_matricula' => self::formatoFecha($fechaMatricula),
            'estudiante' => self::formatoNombre($row->apellido ?? '', $row->nombre ?? ''),
            'edad' => self::calcularEdad($fechaNac, $fechaMatricula, $anoCiclo),
            'dni' => trim((string) ($row->dni ?? '')),
            'domicilio' => mb_strtoupper(trim((string) ($row->callenum ?? '')), 'UTF-8'),
            'fecha_nac' => self::formatoFecha($fechaNac),
            'lugar_nac' => self::formatoLugarNacimiento($row->ln_ciudad ?? '', $row->ln_provincia ?? ''),
            'padre' => mb_strtoupper(trim((string) ($row->nombrepad ?? '')), 'UTF-8'),
            'madre' => mb_strtoupper(trim((string) ($row->nombremad ?? '')), 'UTF-8'),
            'cur' => trim((string) ($row->cursec ?? '')),
        ];
    }

    private static function parseFecha(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function formatoFecha(?Carbon $fecha): string
    {
        return $fecha ? $fecha->format('d/m/Y') : '';
    }

    private static function formatoNombre(string $apellido, string $nombre): string
    {
        $texto = trim($apellido.' '.$nombre);

        return $texto !== '' ? mb_strtoupper($texto, 'UTF-8') : '';
    }

    private static function formatoLugarNacimiento(string $ciudad, string $provincia): string
    {
        $ciudad = trim($ciudad);
        $provincia = trim($provincia);

        if ($ciudad === '' && $provincia === '') {
            return '';
        }

        if ($ciudad !== '' && $provincia !== '') {
            return mb_strtoupper($ciudad.' - '.$provincia, 'UTF-8');
        }

        return mb_strtoupper($ciudad !== '' ? $ciudad : $provincia, 'UTF-8');
    }

    /**
     * Edad en años cumplidos (enteros) entre la fecha de nacimiento y la fecha de matrícula
     * del ciclo lectivo actual (año de login). Si falta la fecha de matrícula, se usa
     * el 31/12 del año del ciclo como referencia.
     */
    private static function calcularEdad(?Carbon $fechaNac, ?Carbon $fechaMatricula, ?int $anoCiclo): string
    {
        if ($fechaNac === null) {
            return '';
        }

        $referencia = $fechaMatricula;
        if ($referencia === null && $anoCiclo !== null && $anoCiclo > 0) {
            $referencia = Carbon::createFromDate($anoCiclo, 12, 31)->startOfDay();
        }

        if ($referencia === null || $referencia->lt($fechaNac)) {
            return '';
        }

        return (string) $fechaNac->diff($referencia)->y;
    }
}
