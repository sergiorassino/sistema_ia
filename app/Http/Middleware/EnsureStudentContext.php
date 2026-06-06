<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! studentCtx()->isValid()) {
            return redirect()->route('alumnos.login')
                ->with('error', 'Por favor inicie sesión.');
        }

        return $next($request);
    }
}

