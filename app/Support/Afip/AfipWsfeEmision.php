<?php

namespace App\Support\Afip;

use RuntimeException;
use SoapClient;
use SoapFault;

/**
 * Emisión de comprobantes electrónicos vía WSFEv1.
 */
final class AfipWsfeEmision
{
    /**
     * @param  array{
     *     produccion: bool,
     *     cert_usuario_id: string,
     *     cert_key: string,
     *     cert_crt: string,
     *     cbte_tipo: int,
     *     concepto: int,
     *     doc_tipo: int,
     *     nota_credito_tipo?: int
     * }  $config
     * @param  array{
     *     cuit: string,
     *     pto_vta: int,
     *     doc_nro: int,
     *     importe: float,
     *     fecha_yyyymmdd: string,
     *     fch_serv_desde: string,
     *     fch_serv_hasta: string,
     *     condicion_iva_receptor_id: int
     * }  $comprobante
     * @return array{cae: string, cae_fch_vto: string, cbte_hasta: int}
     */
    public static function emitirRecibo(array $config, array $comprobante): array
    {
        if (! empty($config['simular'])) {
            return [
                'cae' => 'SIM'.now()->format('ymdHis'),
                'cae_fch_vto' => now()->addDays(10)->format('Ymd'),
                'cbte_hasta' => 99999,
            ];
        }

        $auth = AfipTokenAcceso::obtener($config);
        $wsdl = base_path('afipSE/wsdl/WSFEv1.wsdl');
        $location = ! empty($config['produccion'])
            ? 'https://servicios1.afip.gov.ar/wsfev1/service.asmx'
            : 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';

        $cuit = preg_replace('/\D/', '', (string) $comprobante['cuit']) ?? '';
        $ptoVta = (int) $comprobante['pto_vta'];
        $tipoCmp = (int) ($comprobante['tipo_cbte'] ?? $config['cbte_tipo']);
        $importe = round((float) $comprobante['importe'], 2);

        if ($importe <= 0) {
            throw new RuntimeException('El importe a facturar debe ser mayor a cero.');
        }

        try {
            $client = new SoapClient($wsdl, [
                'soap_version' => SOAP_1_2,
                'location' => $location,
                'trace' => 1,
                'exceptions' => true,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                        'ciphers' => 'DEFAULT:@SECLEVEL=1',
                    ],
                ]),
            ]);

            $ultimo = $client->FECompUltimoAutorizado([
                'Auth' => [
                    'Token' => $auth['token'],
                    'Sign' => $auth['sign'],
                    'Cuit' => $cuit,
                ],
                'PtoVta' => $ptoVta,
                'CbteTipo' => $tipoCmp,
            ]);

            $nroSiguiente = (int) ($ultimo->FECompUltimoAutorizadoResult->CbteNro ?? 0) + 1;

            $detalles = [
                'Concepto' => (int) $config['concepto'],
                'DocTipo' => (int) $config['doc_tipo'],
                'DocNro' => (int) $comprobante['doc_nro'],
                'CbteDesde' => $nroSiguiente,
                'CbteHasta' => $nroSiguiente,
                'CbteFch' => (string) $comprobante['fecha_yyyymmdd'],
                'FchServDesde' => (string) $comprobante['fch_serv_desde'],
                'FchServHasta' => (string) $comprobante['fch_serv_hasta'],
                'FchVtoPago' => (string) $comprobante['fecha_yyyymmdd'],
                'ImpTotal' => $importe,
                'ImpTotConc' => 0.00,
                'ImpNeto' => $importe,
                'ImpOpEx' => 0.00,
                'ImpIVA' => 0.00,
                'ImpTrib' => 0.00,
                'MonId' => 'PES',
                'MonCotiz' => 1.000,
                'CondicionIVAReceptorId' => (int) $comprobante['condicion_iva_receptor_id'],
            ];

            $notaCreditoTipo = (int) ($config['nota_credito_tipo'] ?? 0);
            if ($tipoCmp === $notaCreditoTipo && $notaCreditoTipo > 0) {
                $nroAsoc = (int) ($comprobante['cbte_asoc_nro'] ?? 0);
                if ($nroAsoc <= 0) {
                    throw new RuntimeException('Falta el comprobante asociado para la nota de crédito.');
                }
                $detalles['CbtesAsoc'] = [
                    'CbteAsoc' => [[
                        'Tipo' => (int) ($comprobante['cbte_asoc_tipo'] ?? $config['cbte_tipo_asociado'] ?? $config['cbte_tipo']),
                        'PtoVta' => (int) ($comprobante['cbte_asoc_pto_vta'] ?? $ptoVta),
                        'Nro' => $nroAsoc,
                    ]],
                ];
                $detalles['Motivo'] = (string) ($comprobante['motivo_nc'] ?? 'Anulación de comprobante por error de facturación');
            }

            $respuesta = $client->FECAESolicitar([
                'Auth' => [
                    'Token' => $auth['token'],
                    'Sign' => $auth['sign'],
                    'Cuit' => $cuit,
                ],
                'FeCAEReq' => [
                    'FeCabReq' => [
                        'CantReg' => 1,
                        'PtoVta' => $ptoVta,
                        'CbteTipo' => $tipoCmp,
                    ],
                    'FeDetReq' => [
                        'FECAEDetRequest' => $detalles,
                    ],
                ],
            ]);
        } catch (SoapFault $e) {
            throw new RuntimeException('AFIP WSFE: '.$e->getMessage(), 0, $e);
        }

        $detalle = $respuesta->FECAESolicitarResult->FeDetResp->FECAEDetResponse ?? null;
        if ($detalle === null) {
            throw new RuntimeException('AFIP no devolvió detalle del comprobante.');
        }

        $resultado = (string) ($detalle->Resultado ?? '');
        if ($resultado !== '' && $resultado !== 'A') {
            $obs = '';
            if (isset($detalle->Observaciones->Obs)) {
                $items = is_array($detalle->Observaciones->Obs)
                    ? $detalle->Observaciones->Obs
                    : [$detalle->Observaciones->Obs];
                $partes = [];
                foreach ($items as $item) {
                    $partes[] = trim((string) ($item->Msg ?? ''));
                }
                $obs = implode(' ', array_filter($partes));
            }

            throw new RuntimeException($obs !== '' ? $obs : 'AFIP rechazó el comprobante.');
        }

        $cae = trim((string) ($detalle->CAE ?? ''));
        if ($cae === '') {
            throw new RuntimeException('AFIP no otorgó CAE para el comprobante.');
        }

        return [
            'cae' => $cae,
            'cae_fch_vto' => (string) ($detalle->CAEFchVto ?? ''),
            'cbte_hasta' => (int) ($detalle->CbteHasta ?? $nroSiguiente),
        ];
    }

    /**
     * Emite varios comprobantes en un único FECAESolicitar (mismo PtoVta y CbteTipo).
     *
     * @param  array<string, mixed>  $config
     * @param  list<array<string, mixed>>  $comprobantes
     * @return list<array{ok: bool, cae?: string, cae_fch_vto?: string, cbte_hasta?: int, mensaje?: string}>
     */
    public static function emitirReciboLote(array $config, array $comprobantes): array
    {
        if ($comprobantes === []) {
            return [];
        }

        if (! empty($config['simular'])) {
            $base = 99000;
            $resultados = [];
            foreach ($comprobantes as $i => $comp) {
                $resultados[] = [
                    'ok' => true,
                    'cae' => 'SIM'.now()->format('ymdHis').str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'cae_fch_vto' => now()->addDays(10)->format('Ymd'),
                    'cbte_hasta' => $base + $i + 1,
                ];
            }

            return $resultados;
        }

        $auth = AfipTokenAcceso::obtener($config);
        $wsdl = base_path('afipSE/wsdl/WSFEv1.wsdl');
        $location = ! empty($config['produccion'])
            ? 'https://servicios1.afip.gov.ar/wsfev1/service.asmx'
            : 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';

        $primer = $comprobantes[0];
        $cuit = preg_replace('/\D/', '', (string) ($primer['cuit'] ?? '')) ?? '';
        $ptoVta = (int) ($primer['pto_vta'] ?? 0);
        $tipoCmp = (int) ($primer['tipo_cbte'] ?? $config['cbte_tipo']);

        if ($cuit === '' || $ptoVta <= 0) {
            throw new RuntimeException('Faltan CUIT o punto de venta para la emisión masiva AFIP.');
        }

        try {
            $client = new SoapClient($wsdl, [
                'soap_version' => SOAP_1_2,
                'location' => $location,
                'trace' => 1,
                'exceptions' => true,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                        'ciphers' => 'DEFAULT:@SECLEVEL=1',
                    ],
                ]),
            ]);

            $ultimo = $client->FECompUltimoAutorizado([
                'Auth' => [
                    'Token' => $auth['token'],
                    'Sign' => $auth['sign'],
                    'Cuit' => $cuit,
                ],
                'PtoVta' => $ptoVta,
                'CbteTipo' => $tipoCmp,
            ]);

            $nroSiguiente = (int) ($ultimo->FECompUltimoAutorizadoResult->CbteNro ?? 0) + 1;
            $detalles = [];

            foreach ($comprobantes as $i => $comprobante) {
                $importe = round((float) ($comprobante['importe'] ?? 0), 2);
                if ($importe <= 0) {
                    throw new RuntimeException('El importe a facturar debe ser mayor a cero.');
                }

                $cbteNro = $nroSiguiente + $i;
                $detalles[] = [
                    'Concepto' => (int) $config['concepto'],
                    'DocTipo' => (int) $config['doc_tipo'],
                    'DocNro' => (int) ($comprobante['doc_nro'] ?? 0),
                    'CbteDesde' => $cbteNro,
                    'CbteHasta' => $cbteNro,
                    'CbteFch' => (string) ($comprobante['fecha_yyyymmdd'] ?? ''),
                    'FchServDesde' => (string) ($comprobante['fch_serv_desde'] ?? ''),
                    'FchServHasta' => (string) ($comprobante['fch_serv_hasta'] ?? ''),
                    'FchVtoPago' => (string) ($comprobante['fecha_yyyymmdd'] ?? ''),
                    'ImpTotal' => $importe,
                    'ImpTotConc' => 0.00,
                    'ImpNeto' => $importe,
                    'ImpOpEx' => 0.00,
                    'ImpIVA' => 0.00,
                    'ImpTrib' => 0.00,
                    'MonId' => 'PES',
                    'MonCotiz' => 1.000,
                    'CondicionIVAReceptorId' => (int) ($comprobante['condicion_iva_receptor_id'] ?? 5),
                ];
            }

            $respuesta = $client->FECAESolicitar([
                'Auth' => [
                    'Token' => $auth['token'],
                    'Sign' => $auth['sign'],
                    'Cuit' => $cuit,
                ],
                'FeCAEReq' => [
                    'FeCabReq' => [
                        'CantReg' => count($detalles),
                        'PtoVta' => $ptoVta,
                        'CbteTipo' => $tipoCmp,
                    ],
                    'FeDetReq' => [
                        'FECAEDetRequest' => $detalles,
                    ],
                ],
            ]);
        } catch (SoapFault $e) {
            throw new RuntimeException('AFIP WSFE: '.$e->getMessage(), 0, $e);
        }

        $detResp = $respuesta->FECAESolicitarResult->FeDetResp->FECAEDetResponse ?? null;
        if ($detResp === null) {
            throw new RuntimeException('AFIP no devolvió detalle de los comprobantes.');
        }

        if (! is_array($detResp)) {
            $detResp = [$detResp];
        }

        $resultados = [];
        foreach ($detResp as $idx => $detalle) {
            $resultado = (string) ($detalle->Resultado ?? '');
            if ($resultado !== '' && $resultado !== 'A') {
                $obs = self::observacionesDetalle($detalle);
                $resultados[] = [
                    'ok' => false,
                    'mensaje' => $obs !== '' ? $obs : 'AFIP rechazó el comprobante.',
                ];

                continue;
            }

            $cae = trim((string) ($detalle->CAE ?? ''));
            if ($cae === '') {
                $resultados[] = [
                    'ok' => false,
                    'mensaje' => 'AFIP no otorgó CAE para el comprobante.',
                ];

                continue;
            }

            $resultados[] = [
                'ok' => true,
                'cae' => $cae,
                'cae_fch_vto' => (string) ($detalle->CAEFchVto ?? ''),
                'cbte_hasta' => (int) ($detalle->CbteHasta ?? ($nroSiguiente + $idx)),
            ];
        }

        return $resultados;
    }

    private static function observacionesDetalle(object $detalle): string
    {
        if (! isset($detalle->Observaciones->Obs)) {
            return '';
        }

        $items = is_array($detalle->Observaciones->Obs)
            ? $detalle->Observaciones->Obs
            : [$detalle->Observaciones->Obs];
        $partes = [];
        foreach ($items as $item) {
            $partes[] = trim((string) ($item->Msg ?? ''));
        }

        return implode(' ', array_filter($partes));
    }
}
