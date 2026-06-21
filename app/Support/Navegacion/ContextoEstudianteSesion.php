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

    public const COOPERADORA_PAGOS_ESTUDIANTE = 'cooperadora_pagos_estudiante';

    public const VISTA_CUOTAS_ANIO = 'anio';

    public const VISTA_CUOTAS_HISTORIAL = 'historial';

    private const SESSION_KEY = 'contexto_estudiante_navegacion';

    private const TTL_MINUTES = 120;

    /**
     * @param  array{matricula?: int, curso?: int, idLegajos?: int, idCuotaGenerada?: int|null, idsCuotasGeneradas?: list<int>|null, idCuotaPago?: int|null, materia?: int, tipo?: int|string, desde?: string, hasta?: string, vistaCuotas?: string|null}  $datos
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

        if (array_key_exists('idLegajos', $datos)) {
            $nuevoLegajo = (int) ($datos['idLegajos'] ?? 0);
            $legajoPrevio = (int) ($previo['idLegajos'] ?? 0);
            if ($nuevoLegajo > 0 && $legajoPrevio > 0 && $nuevoLegajo !== $legajoPrevio && ! array_key_exists('vistaCuotas', $datos)) {
                unset($fusion['vistaCuotas']);
            }
        }

        if (array_key_exists('vistaCuotas', $datos)) {
            $rawVista = $datos['vistaCuotas'];
            if ($rawVista === null || $rawVista === '') {
                unset($fusion['vistaCuotas']);
            } else {
                $vista = self::normalizarVistaCuotas($rawVista);
                if ($vista === null) {
                    unset($fusion['vistaCuotas']);
                } else {
                    $fusion['vistaCuotas'] = $vista;
                }
            }
        }

        if (array_key_exists('idsCuotasGeneradas', $datos)) {
            $ids = self::normalizarIdsCuotasGeneradas($datos['idsCuotasGeneradas']);
            if ($ids === []) {
                unset($fusion['idsCuotasGeneradas']);
            } else {
                $fusion['idsCuotasGeneradas'] = $ids;
                unset($fusion['idCuotaGenerada']);
            }
        }

        if (array_key_exists('idCuotaGenerada', $datos)) {
            $idCuota = (int) ($datos['idCuotaGenerada'] ?? 0);
            if ($idCuota <= 0) {
                unset($fusion['idCuotaGenerada']);
            } else {
                $fusion['idCuotaGenerada'] = $idCuota;
                unset($fusion['idsCuotasGeneradas']);
            }
        }

        if (array_key_exists('idCuotaPago', $datos) && (int) ($datos['idCuotaPago'] ?? 0) <= 0) {
            unset($fusion['idCuotaPago']);
        }
        $fusion['expira'] = now()->addMinutes(self::TTL_MINUTES)->timestamp;

        $actual[$alcance] = $fusion;
        session([self::SESSION_KEY => $actual]);
    }

    /**
     * @return array{matricula?: int, curso?: int, idLegajos?: int, idCuotaGenerada?: int, idsCuotasGeneradas?: list<int>, idCuotaPago?: int, materia?: int, tipo?: int|string, desde?: string, hasta?: string, vistaCuotas?: string}
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

    /**
     * IDs de cuotas generadas para imputación múltiple (Gestión de aranceles).
     *
     * @return list<int>
     */
    public static function idsCuotasGeneradas(string $alcance): array
    {
        $raw = self::leer($alcance)['idsCuotasGeneradas'] ?? [];

        return self::normalizarIdsCuotasGeneradas($raw);
    }

    /**
     * Una o varias cuotas a imputar: prioriza el listado múltiple; si no, la cuota única.
     *
     * @return list<int>
     */
    public static function cuotasGeneradasParaImputar(string $alcance): array
    {
        $ids = self::idsCuotasGeneradas($alcance);
        if ($ids !== []) {
            return $ids;
        }

        $unica = self::cuotaGenerada($alcance);

        return $unica !== null ? [$unica] : [];
    }

    public static function cuotaPago(string $alcance): ?int
    {
        $id = (int) (self::leer($alcance)['idCuotaPago'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function materia(string $alcance): ?int
    {
        $id = (int) (self::leer($alcance)['materia'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function mostrarHistorialCuotas(string $alcance): bool
    {
        return (self::leer($alcance)['vistaCuotas'] ?? self::VISTA_CUOTAS_ANIO) === self::VISTA_CUOTAS_HISTORIAL;
    }

    public static function etiquetaVistaCuotas(string $alcance): string
    {
        return self::mostrarHistorialCuotas($alcance)
            ? self::VISTA_CUOTAS_HISTORIAL
            : self::VISTA_CUOTAS_ANIO;
    }

    public static function fijarVistaCuotas(string $alcance, bool $mostrarHistorial): void
    {
        self::fijar($alcance, [
            'vistaCuotas' => $mostrarHistorial ? self::VISTA_CUOTAS_HISTORIAL : self::VISTA_CUOTAS_ANIO,
        ]);
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

        foreach (['matricula', 'curso', 'idLegajos', 'idCuotaGenerada', 'idCuotaPago', 'materia', 'tipo', 'desde', 'hasta', 'fecha'] as $clave) {
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

    /**
     * @return list<int>
     */
    private static function normalizarIdsCuotasGeneradas(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $id) {
            $entero = (int) $id;
            if ($entero > 0) {
                $ids[$entero] = $entero;
            }
        }

        return array_values($ids);
    }

    private static function normalizarVistaCuotas(mixed $valor): ?string
    {
        $vista = mb_strtolower(trim((string) ($valor ?? '')));

        return match ($vista) {
            self::VISTA_CUOTAS_HISTORIAL, 'historial' => self::VISTA_CUOTAS_HISTORIAL,
            self::VISTA_CUOTAS_ANIO, 'anio', 'año' => self::VISTA_CUOTAS_ANIO,
            default => null,
        };
    }
}
