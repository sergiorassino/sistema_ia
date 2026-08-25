<?php

namespace App\Http\Middleware;

use App\Models\Legajo;
use App\Support\StudentContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentContext
{
    public function handle(Request $request, Closure $next): Response
    {
        // Evitar singleton vacío resuelto antes de tener la sesión cargada.
        StudentContext::olvidarInstanciaResuelta();

        if (studentCtx()->isValid()) {
            return $next($request);
        }

        $alumno = Auth::guard('alumno')->user();
        if ($alumno instanceof Legajo && StudentContext::establecerDesdeLegajo($alumno) && studentCtx()->isValid()) {
            return $next($request);
        }

        // No mandar a loginEstudiante si aún hay auth: esa pantalla limpia la sesión.
        if ($alumno instanceof Legajo) {
            Auth::guard('alumno')->logout();
            StudentContext::clear();

            return redirect()
                ->to(se_route_url('alumnos.login'))
                ->with('error', 'No se pudo determinar el ciclo lectivo para autogestión. Contacte a secretaría.');
        }

        return redirect()
            ->to(se_route_url('alumnos.login'))
            ->with('error', 'Por favor inicie sesión nuevamente.');
    }
}
