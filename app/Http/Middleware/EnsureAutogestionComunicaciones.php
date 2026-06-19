<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAutogestionComunicaciones
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(tenantAutogestionComunicacionesHabilitada(), 404);

        return $next($request);
    }
}
