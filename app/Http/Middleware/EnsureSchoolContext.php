<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! schoolCtx()->isValid()) {
            $mensaje = 'Por favor inicie sesión y seleccione nivel y año lectivo.';

            return redirect()->route('login')->with('error', $mensaje);
        }

        return $next($request);
    }
}
