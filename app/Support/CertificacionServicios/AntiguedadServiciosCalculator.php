<?php

namespace App\Support\CertificacionServicios;

use Carbon\Carbon;

/**
 * Cálculo de duración y antigüedad para Certificación de Servicios.
 *
 * - Por fila: diferencia calendario inclusiva (fin inclusive), estilo ScriptCase Dif_Datas_2.
 * - Totales: unión de intervalos (sin doble conteo de solapes) + normalización 30 días / 12 meses.
 * - Licencias parciales no descuentan.
 */
final class AntiguedadServiciosCalculator
{
    /**
     * Diferencia inclusiva entre dos fechas (ambas incluidas).
     *
     * @return array{anios: int, meses: int, dias: int}
     */
    public static function diffYmd(Carbon|string $inicio, Carbon|string $fin): array
    {
        $start = self::asDate($inicio);
        $end = self::asDate($fin);

        if ($end->lt($start)) {
            return ['anios' => 0, 'meses' => 0, 'dias' => 0];
        }

        $anios = $end->year - $start->year;
        $meses = $end->month - $start->month;
        $dias = $end->day - $start->day + 1;

        if ($dias < 1) {
            $meses -= 1;
            $diasDelMesAnterior = $end->copy()->startOfMonth()->subDay()->day;
            $dias += $diasDelMesAnterior;
        }

        if ($meses < 0) {
            $anios -= 1;
            $meses += 12;
        }

        return [
            'anios' => max(0, $anios),
            'meses' => max(0, $meses),
            'dias' => max(0, $dias),
        ];
    }

    /**
     * @param  list<array{inicio: Carbon|string, fin: Carbon|string|null}>  $periodos
     * @return list<array{inicio: Carbon, fin: Carbon}>
     */
    public static function unirIntervalos(array $periodos, Carbon|string $fechaRef): array
    {
        $ref = self::asDate($fechaRef);
        $intervalos = [];

        foreach ($periodos as $p) {
            $inicio = self::asDate($p['inicio']);
            $finRaw = $p['fin'] ?? null;
            $fin = self::finEfectivo($finRaw, $ref);
            if ($fin->lt($inicio)) {
                continue;
            }
            $intervalos[] = ['inicio' => $inicio, 'fin' => $fin];
        }

        if ($intervalos === []) {
            return [];
        }

        usort(
            $intervalos,
            static fn (array $a, array $b): int => $a['inicio']->timestamp <=> $b['inicio']->timestamp
                ?: $a['fin']->timestamp <=> $b['fin']->timestamp
        );

        $fusionados = [];
        $actual = $intervalos[0];

        for ($i = 1, $n = count($intervalos); $i < $n; $i++) {
            $siguiente = $intervalos[$i];
            // Fechas inclusivas: adyacentes (fin+1 == inicio) también se unen.
            $finMasUno = $actual['fin']->copy()->addDay();
            if ($siguiente['inicio']->lte($finMasUno)) {
                if ($siguiente['fin']->gt($actual['fin'])) {
                    $actual['fin'] = $siguiente['fin']->copy();
                }

                continue;
            }
            $fusionados[] = $actual;
            $actual = $siguiente;
        }
        $fusionados[] = $actual;

        return $fusionados;
    }

    /**
     * Suma Y/M/D de intervalos ya fusionados y normaliza con 30 días = 1 mes, 12 meses = 1 año.
     *
     * @param  list<array{inicio: Carbon, fin: Carbon}>  $intervalos
     * @return array{anios: int, meses: int, dias: int}
     */
    public static function sumarIntervalos(array $intervalos): array
    {
        $anios = 0;
        $meses = 0;
        $dias = 0;

        foreach ($intervalos as $iv) {
            $d = self::diffYmd($iv['inicio'], $iv['fin']);
            $anios += $d['anios'];
            $meses += $d['meses'];
            $dias += $d['dias'];
        }

        return self::normalizar($anios, $meses, $dias);
    }

    /**
     * @return array{anios: int, meses: int, dias: int}
     */
    public static function normalizar(int $anios, int $meses, int $dias): array
    {
        while ($dias >= 30) {
            $dias -= 30;
            $meses += 1;
        }
        while ($meses >= 12) {
            $meses -= 12;
            $anios += 1;
        }

        return ['anios' => $anios, 'meses' => $meses, 'dias' => $dias];
    }

    /**
     * Resta Y/M/D con préstamo 30/12 (legacy ScriptCase).
     *
     * @param  array{anios: int, meses: int, dias: int}  $minuendo
     * @param  array{anios: int, meses: int, dias: int}  $sustraendo
     * @return array{ok: bool, anios: int, meses: int, dias: int}
     */
    public static function restarYmd(array $minuendo, array $sustraendo): array
    {
        $anios = (int) $minuendo['anios'];
        $meses = (int) $minuendo['meses'];
        $dias = (int) $minuendo['dias'];
        $sa = (int) $sustraendo['anios'];
        $sm = (int) $sustraendo['meses'];
        $sd = (int) $sustraendo['dias'];

        while ($dias < $sd) {
            if ($meses > 0) {
                $dias += 30;
                $meses -= 1;
            } elseif ($anios > 0) {
                $anios -= 1;
                $meses += 12;
            } else {
                return ['ok' => false, 'anios' => 0, 'meses' => 0, 'dias' => 0];
            }
        }
        $dias -= $sd;

        while ($meses < $sm) {
            if ($anios > 0) {
                $anios -= 1;
                $meses += 12;
            } else {
                return ['ok' => false, 'anios' => 0, 'meses' => 0, 'dias' => 0];
            }
        }
        $meses -= $sm;

        if ($anios < $sa) {
            return ['ok' => false, 'anios' => 0, 'meses' => 0, 'dias' => 0];
        }
        $anios -= $sa;

        $norm = self::normalizar($anios, $meses, $dias);

        return ['ok' => true, 'anios' => $norm['anios'], 'meses' => $norm['meses'], 'dias' => $norm['dias']];
    }

    /**
     * @param  list<array{fechaAlta: mixed, fechaBaja?: mixed}>  $servicios
     * @param  list<array{fechaInicio: mixed, fechaFin: mixed, parcial: mixed}>  $licencias
     * @return array{
     *     subtotal: array{anios: int, meses: int, dias: int},
     *     descuentoLicencias: array{anios: int, meses: int, dias: int},
     *     antiguedad: array{ok: bool, anios: int, meses: int, dias: int},
     *     filasServicios: list<array{anios: int, meses: int, dias: int}>,
     *     filasLicencias: list<array{anios: int, meses: int, dias: int}>
     * }
     */
    public static function calcular(array $servicios, array $licencias, Carbon|string $fechaRef): array
    {
        $ref = self::asDate($fechaRef);

        $filasServicios = [];
        $periodosServicio = [];
        foreach ($servicios as $s) {
            if (self::fechaVacia($s['fechaAlta'] ?? null)) {
                $filasServicios[] = ['anios' => 0, 'meses' => 0, 'dias' => 0];

                continue;
            }
            $alta = self::asDate($s['fechaAlta'] ?? null);
            $baja = self::finEfectivo($s['fechaBaja'] ?? null, $ref);
            $filasServicios[] = self::diffYmd($alta, $baja);
            $periodosServicio[] = ['inicio' => $alta, 'fin' => $s['fechaBaja'] ?? null];
        }

        $filasLicencias = [];
        $periodosLicNoParcial = [];
        foreach ($licencias as $l) {
            if (self::fechaVacia($l['fechaInicio'] ?? null) || self::fechaVacia($l['fechaFin'] ?? null)) {
                $filasLicencias[] = ['anios' => 0, 'meses' => 0, 'dias' => 0];

                continue;
            }
            $ini = self::asDate($l['fechaInicio'] ?? null);
            $fin = self::asDate($l['fechaFin'] ?? null);
            $filasLicencias[] = self::diffYmd($ini, $fin);
            $parcial = $l['parcial'] ?? null;
            // Solo descuenta si está explícitamente en No (0). Vacío o Sí → no descuenta.
            if ($parcial !== null && $parcial !== '' && ! self::esParcial($parcial)) {
                $periodosLicNoParcial[] = ['inicio' => $ini, 'fin' => $fin];
            }
        }

        $subtotal = self::sumarIntervalos(self::unirIntervalos($periodosServicio, $ref));
        $descuento = self::sumarIntervalos(self::unirIntervalos($periodosLicNoParcial, $ref));
        $antiguedad = self::restarYmd($subtotal, $descuento);

        return [
            'subtotal' => $subtotal,
            'descuentoLicencias' => $descuento,
            'antiguedad' => $antiguedad,
            'filasServicios' => $filasServicios,
            'filasLicencias' => $filasLicencias,
        ];
    }

    public static function finEfectivo(mixed $fechaFin, Carbon $fechaRef): Carbon
    {
        if (self::fechaVacia($fechaFin)) {
            return $fechaRef->copy()->startOfDay();
        }

        return self::asDate($fechaFin);
    }

    public static function fechaVacia(mixed $fecha): bool
    {
        if ($fecha === null || $fecha === '') {
            return true;
        }
        $s = trim((string) $fecha);

        return $s === '' || $s === '0000-00-00' || str_starts_with($s, '0000-00-00');
    }

    public static function esParcial(mixed $parcial): bool
    {
        if (is_bool($parcial)) {
            return $parcial;
        }
        $s = strtolower(trim((string) $parcial));

        return in_array($s, ['1', 'si', 'sí', 'true', 'yes'], true);
    }

    private static function asDate(mixed $fecha): Carbon
    {
        if ($fecha instanceof Carbon) {
            return $fecha->copy()->startOfDay();
        }

        $s = trim((string) ($fecha ?? ''));
        if ($s === '' || str_starts_with($s, '0000-00-00')) {
            return Carbon::today()->startOfDay();
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s) === 1) {
            return Carbon::createFromFormat('d/m/Y', $s)->startOfDay();
        }

        return Carbon::parse($s)->startOfDay();
    }
}
