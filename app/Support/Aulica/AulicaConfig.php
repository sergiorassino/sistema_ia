<?php

namespace App\Support\Aulica;

/**
 * Configuración del cliente External API de Áulica (por tenant + .env).
 */
final class AulicaConfig
{
    public const AMBIENTE_TEST = 'test';

    public const AMBIENTE_PRODUCCION = 'produccion';

    public const API_TEST = 'https://pau-develop-externalapi.aulicatest.com.ar';

    public const API_PRODUCCION = 'https://externalapi.aulica.com.ar';

    public const AUTH_TEST = 'https://pau-develop-authserver.aulicatest.com.ar';

    public const AUTH_PRODUCCION = 'https://authserver.aulica.com.ar';

    public static function habilitada(): bool
    {
        if (! (bool) config('tenant.aulica_deuda.habilitado', false)) {
            return false;
        }

        return self::username() !== ''
            && self::password() !== ''
            && self::codigo() !== '';
    }

    public static function bloquearAutogestion(): bool
    {
        return self::habilitada()
            && (bool) config('tenant.aulica_deuda.bloquear_autogestion', false);
    }

    public static function ambiente(): string
    {
        $desdeEnv = strtolower(trim((string) config('services.aulica.ambiente', '')));
        if (in_array($desdeEnv, [self::AMBIENTE_TEST, self::AMBIENTE_PRODUCCION], true)) {
            return $desdeEnv;
        }

        $desdeTenant = strtolower(trim((string) config('tenant.aulica_deuda.ambiente', self::AMBIENTE_TEST)));

        return $desdeTenant === self::AMBIENTE_PRODUCCION
            ? self::AMBIENTE_PRODUCCION
            : self::AMBIENTE_TEST;
    }

    public static function urlApi(): string
    {
        return self::ambiente() === self::AMBIENTE_PRODUCCION
            ? self::API_PRODUCCION
            : self::API_TEST;
    }

    public static function urlAuth(): string
    {
        return self::ambiente() === self::AMBIENTE_PRODUCCION
            ? self::AUTH_PRODUCCION
            : self::AUTH_TEST;
    }

    public static function username(): string
    {
        return trim((string) config('services.aulica.username', ''));
    }

    public static function password(): string
    {
        return (string) config('services.aulica.password', '');
    }

    public static function codigo(): string
    {
        return trim((string) config('services.aulica.codigo', ''));
    }

    public static function timeout(): int
    {
        $timeout = (int) config('services.aulica.timeout', 15);

        return $timeout > 0 ? min($timeout, 60) : 15;
    }

    public static function cacheSaldosSegundos(): int
    {
        $segundos = (int) config('tenant.aulica_deuda.cache_saldos_segundos', 300);

        return $segundos > 0 ? min($segundos, 3600) : 300;
    }

    public static function slugCache(): string
    {
        $slug = strtolower(trim((string) config('tenant.slug', 'default')));

        return $slug !== '' ? $slug : 'default';
    }

    /**
     * Ruta al bundle de CA para cURL en Windows. Vacío = certificados del sistema.
     */
    public static function caBundle(): string
    {
        $candidatos = [
            trim((string) config('services.aulica.ca_bundle', '')),
            (string) ini_get('curl.cainfo'),
            (string) ini_get('openssl.cafile'),
            storage_path('certs/cacert.pem'),
        ];

        foreach ($candidatos as $ruta) {
            $ruta = trim($ruta);
            if ($ruta !== '' && is_file($ruta)) {
                return $ruta;
            }
        }

        return '';
    }

    public static function sslVerify(): bool
    {
        return (bool) config('services.aulica.ssl_verify', true);
    }
}
