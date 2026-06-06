<?php

namespace App\Support\Navegacion;

/**
 * Contexto de navegación (matrícula, curso, legajo) en sesión — evita IDs sensibles en la URL.
 */
final class ContextoEstudianteSesion
{
    public const SEGUIMIENTO_INASISTENCIAS = 'seguimiento_inasistencias';

    public const SEGUIMIENTO_DISCIPLINARIO = 'seguimiento_disciplinario';

    public const SEGUIMIENTO_DISCIPLINARIO_ANTECEDENTES = 'seguimiento_disciplinario_antecedentes';

    public const PORTAL_DOCENTE_CUADERNO = 'portal_docente_cuaderno';

    public const MATRIZ_ANALITICOS = 'matriz_analiticos';

    public const EXAMENES_MATERIAS_ADEUDADAS = 'examenes_materias_adeudadas';

    public const CIERRE_ANUAL_SECUNDARIO = 'cierre_anual_secundario';

    public const SOLICITUD_EVALUACION = 'solicitud_evaluacion';

    public const LEGAJO_ABM = 'legajo_abm';

    public const CUOTAS_GESTION = 'cuotas_gestion';

    private const SESSION_KEY = 'contexto_estudiante_navegacion';

    private const TTL_MINUTES = 120;

    /**
     * @param  array{matricula?: int, curso?: int, idLegajos?: int, idCuotaGenerada?: int|null, materia?: int, tipo?: int|string, desde?: string, hasta?: string}  $datos
     */
    public static function fijar(string $alcance, array $datos): void
    {
        $alcance = self::normalizarAlcance($alcance);
        $actual = session(self::SESSION_KEY, []);
        if (! is_array($actual)) {
            $actual = [];
        }

        $previo = $actual[$alcance] ?? [];
        if (! is_array($previo)) {
            $previo = [];
        }

        $fusion = array_merge($previo, self::filtrarDatos($datos));
        if (array_key_exists('idCuotaGenerada', $datos) && (int) ($datos['idCuotaGenerada'] ?? 0) <= 0) {
            unset($fusion['idCuotaGenerada']);
        }
        $fusion['expira'] = now()->addMinutes(self::TTL_MINUTES)->timestamp;

        $actual[$alcance] = $fusion;
        session([self::SESSION_KEY => $actual]);
    }

    /**
     * @return array{matricula?: int, curso?: int, idLegajos?: int, idCuotaGenerada?: int, materia?: int, tipo?: int|string, desde?: string, hasta?: string}
     */
    public static function leer(string $alcance): array
    {
        $alcance = self::normalizarAlcance($alcance);
        $actual = session(self::SESSION_KEY, []);
        if (! is_array($actual)) {
            return [];
        }

        $data = $actual[$alcance] ?? null;
        if (! is_array($data)) {
            return [];
        }

        if ((int) ($data['expira'] ?? 0) < now()->timestamp) {
            self::limpiar($alcance);

            return [];
        }

        unset($data['expira']);

        return $data;
    }

    public static function matricula(string $alcance): ?int
    {
        $id = (int) (self::leer($alcance)['matricula'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function curso(string $alcance): ?int
    {
        $id = (int) (self::leer($alcance)['curso'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function legajo(string $alcance): ?int
    {
        $id = (int) (self::leer($alcance)['idLegajos'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function cuotaGenerada(string $alcance): ?int
    {
        $id = (int) (self::leer($alcance)['idCuotaGenerada'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function materia(string $alcance): ?int
    {
        $id = (int) (self::leer($alcance)['materia'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function limpiar(string $alcance): void
    {
        $alcance = self::normalizarAlcance($alcance);
        $actual = session(self::SESSION_KEY, []);
        if (! is_array($actual)) {
            return;
        }

        unset($actual[$alcance]);
        session([self::SESSION_KEY => $actual]);
    }

    private static function normalizarAlcance(string $alcance): string
    {
        return trim($alcance);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private static function filtrarDatos(array $datos): array
    {
        $out = [];
        if (array_key_exists('portal_docente', $datos)) {
            $out['portal_docente'] = (int) $datos['portal_docente'] === 1 ? 1 : 0;
        }

        foreach (['matricula', 'curso', 'idLegajos', 'idCuotaGenerada', 'materia', 'tipo', 'desde', 'hasta', 'fecha'] as $clave) {
            if (! array_key_exists($clave, $datos)) {
                continue;
            }
            $valor = $datos[$clave];
            if ($clave === 'desde' || $clave === 'hasta' || $clave === 'fecha') {
                $out[$clave] = is_scalar($valor) ? trim((string) $valor) : '';

                continue;
            }
            if ($clave === 'tipo') {
                $out[$clave] = is_scalar($valor) ? (string) $valor : '';

                continue;
            }
            $entero = (int) $valor;
            if ($entero > 0) {
                $out[$clave] = $entero;
            }
        }

        return $out;
    }
}
