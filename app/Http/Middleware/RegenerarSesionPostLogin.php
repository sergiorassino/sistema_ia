<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tras login Livewire no rotar el id de sesión en el POST AJAX: en navegadores nuevos
 * el Set-Cookie puede no aplicarse antes del redirect y el ingreso falla una vez.
 * La rotación (anti session-fixation) ocurre en la primera petición HTML posterior.
 */
class RegenerarSesionPostLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && $request->session()->pull('auth.pending_session_regenerate', false)) {
            $request->session()->regenerate();
        }

        return $next($request);
    }
}
