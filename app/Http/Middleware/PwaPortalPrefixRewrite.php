<?php

namespace App\Http\Middleware;

use App\Support\Pwa\PwaIdentity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quita /pwa-personal o /pwa-familias del path para reutilizar las rutas actuales.
 * Chrome exige alcances de manifiesto no solapados para instalar las dos apps.
 */
class PwaPortalPrefixRewrite
{
    public function handle(Request $request, Closure $next): Response
    {
        $parsed = PwaIdentity::parsearPrefijoDePath($request->getPathInfo());

        if ($parsed === null) {
            PwaIdentity::quitarPrefijoUrls();

            return $next($request);
        }

        $portal = $parsed['portal'];
        $resto = $parsed['resto'];
        $query = $request->getQueryString();
        $newUri = $resto.($query !== null && $query !== '' ? '?'.$query : '');

        $server = $request->server->all();
        $server['REQUEST_URI'] = $newUri;
        $server['SE_PWA_PORTAL'] = $portal;

        $nuevo = $request->duplicate(server: $server);
        $nuevo->attributes->set('se_pwa_portal', $portal);

        app()->instance('request', $nuevo);
        PwaIdentity::aplicarPrefijoUrls($portal);

        return $next($nuevo);
    }
}
