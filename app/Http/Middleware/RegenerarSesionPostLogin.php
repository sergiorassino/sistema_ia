<?php

namespace App\Http\Middleware;

use App\Support\SchoolContext;
use App\Support\StudentContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tras login Livewire no rotar el id de sesión en el POST AJAX: en navegadores nuevos
 * el Set-Cookie puede no aplicarse antes del redirect y el ingreso falla una vez.
 * La rotación (anti session-fixation) ocurre en la primera petición HTML posterior.
 *
 * regenerate(false): emite id nuevo sin borrar el anterior. Si el navegador aún envía
 * la cookie vieja en el siguiente clic del menú, la sesión sigue válida.
 *
 * En cada request (tras StartSession) se descarta el singleton de contexto para leer
 * siempre los valores actuales de la sesión.
 */
class RegenerarSesionPostLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            if ($request->session()->pull('auth.pending_session_regenerate', false)) {
                $request->session()->regenerate(false);
            }

            StudentContext::olvidarInstanciaResuelta();
            if (app()->resolved(SchoolContext::class)) {
                app()->forgetInstance(SchoolContext::class);
            }
        }

        return $next($request);
    }
}
