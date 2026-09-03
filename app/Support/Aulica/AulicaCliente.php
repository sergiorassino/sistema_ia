<?php

namespace App\Support\Aulica;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Autenticación OAuth-like de Áulica y llamadas autenticadas a la External API.
 */
final class AulicaCliente
{
    /**
     * @param  array<string, mixed>  $cuerpo
     */
    public function postApi(string $path, array $cuerpo = []): Response
    {
        return $this->requestApi('post', $path, $cuerpo);
    }

    public function getApi(string $path): Response
    {
        return $this->requestApi('get', $path);
    }

    /**
     * @param  array<string, mixed>  $cuerpo
     */
    private function requestApi(string $metodo, string $path, array $cuerpo = []): Response
    {
        $url = rtrim(AulicaConfig::urlApi(), '/').'/'.ltrim($path, '/');
        $token = $this->accessToken();

        try {
            $pending = $this->http()->withHeaders(['x-access-token' => $token]);

            $response = $metodo === 'get'
                ? $pending->get($url)
                : $pending->post($url, $cuerpo);
        } catch (Throwable $e) {
            Log::warning('Áulica: error de red', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            throw new AulicaClienteException('No se pudo contactar Áulica.', 0, $e);
        }

        if ($response->status() === 401) {
            Cache::forget($this->claveCacheToken());
            $token = $this->accessToken(forzar: true);

            try {
                $pending = $this->http()->withHeaders(['x-access-token' => $token]);

                $response = $metodo === 'get'
                    ? $pending->get($url)
                    : $pending->post($url, $cuerpo);
            } catch (Throwable $e) {
                throw new AulicaClienteException('No se pudo contactar Áulica.', 0, $e);
            }
        }

        return $response;
    }

    public function accessToken(bool $forzar = false): string
    {
        if (! AulicaConfig::habilitada()) {
            throw new AulicaClienteException('Áulica no está configurada (credenciales o flag de tenant).');
        }

        $clave = $this->claveCacheToken();
        if (! $forzar) {
            $cacheado = Cache::get($clave);
            if (is_string($cacheado) && $cacheado !== '') {
                return $cacheado;
            }
        }

        $payload = $this->autenticar();
        $token = trim((string) ($payload['accessToken'] ?? ''));
        if ($token === '') {
            throw new AulicaClienteException('Áulica no devolvió accessToken.');
        }

        $ttl = $this->ttlDesdePayload($payload);
        Cache::put($clave, $token, $ttl);

        $refresh = trim((string) ($payload['refreshToken'] ?? ''));
        if ($refresh !== '') {
            Cache::put($this->claveCacheRefresh(), $refresh, $ttl + 3600);
        }

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function autenticar(): array
    {
        $url = rtrim(AulicaConfig::urlAuth(), '/').'/externalauth/authenticate';

        try {
            $response = $this->http()->post($url, [
                'username' => AulicaConfig::username(),
                'password' => AulicaConfig::password(),
                'codigo' => AulicaConfig::codigo(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Áulica: error de autenticación (red)', ['message' => $e->getMessage()]);

            throw new AulicaClienteException('No se pudo autenticar en Áulica.', 0, $e);
        }

        if (! $response->successful()) {
            Log::warning('Áulica: autenticación rechazada', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 400),
            ]);

            throw new AulicaClienteException('Áulica rechazó las credenciales (HTTP '.$response->status().').');
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new AulicaClienteException('Respuesta de autenticación Áulica inválida.');
        }

        return $json;
    }

    /**
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function http()
    {
        $pending = Http::timeout(AulicaConfig::timeout())
            ->acceptJson()
            ->asJson();

        $ca = AulicaConfig::caBundle();
        if ($ca !== '') {
            return $pending->withOptions(['verify' => $ca]);
        }

        if (! AulicaConfig::sslVerify()) {
            return $pending->withOptions(['verify' => false]);
        }

        return $pending;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ttlDesdePayload(array $payload): int
    {
        $exp = (int) ($payload['expirationDate'] ?? 0);
        if ($exp > 1_000_000_000) {
            $segundos = $exp - time() - 60;

            return max(60, min($segundos, 7200));
        }

        return 110 * 60;
    }

    private function claveCacheToken(): string
    {
        return 'aulica:'.AulicaConfig::slugCache().':'.AulicaConfig::ambiente().':access';
    }

    private function claveCacheRefresh(): string
    {
        return 'aulica:'.AulicaConfig::slugCache().':'.AulicaConfig::ambiente().':refresh';
    }
}
