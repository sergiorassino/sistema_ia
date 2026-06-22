<?php

namespace App\Support\Alumnos;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Obtiene la cadena QR desde SIRO (legacy obtenerQR).
 *
 * Si no hay URL configurada o la consulta falla, devuelve cadena vacía (sin QR en el PDF).
 */
final class ComprobantePagoSiroQr
{
    public static function obtenerCadena(string $nroClienteEmpresa, string $nroComprobante): string
    {
        $url = tenantCuotasSiroQrUrl();
        if ($url === '') {
            return '';
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->post($url, [
                    'nro_cliente_empresa' => $nroClienteEmpresa,
                    'nro_comprobante' => $nroComprobante,
                    'vto_1' => 'nada',
                    'importe_1' => 'nada',
                    'vto_2' => 'nada',
                    'importe_2' => 'nada',
                    'vto_3' => 'nada',
                    'importe_3' => 'nada',
                ]);

            if (! $response->successful()) {
                Log::warning('SIRO QR: respuesta HTTP no exitosa', ['status' => $response->status()]);

                return '';
            }

            $body = $response->json();
            if (is_string($body)) {
                return trim($body);
            }

            if (is_array($body)) {
                foreach (['cadena', 'qr', 'data', 'payload'] as $key) {
                    if (! empty($body[$key]) && is_string($body[$key])) {
                        return trim($body[$key]);
                    }
                }
            }

            return trim((string) $response->body());
        } catch (\Throwable $e) {
            Log::warning('SIRO QR: error al consultar', ['message' => $e->getMessage()]);

            return '';
        }
    }
}
