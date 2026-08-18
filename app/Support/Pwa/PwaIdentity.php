<?php

namespace App\Support\Pwa;

/**
 * Nombre e URLs de la PWA (un ícono por instalación / subcarpeta de tenant).
 */
final class PwaIdentity
{
    public static function nombre(): string
    {
        try {
            $desdeEnto = trim(entoInstitutionalNombre());
            if ($desdeEnto !== '') {
                return $desdeEnto;
            }
        } catch (\Throwable) {
            // Sin BD o tabla `ento` (p. ej. tests).
        }

        $tenant = trim((string) config('tenant.nombre', ''));
        if ($tenant !== '' && strcasecmp($tenant, 'Colegio') !== 0) {
            return $tenant;
        }

        return trim((string) config('app.name', 'Sistemas Escolares')) ?: 'Sistemas Escolares';
    }

    public static function nombreCorto(): string
    {
        $nombre = self::nombre();
        if (mb_strlen($nombre) <= 12) {
            return $nombre;
        }

        $tenant = trim((string) config('tenant.nombre', ''));
        if ($tenant !== '' && mb_strlen($tenant) <= 12) {
            return $tenant;
        }

        return 'SE';
    }

    /** Base pública con barra final (`https://dominio/ia/colegio/`). */
    public static function baseUrl(): string
    {
        return rtrim(url('/'), '/').'/';
    }

    /** Path de APP_URL (`/ia/colegio/` o `/`) — id estable de la app instalada. */
    public static function idPath(): string
    {
        $path = parse_url(self::baseUrl(), PHP_URL_PATH);

        return ($path !== null && $path !== '') ? rtrim($path, '/').'/' : '/';
    }
}
