<?php

namespace App\Support\Cuotas\Siro\Descarga;

/**
 * Parsea una línea del archivo de rendición SIRO (formato Integrado).
 *
 * Posiciones según SIRO Developers — Archivo de rendición Integrado v5.2 (base 1).
 * Líneas ≥272 incluyen id. de pago SIRO; algunos archivos de cobranza (p. ej. API BPD)
 * traen solo el bloque hasta canal (pos. 124–126) con relleno, ~226 caracteres.
 */
final class SiroDescargaRendicionLinea
{
    /** Hasta canal (pos. 124–126, base 1). */
    public const LARGO_MINIMO = 126;

    /**
     * @return array{
     *     fechaPago: string,
     *     fechaAcreditacion: string,
     *     fechVenc1: string,
     *     importePagadoCentavos: int,
     *     idUsuario: string,
     *     concepto: string,
     *     codigoBarras: string,
     *     idComprobante: string,
     *     canalAbrev: string,
     *     textoTrasCanal: string,
     *     idPagoSiro: string,
     *     idClienteExtendido: string,
     *     cadenaPago: string
     * }|null
     */
    public static function parsear(string $linea): ?array
    {
        // Algunos exportes SIRO traen BOM UTF-8 al inicio del archivo (solo 1.ª línea).
        if (str_starts_with($linea, "\xEF\xBB\xBF")) {
            $linea = substr($linea, 3);
        }
        $linea = rtrim($linea, "\r\n");
        if ($linea === '' || strlen($linea) < self::LARGO_MINIMO) {
            return null;
        }

        $idComprobante = trim(substr($linea, 103, 20));
        $canal = trim(substr($linea, 123, 3));
        // Tras el canal (pos. 127+): código/descripción de rechazo en canales tipo BPR/DDR/MCR/VSR.
        $textoTrasCanal = strlen($linea) > 126 ? trim(substr($linea, 126)) : '';

        $parsed = [
            'fechaPago' => substr($linea, 0, 8),
            'fechaAcreditacion' => substr($linea, 8, 8),
            'fechVenc1' => substr($linea, 16, 8),
            'importePagadoCentavos' => (int) substr($linea, 24, 11),
            'idUsuario' => trim(substr($linea, 35, 8)),
            'concepto' => substr($linea, 43, 1),
            'codigoBarras' => self::extraerCodigoBarras($linea),
            'idComprobante' => $idComprobante,
            'canalAbrev' => $canal,
            'textoTrasCanal' => $textoTrasCanal,
            'idPagoSiro' => strlen($linea) >= 236 ? trim(substr($linea, 226, 10)) : '',
            'cadenaPago' => $linea,
        ];
        $parsed['idClienteExtendido'] = SiroDescargaRendicionIdClienteExtendido::identUsuario15($parsed);

        return $parsed;
    }

    /**
     * Algunos archivos traen ceros de más antes del prefijo 0448/0449 en el campo de barras.
     */
    private static function extraerCodigoBarras(string $linea): string
    {
        if (preg_match('/04(?:48|49)\d{55}/', $linea, $coincidencia) === 1) {
            return substr($coincidencia[0], 0, 59);
        }

        return substr($linea, 44, 59);
    }

    public static function importeDesdeCentavos(int $centavos): float
    {
        return round($centavos / 100, 2);
    }

    public static function fechaDesdeSiro(string $ymd): ?string
    {
        if (strlen($ymd) !== 8 || ! ctype_digit($ymd) || $ymd === '00000000' || $ymd === '19000101') {
            return null;
        }

        $y = (int) substr($ymd, 0, 4);
        $m = (int) substr($ymd, 4, 2);
        $d = (int) substr($ymd, 6, 2);
        if (! checkdate($m, $d, $y)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
}
