<?php

namespace App\Support\Cuotas\Siro;

use App\Models\Ento;

/**
 * Código de pago electrónico SIRO (19 dígitos).
 *
 * Formato: {@code 00} + legajo (7) + {@see Ento::$siroIdentCuenta} del nivel (10).
 */
final class SiroCodigoPagoElectronico
{
    public static function generar(int $idLegajos, int $idNivel): string
    {
        $prefijo = '00'.str_pad((string) $idLegajos, 7, '0', STR_PAD_LEFT);
        $cuenta = self::cuentaRecaudadoraPorNivel($idNivel);

        return $prefijo.$cuenta;
    }

    /**
     * Últimos 10 dígitos del CPE (cuenta SIRO / convenio).
     */
    public static function cuentaRecaudadoraPorNivel(int $idNivel): string
    {
        $entoNivel = Ento::query()->where('idNivel', $idNivel)->first();

        return self::cuentaDesdeEnto($entoNivel);
    }

    private static function cuentaDesdeEnto(?Ento $ento): string
    {
        if ($ento === null) {
            return str_repeat('0', 10);
        }

        $raw = trim((string) ($ento->siroIdentCuenta ?? ''));
        if ($raw === '') {
            return str_repeat('0', 10);
        }

        return self::normalizarCuentaDiezDigitos($raw);
    }

    private static function normalizarCuentaDiezDigitos(string $valor): string
    {
        $digits = preg_replace('/\D+/', '', $valor) ?? '';

        return str_pad(substr($digits, 0, 10), 10, '0', STR_PAD_LEFT);
    }
}
