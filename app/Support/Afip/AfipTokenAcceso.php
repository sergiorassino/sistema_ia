<?php

namespace App\Support\Afip;

use DateTime;
use DateTimeZone;
use RuntimeException;
use SimpleXMLElement;
use SoapClient;
use SoapFault;

/**
 * Ticket de acceso WSAA (TA.xml) para servicios AFIP.
 */
final class AfipTokenAcceso
{
    /**
     * @param  array{
     *     cert_usuario_id: string,
     *     cert_key: string,
     *     cert_crt: string,
     *     produccion: bool,
     *     service?: string
     * }  $config
     * @return array{token: string, sign: string}
     */
    public static function obtener(array $config): array
    {
        $service = trim((string) ($config['service'] ?? 'wsfe'));
        if ($service === '') {
            $service = 'wsfe';
        }

        $base = base_path('afipSE/cert/'.trim((string) $config['cert_usuario_id']));
        $cert = $base.'/'.trim((string) $config['cert_crt']);
        $privateKey = $base.'/'.trim((string) $config['cert_key']);
        $tra = $service === 'wsfe'
            ? $base.'/TRA.xml'
            : $base.'/TRA_'.$service.'.xml';
        $ta = self::archivoTa($base, $service);

        foreach ([$cert, $privateKey] as $archivo) {
            if (! is_file($archivo)) {
                throw new RuntimeException('No se encontró el certificado AFIP: '.$archivo);
            }
        }

        if (self::taVigente($ta)) {
            return self::leerTa($ta);
        }

        $uniqueId = time();
        $generationTime = (new DateTime('now', new DateTimeZone('UTC')))->modify('-1 minutes')->format('Y-m-d\TH:i:s').'Z';
        $expirationTime = (new DateTime('now', new DateTimeZone('UTC')))->modify('+5 minutes')->format('Y-m-d\TH:i:s').'Z';

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<loginTicketRequest version="1.0">
  <header>
    <uniqueId>{$uniqueId}</uniqueId>
    <generationTime>{$generationTime}</generationTime>
    <expirationTime>{$expirationTime}</expirationTime>
  </header>
  <service>{$service}</service>
</loginTicketRequest>
XML;

        file_put_contents($tra, $xml);

        $cmsTmp = tempnam(sys_get_temp_dir(), 'afip_cms_');
        if ($cmsTmp === false) {
            throw new RuntimeException('No se pudo crear archivo temporal para firmar el TRA.');
        }

        $opensslBin = self::opensslBinario();
        $cmd = sprintf(
            '%s smime -sign -signer %s -inkey %s -in %s -out %s -outform DER -nodetach 2>&1',
            escapeshellarg($opensslBin),
            escapeshellarg($cert),
            escapeshellarg($privateKey),
            escapeshellarg($tra),
            escapeshellarg($cmsTmp),
        );

        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            @unlink($cmsTmp);

            throw new RuntimeException('Error al firmar el TRA AFIP: '.implode("\n", $output));
        }

        $cms = file_get_contents($cmsTmp);
        @unlink($cmsTmp);
        if ($cms === false || $cms === '') {
            throw new RuntimeException('No se pudo leer el CMS firmado del TRA.');
        }

        $wsaaUrl = ! empty($config['produccion'])
            ? 'https://wsaa.afip.gov.ar/ws/services/LoginCms?WSDL'
            : 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?WSDL';

        try {
            $client = new SoapClient($wsaaUrl, [
                'trace' => 1,
                'exceptions' => true,
            ]);

            $result = $client->loginCms([
                'in0' => base64_encode($cms),
            ]);
        } catch (SoapFault $e) {
            throw new RuntimeException('AFIP WSAA: '.$e->getMessage(), 0, $e);
        }

        file_put_contents($ta, (string) $result->loginCmsReturn);

        return self::leerTa($ta);
    }

    private static function taVigente(string $archivoTa): bool
    {
        if (! is_file($archivoTa)) {
            return false;
        }

        $ta = @simplexml_load_file($archivoTa);
        if (! $ta instanceof SimpleXMLElement) {
            return false;
        }

        $expiration = strtotime((string) $ta->header->expirationTime);

        return $expiration !== false && time() < $expiration;
    }

    /**
     * @return array{token: string, sign: string}
     */
    private static function leerTa(string $archivoTa): array
    {
        $ta = simplexml_load_file($archivoTa);
        if (! $ta instanceof SimpleXMLElement) {
            throw new RuntimeException('No se pudo leer TA.xml de AFIP.');
        }

        return [
            'token' => (string) $ta->credentials->token,
            'sign' => (string) $ta->credentials->sign,
        ];
    }

    private static function archivoTa(string $base, string $service): string
    {
        if ($service === 'wsfe') {
            return $base.'/TA.xml';
        }

        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $service) ?? $service;

        return $base.'/TA_'.$safe.'.xml';
    }

    private static function opensslBinario(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $candidatos = [
                'C:\\OpenSSL-Win64\\bin\\openssl.exe',
                'C:\\OpenSSL-Win32\\bin\\openssl.exe',
            ];
            foreach ($candidatos as $ruta) {
                if (is_file($ruta)) {
                    return $ruta;
                }
            }
        }

        return 'openssl';
    }
}
