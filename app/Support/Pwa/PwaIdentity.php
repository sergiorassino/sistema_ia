<?php

namespace App\Support\Pwa;

use Illuminate\Support\Facades\URL;

/**
 * Dos PWA por tenant (personal / familias), mismas URLs relativas al host actual.
 */
final class PwaIdentity
{
    public const PERSONAL = 'personal';

    public const FAMILIAS = 'familias';

    /** Segmento de URL que aísla el alcance de cada PWA (Chrome no instala dos apps con el mismo scope). */
    public const SCOPE_SEGMENT_PERSONAL = 'pwa-personal';

    public const SCOPE_SEGMENT_FAMILIAS = 'pwa-familias';

    public const SESSION_KEY = 'se_pwa_portal';

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
        $desdePrefijo = request()->attributes->get('se_pwa_portal');
        if (is_string($desdePrefijo) && self::esPortal($desdePrefijo)) {
            return $desdePrefijo;
        }

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

    public static function scopeSegment(string $portal): string
    {
        return self::normalizarPortal($portal) === self::FAMILIAS
            ? self::SCOPE_SEGMENT_FAMILIAS
            : self::SCOPE_SEGMENT_PERSONAL;
    }

    /**
     * Alcance del manifiesto: directorio exclusivo por portal, con barra final.
     * Sin eso Chrome trata Personal y Familias como la misma app.
     */
    public static function scopeAbsoluto(string $portal): string
    {
        return rtrim(self::urlDentroDelPortal($portal, ''), '/').'/';
    }

    /**
     * Arranque de la PWA: /entrar (si hay sesión va al home; el login limpia sesión).
     */
    public static function startUrlAbsoluto(string $portal): string
    {
        return self::urlDentroDelPortal($portal, 'entrar');
    }

    public static function urlLoginPrefijado(string $portal): string
    {
        $portal = self::normalizarPortal($portal);

        return self::urlDentroDelPortal(
            $portal,
            $portal === self::FAMILIAS ? 'loginEstudiante' : 'loginUsuario'
        );
    }

    public static function urlDentroDelPortal(string $portal, string $pathInterno): string
    {
        $segment = self::scopeSegment($portal);
        $pathInterno = trim($pathInterno, '/');
        $path = '/'.$segment.($pathInterno !== '' ? '/'.$pathInterno : '');

        return url($path);
    }

    /**
     * @return array{portal: string, resto: string}|null
     */
    public static function parsearPrefijoDePath(string $pathInfo): ?array
    {
        $path = '/'.trim($pathInfo, '/');
        if ($path === '/') {
            return null;
        }

        foreach ([self::PERSONAL => self::SCOPE_SEGMENT_PERSONAL, self::FAMILIAS => self::SCOPE_SEGMENT_FAMILIAS] as $portal => $segment) {
            $prefix = '/'.$segment;
            if ($path === $prefix) {
                return ['portal' => $portal, 'resto' => '/entrar'];
            }
            if (str_starts_with($path, $prefix.'/')) {
                $resto = substr($path, strlen($prefix));

                return ['portal' => $portal, 'resto' => ($resto === '' || $resto === false) ? '/entrar' : $resto];
            }
        }

        return null;
    }

    public static function aplicarPrefijoUrls(string $portal): void
    {
        $segment = self::scopeSegment($portal);

        URL::formatPathUsing(function (string $path, $route = null) use ($segment): string {
            $path = '/'.ltrim($path, '/');
            if (self::pathSinPrefijoPwa($path, $segment)) {
                return $path;
            }

            return '/'.$segment.$path;
        });
    }

    public static function quitarPrefijoUrls(): void
    {
        URL::formatPathUsing(static fn (string $path, $route = null): string => $path);
    }

    public static function pathSinPrefijoPwa(string $path, string $segment): bool
    {
        $path = '/'.ltrim($path, '/');
        $prefix = '/'.$segment;
        if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
            return true;
        }

        $sinBarra = ltrim($path, '/');

        $prefijos = ['build/', 'img/', 'storage/', 'fonts/', 'vendor/', 'livewire-', 'pwa-icon/', 'manifest'];
        foreach ($prefijos as $inicio) {
            if (str_starts_with($sinBarra, $inicio)) {
                return true;
            }
        }

        return in_array($sinBarra, ['favicon.ico', 'icono-escuela.png', 'sw.js', 'up'], true);
    }

    /** @deprecated Usar startUrlAbsoluto() */
    public static function startUrlRelativo(string $portal): string
    {
        return self::startUrlAbsoluto($portal);
    }

    public static function idRelativo(string $portal): string
    {
        return self::scopeAbsoluto($portal);
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

    /**
     * URL absoluta del icono PWA en el host de esta petición, con ?v=filemtime
     * para que Chrome no reutilice el PNG verde anterior (misma ruta, otro contenido).
     */
    public static function iconAbsoluto(string $filename): string
    {
        $filename = basename($filename);
        $path = self::rootPath('img/'.$filename);
        $url = request()->getSchemeAndHttpHost().$path;
        $file = public_path('img/'.$filename);
        $v = is_file($file) ? (string) filemtime($file) : '16';

        return $url.'?v='.$v;
    }
}
