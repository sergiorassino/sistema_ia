<?php

namespace App\Support\Cuotas\Siro;

use App\Models\Ento;

/**
 * Código de pago electrónico SIRO (19 dígitos).
 *
 * Formato: {@code prefijo(2)} + legajo (7) + {@see Ento::$siroIdentCuenta} (10).
 * Prefijo, cuenta y mensaje SIRO se leen solo del {@see Ento} del nivel (sin fallbacks).
 */
final class SiroCodigoPagoElectronico
{
    public static function generar(int $idLegajos, ?int $idNivel = null): string
    {
        $ento = self::exigirParaCpe($idNivel);

        return self::bloqueLegajoNueveDigitosDesdeEnto($idLegajos, $ento).self::cuentaDesdeEnto($ento);
    }

    /**
     * Primeros 9 dígitos del CPE (prefijo + legajo). Usado en QR SIRO.
     */
    public static function bloqueLegajoNueveDigitos(int $idLegajos, ?int $idNivel = null): string
    {
        return self::bloqueLegajoNueveDigitosDesdeEnto($idLegajos, self::exigirParaCpe($idNivel));
    }

    public static function bloqueLegajoNueveDigitosDesdeEnto(int $idLegajos, Ento $ento): string
    {
        self::exigirCamposCpeEnEnto($ento, null);

        return self::prefijoDosDigitos($ento).str_pad((string) $idLegajos, 7, '0', STR_PAD_LEFT);
    }

    /**
     * Últimos 10 dígitos del CPE (cuenta SIRO / convenio del nivel).
     */
    public static function cuentaRecaudadoraPorNivel(?int $idNivel = null): string
    {
        return self::cuentaDesdeEnto(self::exigirParaCpe($idNivel));
    }

    /**
     * Prefijo CPE de 2 dígitos desde {@see Ento::$siroPrefijoCPE} (obligatorio).
     *
     * @throws SiroConfiguracionIncompletaException
     */
    public static function prefijoDosDigitos(Ento $ento): string
    {
        $prefijo = self::prefijoNormalizado($ento);
        if ($prefijo === null) {
            throw new SiroConfiguracionIncompletaException(['Prefijo CPE'], (int) ($ento->idNivel ?? 0) ?: null);
        }

        return $prefijo;
    }

    /**
     * Valida prefijo + cuenta + mensaje del nivel. Usar antes de cupón / subida SIRO.
     *
     * @throws SiroConfiguracionIncompletaException
     */
    public static function exigirParaOperacion(?int $idNivel): Ento
    {
        $ento = self::entoExigido($idNivel);
        $faltantes = self::faltantesParaOperacion($ento);
        if ($faltantes !== []) {
            throw new SiroConfiguracionIncompletaException($faltantes, $idNivel);
        }

        return $ento;
    }

    /**
     * Valida prefijo + cuenta del nivel (armado de CPE).
     *
     * @throws SiroConfiguracionIncompletaException
     */
    public static function exigirParaCpe(?int $idNivel): Ento
    {
        $ento = self::entoExigido($idNivel);
        self::exigirCamposCpeEnEnto($ento, $idNivel);

        return $ento;
    }

    /**
     * @return list<string>
     */
    public static function faltantesParaOperacion(?Ento $ento): array
    {
        $faltantes = self::faltantesParaCpe($ento);
        if (self::mensajeTicketNormalizado($ento) === null) {
            $faltantes[] = 'Mensaje en ticket / pantalla SIRO';
        }

        return $faltantes;
    }

    /**
     * @return list<string>
     */
    public static function faltantesParaCpe(?Ento $ento): array
    {
        $faltantes = [];
        if (self::prefijoNormalizado($ento) === null) {
            $faltantes[] = 'Prefijo CPE';
        }
        if (self::cuentaNormalizada($ento) === null) {
            $faltantes[] = 'Cuenta recaudadora SIRO';
        }

        return $faltantes;
    }

    public static function mensajeTicketDesdeEnto(Ento $ento): string
    {
        $mensaje = self::mensajeTicketNormalizado($ento);
        if ($mensaje === null) {
            throw new SiroConfiguracionIncompletaException(
                ['Mensaje en ticket / pantalla SIRO'],
                (int) ($ento->idNivel ?? 0) ?: null,
            );
        }

        return $mensaje;
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

    /**
     * @throws SiroConfiguracionIncompletaException
     */
    private static function entoExigido(?int $idNivel): Ento
    {
        if ($idNivel === null || $idNivel <= 0) {
            throw new SiroConfiguracionIncompletaException(
                ['Prefijo CPE', 'Cuenta recaudadora SIRO', 'Mensaje en ticket / pantalla SIRO'],
                $idNivel,
            );
        }

        $ento = self::entoPorNivel($idNivel);
        if ($ento === null) {
            throw new SiroConfiguracionIncompletaException(
                ['Prefijo CPE', 'Cuenta recaudadora SIRO', 'Mensaje en ticket / pantalla SIRO'],
                $idNivel,
            );
        }

        return $ento;
    }

    /**
     * @throws SiroConfiguracionIncompletaException
     */
    private static function exigirCamposCpeEnEnto(Ento $ento, ?int $idNivel): void
    {
        $faltantes = self::faltantesParaCpe($ento);
        if ($faltantes !== []) {
            throw new SiroConfiguracionIncompletaException(
                $faltantes,
                $idNivel ?? ((int) ($ento->idNivel ?? 0) ?: null),
            );
        }
    }

    private static function prefijoNormalizado(?Ento $ento): ?string
    {
        $prefijo = trim((string) ($ento?->siroPrefijoCPE ?? ''));
        if (preg_match('/^\d{2}$/', $prefijo) !== 1) {
            return null;
        }

        return $prefijo;
    }

    private static function cuentaNormalizada(?Ento $ento): ?string
    {
        if ($ento === null) {
            return null;
        }

        $raw = trim((string) ($ento->siroIdentCuenta ?? ''));
        if ($raw === '') {
            return null;
        }

        $cuenta = self::normalizarCuentaDiezDigitos($raw);
        if (preg_match('/^0+$/', $cuenta) === 1) {
            return null;
        }

        return $cuenta;
    }

    private static function mensajeTicketNormalizado(?Ento $ento): ?string
    {
        $mensaje = mb_strtoupper(trim((string) ($ento?->siroMje ?? '')));

        return $mensaje !== '' ? $mensaje : null;
    }

    private static function cuentaDesdeEnto(Ento $ento): string
    {
        $cuenta = self::cuentaNormalizada($ento);
        if ($cuenta === null) {
            throw new SiroConfiguracionIncompletaException(
                ['Cuenta recaudadora SIRO'],
                (int) ($ento->idNivel ?? 0) ?: null,
            );
        }

        return $cuenta;
    }

    private static function normalizarCuentaDiezDigitos(string $valor): string
    {
        $digits = preg_replace('/\D+/', '', $valor) ?? '';

        return str_pad(substr($digits, 0, 10), 10, '0', STR_PAD_LEFT);
    }
}
