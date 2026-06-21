<?php

namespace App\Support\Afip;

/**
 * URL del código QR AFIP (RG 4892) para comprobantes electrónicos.
 */
final class AfipComprobanteQrUrl
{
    /**
     * @param  array{
     *     fecha_yyyy_mm_dd: string,
     *     cuit: string,
     *     pto_vta: int,
     *     tipo_cmp: int,
     *     nro_cmp: int,
     *     importe: float,
     *     doc_tipo: int,
     *     doc_nro: int,
     *     cae: string
     * }  $datos
     */
    public static function generar(array $datos): string
    {
        $payload = [
            'ver' => 1,
            'fecha' => (string) $datos['fecha_yyyy_mm_dd'],
            'cuit' => (int) preg_replace('/\D/', '', (string) $datos['cuit']),
            'ptoVta' => (int) $datos['pto_vta'],
            'tipoCmp' => (int) $datos['tipo_cmp'],
            'nroCmp' => (int) $datos['nro_cmp'],
            'importe' => round((float) $datos['importe'], 2),
            'moneda' => 'PES',
            'ctz' => 1,
            'tipoDocRec' => (int) $datos['doc_tipo'],
            'nroDocRec' => (int) preg_replace('/\D/', '', (string) $datos['doc_nro']),
            'tipoCodAut' => 'E',
            'codAut' => (int) preg_replace('/\D/', '', (string) $datos['cae']),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '';
        }

        return 'https://www.afip.gob.ar/fe/qr/?p='.base64_encode($json);
    }
}
