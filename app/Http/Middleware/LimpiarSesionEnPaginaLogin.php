<?php

namespace App\Http\Middleware;

use App\Models\Legajo;
use App\Support\Auth\CerrarSesionAplicacion;
use App\Support\StudentContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Al abrir login, invalida cualquier sesión previa antes de mostrar el formulario.
 *
 * Sustituye al middleware `guest`, que redirigía a usuarios autenticados al dashboard
 * sin permitir un nuevo ingreso con credenciales.
 *
 * Excepción: si el guard `alumno` sigue autenticado (p. ej. un PDF o menú redirigió
 * por error a login), NO se limpia la sesión: se recompone el contexto y se vuelve
 * al portal. Si se limpiara, un falso positivo dejaba a la familia sin sesión.
 */
class LimpiarSesionEnPaginaLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('alumno')->check()) {
            $alumno = Auth::guard('alumno')->user();
            if ($alumno instanceof Legajo && StudentContext::establecerDesdeLegajo($alumno)) {
                return redirect()->to(se_route_url(tenantAutogestionRutaInicio()));
            }
        }

        // Solo limpiar cuando quedó sesión previa (equipos compartidos). En visitas anónimas
        // no rotar CSRF: evita 419 si hay doble GET o Livewire autocompletar justo al cargar.
        if (CerrarSesionAplicacion::haySesionAutenticadaOLegacy($request)) {
            // No invalidar el id de sesión: el HTML ya trae el CSRF nuevo y una cookie distinta
            // provoca 419 si el usuario envía el formulario enseguida (p. ej. con autocompletar).
            CerrarSesionAplicacion::ejecutar($request, invalidarSesion: false);
        }

        return $next($request);
    }
}
