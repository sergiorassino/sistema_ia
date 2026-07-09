<?php

namespace App\Support\Afip;

use RuntimeException;
use SoapClient;
use SoapFault;
use stdClass;

/**
 * Consulta CUIT/CUIL por DNI vía Padrón Alcance 13 (ws_sr_padron_a13).
 */
final class AfipPadronA13Consulta
{
    public const SERVICIO_WSAA = 'ws_sr_padron_a13';

    /**
     * @param  array{
     *     produccion: bool,
     *     cert_usuario_id: string,
     *     cert_key: string,
     *     cert_crt: string,
     *     simular?: bool
     * }  $config
     * @return list<string>
     */
    public static function cuitsPorDni(array $config, string $cuitRepresentada, int $dni): array
    {
        if ($dni <= 0) {
            throw new RuntimeException('El DNI ingresado no es válido.');
        }

        if (! empty($config['simular'])) {
            return self::respuestaSimulada($dni);
        }

        $cuit = preg_replace('/\D/', '', $cuitRepresentada) ?? '';
        if ($cuit === '') {
            throw new RuntimeException('Falta el CUIT de la institución para consultar en ARCA.');
        }

        $authConfig = $config;
        $authConfig['service'] = self::SERVICIO_WSAA;

        $auth = AfipTokenAcceso::obtener($authConfig);
        $wsdl = base_path('afipSE/wsdl/personaServiceA13.wsdl');
        if (! is_file($wsdl)) {
            throw new RuntimeException('No se encontró el WSDL de Padrón A13.');
        }

        $location = ! empty($config['produccion'])
            ? 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA13'
            : 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA13';

        try {
            $client = new SoapClient($wsdl, [
                'soap_version' => SOAP_1_1,
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

            $respuesta = $client->getIdPersonaListByDocumento([
                'token' => $auth['token'],
                'sign' => $auth['sign'],
                'cuitRepresentada' => $cuit,
                'documento' => (string) $dni,
            ]);
        } catch (SoapFault $e) {
            throw new RuntimeException('ARCA Padrón A13: '.$e->getMessage(), 0, $e);
        }

        return self::normalizarCuits($respuesta);
    }

    /**
     * @return list<string>
     */
    private static function respuestaSimulada(int $dni): array
    {
        $base = str_pad((string) $dni, 8, '0', STR_PAD_LEFT);

        return ['20'.$base.'0'];
    }

    /**
     * @return list<string>
     */
    private static function normalizarCuits(mixed $respuesta): array
    {
        if (! $respuesta instanceof stdClass) {
            throw new RuntimeException('ARCA no devolvió respuesta para la consulta por DNI.');
        }

        $retorno = $respuesta->idPersonaListReturn ?? null;
        if (! $retorno instanceof stdClass) {
            return [];
        }

        $idPersona = $retorno->idPersona ?? null;
        if ($idPersona === null || $idPersona === '') {
            return [];
        }

        $items = is_array($idPersona) ? $idPersona : [$idPersona];
        $cuits = [];
        foreach ($items as $item) {
            $valor = preg_replace('/\D/', '', (string) $item) ?? '';
            if ($valor !== '') {
                $cuits[] = $valor;
            }
        }

        return array_values(array_unique($cuits));
    }
}
