<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Despliegue en subcarpeta sin /public en la URL (ver public/index.php y docs/09).
 *
 * - Livewire `update`: URLs fijadas en LivewireDeploymentScripts (sesión + CSRF).
 * - Livewire `upload-file`: URL firmada; si PHP no ve la misma URL pública que al firmar → 401.
 *   Causas típicas: HTTPS no detectado detrás del proxy, o path /ia/colegio ausente al validar.
 */
class ForceHttpsBehindProxy
{
    public function handle(Request $request, Closure $next): Response
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $appPath = rtrim((string) (parse_url($appUrl, PHP_URL_PATH) ?: ''), '/');

        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');

            $forwarded = strtolower((string) $request->header('X-Forwarded-Proto', ''));
            if ($forwarded === 'https' || $forwarded === 'on') {
                $request->server->set('HTTPS', 'on');
            } elseif (! $request->secure() && in_array($request->getHost(), $this->hostsFromAppUrl($appUrl), true)) {
                // Mismo host que APP_URL pero PHP ve HTTP: típico en hosting compartido / Flexible SSL.
                $request->server->set('HTTPS', 'on');
            }
        }

        if ($appPath !== '' && ! $request->headers->has('X-Forwarded-Prefix')) {
            // index.php recorta /ia/colegio del REQUEST_URI para el router; la firma de upload-file
            // debe validarse con la URL pública completa (misma que APP_URL + ruta Livewire).
            $request->headers->set('X-Forwarded-Prefix', $appPath);
        }

        return $next($request);
    }

    /**
     * @return list<string>
     */
    private function hostsFromAppUrl(string $appUrl): array
    {
        $host = parse_url($appUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? [$host] : [];
    }
}
