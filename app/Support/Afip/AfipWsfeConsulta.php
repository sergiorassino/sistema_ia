<?php

namespace App\Support\Afip;

use RuntimeException;
use SoapClient;
use SoapFault;
use stdClass;

/**
 * Consulta de comprobantes electrónicos vía WSFEv1 (FECompConsultar).
 */
final class AfipWsfeConsulta
{
    /**
     * @param  array{
     *     produccion: bool,
     *     cert_usuario_id: string,
     *     cert_key: string,
     *     cert_crt: string,
     *     simular?: bool
     * }  $config
     * @param  array{
     *     cuit: string,
     *     pto_vta: int,
     *     cbte_tipo: int,
     *     cbte_nro: int
     * }  $consulta
     * @return array<string, mixed>
     */
    public static function consultarComprobante(array $config, array $consulta): array
    {
        $ptoVta = (int) $consulta['pto_vta'];
        $tipoCmp = (int) $consulta['cbte_tipo'];
        $cbteNro = (int) $consulta['cbte_nro'];
        $cuit = preg_replace('/\D/', '', (string) $consulta['cuit']) ?? '';

        if ($cuit === '') {
            throw new RuntimeException('Falta el CUIT emisor para consultar en AFIP.');
        }

        if ($ptoVta <= 0 || $tipoCmp <= 0 || $cbteNro <= 0) {
            throw new RuntimeException('Punto de venta, tipo y número de comprobante son obligatorios.');
        }

        if (! empty($config['simular'])) {
            return self::respuestaSimulada($ptoVta, $tipoCmp, $cbteNro);
        }

        $auth = AfipTokenAcceso::obtener($config);
        $wsdl = base_path('afipSE/wsdl/WSFEv1.wsdl');
        $location = ! empty($config['produccion'])
            ? 'https://servicios1.afip.gov.ar/wsfev1/service.asmx'
            : 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';

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

            $respuesta = $client->FECompConsultar([
                'Auth' => [
                    'Token' => $auth['token'],
                    'Sign' => $auth['sign'],
                    'Cuit' => $cuit,
                ],
                'FeCompConsReq' => [
                    'CbteTipo' => $tipoCmp,
                    'PtoVta' => $ptoVta,
                    'CbteNro' => $cbteNro,
                ],
            ]);
        } catch (SoapFault $e) {
            throw new RuntimeException('AFIP WSFE: '.$e->getMessage(), 0, $e);
        }

        $resultado = $respuesta->FECompConsultarResult ?? null;
        if (! $resultado instanceof stdClass) {
            throw new RuntimeException('AFIP no devolvió respuesta para la consulta.');
        }

        $mensajeError = self::mensajeErroresAfip($resultado->Errors ?? null);
        if ($mensajeError !== '') {
            throw new RuntimeException($mensajeError);
        }

        $detalle = $resultado->ResultGet ?? null;
        if (! $detalle instanceof stdClass) {
            throw new RuntimeException('AFIP no encontró el comprobante solicitado.');
        }

        return self::normalizarDetalle($detalle);
    }

    /**
     * @return array<string, mixed>
     */
    private static function respuestaSimulada(int $ptoVta, int $tipoCmp, int $cbteNro): array
    {
        return [
            'simulado' => true,
            'pto_vta' => $ptoVta,
            'cbte_tipo' => $tipoCmp,
            'cbte_nro' => $cbteNro,
            'cbte_desde' => $cbteNro,
            'cbte_hasta' => $cbteNro,
            'fecha_emision' => now()->format('d/m/Y'),
            'fecha_emision_ymd' => now()->format('Ymd'),
            'doc_tipo' => 96,
            'doc_nro' => '0',
            'importe_total' => 0.0,
            'importe_neto' => 0.0,
            'importe_exento' => 0.0,
            'importe_iva' => 0.0,
            'importe_tributos' => 0.0,
            'cae' => 'SIM'.now()->format('ymdHis'),
            'vto_cae' => now()->addDays(10)->format('d/m/Y'),
            'vto_cae_ymd' => now()->addDays(10)->format('Ymd'),
            'moneda' => 'PES',
            'moneda_cotiz' => 1.0,
            'condicion_iva_receptor_id' => null,
            'resultado' => 'A',
            'iva' => [],
            'tributos' => [],
            'comprobantes_asociados' => [],
            'servicio_desde' => null,
            'servicio_hasta' => null,
            'vto_pago' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizarDetalle(stdClass $detalle): array
    {
        $iva = [];
        if (isset($detalle->Iva->AlicIva)) {
            $items = is_array($detalle->Iva->AlicIva)
                ? $detalle->Iva->AlicIva
                : [$detalle->Iva->AlicIva];
            foreach ($items as $item) {
                if (! $item instanceof stdClass) {
                    continue;
                }
                $iva[] = [
                    'id' => (int) ($item->Id ?? 0),
                    'base' => round((float) ($item->BaseImp ?? 0), 2),
                    'importe' => round((float) ($item->Importe ?? 0), 2),
                ];
            }
        }

        $tributos = [];
        if (isset($detalle->Tributos->Tributo)) {
            $items = is_array($detalle->Tributos->Tributo)
                ? $detalle->Tributos->Tributo
                : [$detalle->Tributos->Tributo];
            foreach ($items as $item) {
                if (! $item instanceof stdClass) {
                    continue;
                }
                $tributos[] = [
                    'id' => (int) ($item->Id ?? 0),
                    'desc' => trim((string) ($item->Desc ?? '')),
                    'base' => round((float) ($item->BaseImp ?? 0), 2),
                    'importe' => round((float) ($item->Importe ?? 0), 2),
                ];
            }
        }

        $asociados = [];
        if (isset($detalle->CbtesAsoc->CbteAsoc)) {
            $items = is_array($detalle->CbtesAsoc->CbteAsoc)
                ? $detalle->CbtesAsoc->CbteAsoc
                : [$detalle->CbtesAsoc->CbteAsoc];
            foreach ($items as $item) {
                if (! $item instanceof stdClass) {
                    continue;
                }
                $asociados[] = [
                    'tipo' => (int) ($item->Tipo ?? 0),
                    'pto_vta' => (int) ($item->PtoVta ?? 0),
                    'nro' => (int) ($item->Nro ?? 0),
                ];
            }
        }

        $fechaYmd = trim((string) ($detalle->CbteFch ?? ''));
        $vtoCaeYmd = trim((string) ($detalle->FchVto ?? ''));

        return [
            'simulado' => false,
            'pto_vta' => (int) ($detalle->PtoVta ?? 0),
            'cbte_tipo' => (int) ($detalle->CbteTipo ?? 0),
            'cbte_nro' => (int) ($detalle->CbteDesde ?? $detalle->CbteHasta ?? 0),
            'cbte_desde' => (int) ($detalle->CbteDesde ?? 0),
            'cbte_hasta' => (int) ($detalle->CbteHasta ?? 0),
            'fecha_emision' => self::fechaYmdABarra($fechaYmd),
            'fecha_emision_ymd' => $fechaYmd,
            'doc_tipo' => (int) ($detalle->DocTipo ?? 0),
            'doc_nro' => trim((string) ($detalle->DocNro ?? '')),
            'importe_total' => round((float) ($detalle->ImpTotal ?? 0), 2),
            'importe_neto' => round((float) ($detalle->ImpNeto ?? 0), 2),
            'importe_exento' => round((float) ($detalle->ImpOpEx ?? 0), 2),
            'importe_iva' => round((float) ($detalle->ImpIVA ?? 0), 2),
            'importe_tributos' => round((float) ($detalle->ImpTrib ?? 0), 2),
            'cae' => trim((string) ($detalle->CodAutorizacion ?? '')),
            'vto_cae' => self::fechaYmdABarra($vtoCaeYmd),
            'vto_cae_ymd' => $vtoCaeYmd,
            'moneda' => trim((string) ($detalle->MonId ?? 'PES')),
            'moneda_cotiz' => round((float) ($detalle->MonCotiz ?? 1), 3),
            'condicion_iva_receptor_id' => isset($detalle->CondicionIVAReceptorId)
                ? (int) $detalle->CondicionIVAReceptorId
                : null,
            'resultado' => trim((string) ($detalle->Resultado ?? '')),
            'iva' => $iva,
            'tributos' => $tributos,
            'comprobantes_asociados' => $asociados,
            'servicio_desde' => self::fechaYmdABarra(trim((string) ($detalle->FchServDesde ?? ''))),
            'servicio_hasta' => self::fechaYmdABarra(trim((string) ($detalle->FchServHasta ?? ''))),
            'vto_pago' => self::fechaYmdABarra(trim((string) ($detalle->FchVtoPago ?? ''))),
        ];
    }

    private static function fechaYmdABarra(string $ymd): ?string
    {
        $ymd = preg_replace('/\D/', '', $ymd) ?? '';
        if (strlen($ymd) !== 8) {
            return $ymd !== '' ? $ymd : null;
        }

        return substr($ymd, 6, 2).'/'.substr($ymd, 4, 2).'/'.substr($ymd, 0, 4);
    }

    private static function mensajeErroresAfip(mixed $errors): string
    {
        if (! $errors instanceof stdClass || ! isset($errors->Err)) {
            return '';
        }

        $items = is_array($errors->Err) ? $errors->Err : [$errors->Err];
        $partes = [];
        foreach ($items as $item) {
            if (! $item instanceof stdClass) {
                continue;
            }
            $codigo = trim((string) ($item->Code ?? ''));
            $msg = trim((string) ($item->Msg ?? ''));
            if ($msg === '') {
                continue;
            }
            $partes[] = $codigo !== '' ? "[{$codigo}] {$msg}" : $msg;
        }

        return implode(' ', $partes);
    }
}
