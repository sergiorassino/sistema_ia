<?php

namespace App\Support\Abm;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detecta registros que impiden borrar un legajo o una matrícula,
 * y traduce errores FK 1451 a mensajes legibles para la UI.
 *
 * Al borrar una matrícula solo se miran vínculos de ESA matrícula
 * (un año lectivo concreto), nunca el historial de años anteriores.
 */
final class LegajoDependenciasEliminacion
{
    /**
     * Tablas con columna idMatricula que bloquean el borrado de la matrícula.
     * (calificaciones se limpian en cascada al borrar matrícula; no se listan aquí.)
     *
     * @var array<string, string> tabla => etiqueta UI
     */
    private const POR_MATRICULA = [
        'cuotasgeneradas' => 'Cuotas generadas',
        'inasistencias' => 'Inasistencias',
        'sanciones' => 'Sanciones / seguimiento disciplinario',
        'infoxobse' => 'Observaciones de informe',
        'calificaciones_obs' => 'Observaciones de calificaciones',
    ];

    /**
     * Tablas con columna idLegajos que bloquean el borrado del legajo.
     * El detalle de inasistencias/sanciones/cuotas por año se revisa
     * al intentar borrar cada matrícula.
     *
     * @var array<string, string>
     */
    private const POR_LEGAJO_DIRECTO = [
        'matricula' => 'Matrículas',
        'calificaciones' => 'Calificaciones',
        'cuotasgeneradas' => 'Cuotas generadas',
        'ief' => 'Registros IEF',
        'apf' => 'Vínculos familiares',
        'variosalumnos' => 'Datos varios',
    ];

    /**
     * Mapeo de nombres de tabla en mensajes MySQL → etiqueta amigable.
     *
     * @var array<string, string>
     */
    private const TABLAS_EN_ERROR_FK = [
        'cuotasgeneradas' => 'cuotas generadas',
        'inasistencias' => 'inasistencias',
        'sanciones' => 'sanciones / seguimiento disciplinario',
        'calificaciones' => 'calificaciones',
        'matricula' => 'matrículas',
        'ief' => 'registros IEF',
        'apf' => 'vínculos familiares',
        'variosalumnos' => 'datos varios',
        'infoxobse' => 'observaciones de informe',
        'calificaciones_obs' => 'observaciones de calificaciones',
        'cuotaspagos' => 'pagos de cuotas',
    ];

    /**
     * Dependencias de una matrícula concreta (un solo año lectivo).
     *
     * @return array<string, int> etiqueta => cantidad (> 0)
     */
    public static function paraMatricula(int $idMatricula): array
    {
        if ($idMatricula < 1 || ! Schema::hasTable('matricula')) {
            return [];
        }

        $matricula = DB::table('matricula')
            ->where('id', $idMatricula)
            ->first(['id', 'idTerlec', 'idLegajos']);

        if ($matricula === null) {
            return [];
        }

        $idTerlec = (int) ($matricula->idTerlec ?? 0);
        $idLegajos = (int) ($matricula->idLegajos ?? 0);

        $deps = [];
        foreach (self::POR_MATRICULA as $tabla => $etiqueta) {
            $cant = self::contarDeMatriculaAnio($tabla, $idMatricula, $idTerlec, $idLegajos);
            if ($cant > 0) {
                $deps[$etiqueta] = $cant;
            }
        }

        return $deps;
    }

    /**
     * Dependencias directas del legajo (sin acumular inasistencias/sanciones
     * de todos los años: eso se informa al borrar cada matrícula).
     *
     * @return array<string, int> etiqueta => cantidad (> 0)
     */
    public static function paraLegajo(int $idLegajo): array
    {
        if ($idLegajo < 1) {
            return [];
        }

        $deps = [];
        foreach (self::POR_LEGAJO_DIRECTO as $tabla => $etiqueta) {
            $cant = self::contarSiExiste($tabla, 'idLegajos', $idLegajo);
            if ($cant > 0) {
                $deps[$etiqueta] = $cant;
            }
        }

        return $deps;
    }

    /**
     * Formato: "Cuotas generadas (15), Inasistencias (3)".
     *
     * @param  array<string, int>  $deps
     */
    public static function resumen(array $deps): string
    {
        return collect($deps)
            ->map(fn (int $cant, string $modulo) => "{$modulo} ({$cant})")
            ->implode(', ');
    }

    /**
     * Traduce SQLSTATE 23000 / errno 1451 a un mensaje usable en la UI.
     */
    public static function mensajeDesdeQueryException(
        QueryException $exception,
        string $entidad = 'el registro',
    ): ?string {
        $errno = (int) ($exception->errorInfo[1] ?? 0);
        $message = $exception->getMessage();

        if ($errno !== 1451 && ! str_contains(mb_strtolower($message), 'foreign key constraint')) {
            return null;
        }

        $encontradas = [];
        foreach (self::TABLAS_EN_ERROR_FK as $tabla => $label) {
            if (stripos($message, $tabla) !== false) {
                $encontradas[] = $label;
            }
        }

        $encontradas = array_values(array_unique($encontradas));
        if ($encontradas !== []) {
            $detalle = implode(', ', $encontradas);

            return "No se puede eliminar {$entidad} porque tiene registros relacionados en: {$detalle}.";
        }

        return "No se puede eliminar {$entidad} porque tiene registros relacionados en otros módulos del sistema.";
    }

    /**
     * Cuenta solo lo vinculado a esta matrícula (un año).
     * No usa idLegajos suelto: eso mezclaría años anteriores.
     */
    private static function contarDeMatriculaAnio(
        string $tabla,
        int $idMatricula,
        int $idTerlec,
        int $idLegajos,
    ): int {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'idMatricula')) {
            return 0;
        }

        try {
            // Vínculo directo a esta matrícula (= este año lectivo).
            $cant = (int) DB::table($tabla)->where('idMatricula', $idMatricula)->count();

            // Cuotas del mismo legajo+ciclo aún sin idMatricula: son del año
            // que se borra, no del historial de otros terlec.
            if ($tabla === 'cuotasgeneradas' && $idTerlec > 0 && $idLegajos > 0
                && Schema::hasColumn($tabla, 'idTerlec')
                && Schema::hasColumn($tabla, 'idLegajos')
            ) {
                $sinMatricula = (int) DB::table($tabla)
                    ->where('idTerlec', $idTerlec)
                    ->where('idLegajos', $idLegajos)
                    ->where(function ($q) {
                        $q->whereNull('idMatricula')
                            ->orWhere('idMatricula', 0);
                    })
                    ->count();

                $cant += $sinMatricula;
            }

            return $cant;
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function contarSiExiste(string $tabla, string $columna, int $id): int
    {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna)) {
            return 0;
        }

        try {
            return (int) DB::table($tabla)->where($columna, $id)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
