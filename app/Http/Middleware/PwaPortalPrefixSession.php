<?php

namespace App\Http\Middleware;

use App\Support\Pwa\PwaIdentity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Conserva el portal PWA en sesión para que Livewire (URL sin prefijo) siga generando enlaces in-scope.
 */
class PwaPortalPrefixSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $portal = $request->attributes->get('se_pwa_portal');

        if (is_string($portal) && PwaIdentity::esPortal($portal)) {
            if ($request->hasSession()) {
                $request->session()->put(PwaIdentity::SESSION_KEY, $portal);
            }
            PwaIdentity::aplicarPrefijoUrls($portal);

            return $next($request);
        }

        if ($request->hasSession() && $this->conservaPortal($request)) {
            $guardado = $request->session()->get(PwaIdentity::SESSION_KEY);
            if (is_string($guardado) && PwaIdentity::esPortal($guardado)) {
                $request->attributes->set('se_pwa_portal', $guardado);
                PwaIdentity::aplicarPrefijoUrls($guardado);

                return $next($request);
            }
        }

        if ($request->hasSession() && $this->esLoginSinPrefijo($request)) {
            $request->session()->forget(PwaIdentity::SESSION_KEY);
        }

        PwaIdentity::quitarPrefijoUrls();

        return $next($request);
    }

    private function conservaPortal(Request $request): bool
    {
        if ($request->headers->has('X-Livewire')) {
            return true;
        }

        $path = '/'.ltrim($request->path(), '/');

        return str_starts_with($path, '/livewire-') || str_contains($path, '/livewire/');
    }

    private function esLoginSinPrefijo(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        return $request->is('loginUsuario') || $request->is('loginEstudiante');
    }
}
