<?php

namespace App\Support\Cuotas\Siro;

use App\Models\Ento;

/**
 * Código de pago electrónico SIRO (19 dígitos).
 *
 * Formato legacy: {@code prefijo(2)} + legajo (7) + {@see Ento::$siroIdentCuenta} (10).
 * Prefijo, cuenta y demás datos SIRO se leen del {@see Ento} del nivel del curso del alumno.
 */
final class SiroCodigoPagoElectronico
{
    public static function generar(int $idLegajos, ?int $idNivel = null): string
    {
        $ento = self::entoPorNivel($idNivel);

        return self::bloqueLegajoNueveDigitosDesdeEnto($idLegajos, $ento).self::cuentaDesdeEnto($ento);
    }

    /**
     * Primeros 9 dígitos del CPE (prefijo + legajo). Usado en QR SIRO.
     */
    public static function bloqueLegajoNueveDigitos(int $idLegajos, ?int $idNivel = null): string
    {
        return self::bloqueLegajoNueveDigitosDesdeEnto($idLegajos, self::entoPorNivel($idNivel));
    }

    public static function bloqueLegajoNueveDigitosDesdeEnto(int $idLegajos, ?Ento $ento): string
    {
        return self::prefijoDosDigitos($ento).str_pad((string) $idLegajos, 7, '0', STR_PAD_LEFT);
    }

    /**
     * Últimos 10 dígitos del CPE (cuenta SIRO / convenio del nivel).
     */
    public static function cuentaRecaudadoraPorNivel(?int $idNivel = null): string
    {
        return self::cuentaDesdeEnto(self::entoPorNivel($idNivel));
    }

    public static function prefijoDosDigitos(?Ento $ento): string
    {
        $prefijo = trim((string) ($ento?->siroPrefijoCPE ?? ''));
        if (preg_match('/^\d{2}$/', $prefijo) === 1) {
            return $prefijo;
        }

        $tenant = tenantCuotasSiroCpePrefijo();
        if ($tenant !== null) {
            return $tenant;
        }

        $digits = preg_replace('/\D+/', '', (string) ($ento?->siroSecu ?? '')) ?? '';
        if (strlen($digits) >= 2) {
            return str_pad(substr($digits, 0, 2), 2, '0', STR_PAD_LEFT);
        }

        return '00';
    }

    /**
     * Registro `ento` del nivel del curso (configuración SIRO por nivel).
     */
    public static function entoPorNivel(?int $idNivel): ?Ento
    {
        if ($idNivel === null || $idNivel <= 0) {
            return null;
        }

        return Ento::query()->where('idNivel', $idNivel)->first();
    }

    /** CUIT numérico (solo dígitos) del `ento` del nivel indicado. */
    public static function cuitPorNivel(int $idNivel): string
    {
        return preg_replace('/\D+/', '', (string) (self::entoPorNivel($idNivel)?->cuit ?? '')) ?? '';
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
