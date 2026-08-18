<?php

namespace App\Support\Pwa;

/**
 * Dos PWA por tenant (personal / familias), mismas URLs relativas al host actual.
 */
final class PwaIdentity
{
    public const PERSONAL = 'personal';

    public const FAMILIAS = 'familias';

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

    public static function esPortal(string $portal): bool
    {
        return $portal === self::PERSONAL || $portal === self::FAMILIAS;
    }

    public static function normalizarPortal(?string $portal): string
    {
        $portal = strtolower(trim((string) $portal));

        return self::esPortal($portal) ? $portal : self::PERSONAL;
    }

    /**
     * Portal según la pantalla actual (login/layout de alumnos → familias).
     */
    public static function portalDesdeContexto(?string $guestPortal = null): string
    {
        if ($guestPortal === 'alumno') {
            return self::FAMILIAS;
        }

        $route = (string) (request()->route()?->getName() ?? '');
        if ($route === 'alumnos.login' || str_starts_with($route, 'alumnos.')) {
            return self::FAMILIAS;
        }

        return self::PERSONAL;
    }

    public static function nombreApp(string $portal): string
    {
        $portal = self::normalizarPortal($portal);
        $colegio = self::nombre();
        $sufijo = $portal === self::FAMILIAS ? 'Familias' : 'Personal';

        return $colegio === '' ? $sufijo : $colegio.' — '.$sufijo;
    }

    public static function nombreCortoApp(string $portal): string
    {
        return self::normalizarPortal($portal) === self::FAMILIAS ? 'Familias' : 'Personal';
    }

    /** Login de cada portal (HTTP 200). Igual que SILAVET: no usar url('/') — en Apache es 403/404. */
    public static function startUrlAbsoluto(string $portal): string
    {
        return self::normalizarPortal($portal) === self::FAMILIAS
            ? url('/loginEstudiante')
            : url('/loginUsuario');
    }

    /** @deprecated Usar startUrlAbsoluto() */
    public static function startUrlRelativo(string $portal): string
    {
        return self::startUrlAbsoluto($portal);
    }

    public static function idRelativo(string $portal): string
    {
        return self::startUrlAbsoluto($portal);
    }

    public static function archivoManifiesto(string $portal): string
    {
        return 'manifest-'.self::normalizarPortal($portal).'.webmanifest';
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

    /**
     * Ruta absoluta en el host actual (`/ia/colegio/sw.js` o `/sw.js`).
     * Evita el host de APP_URL (www vs sin www, http vs https) que rompe la instalación.
     */
    public static function rootPath(string $relative = ''): string
    {
        $base = rtrim(self::idPath(), '/');
        $suffix = ltrim($relative, '/');
        if ($suffix === '') {
            return $base === '' ? '/' : $base.'/';
        }

        return ($base === '' ? '' : $base).'/'.$suffix;
    }
}
