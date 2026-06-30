<?php

namespace App\Http\Middleware;

use App\Support\Auth\CerrarSesionAplicacion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Al abrir login, invalida cualquier sesión previa antes de mostrar el formulario.
 *
 * Sustituye al middleware `guest`, que redirigía a usuarios autenticados al dashboard
 * sin permitir un nuevo ingreso con credenciales.
 */
class LimpiarSesionEnPaginaLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Solo limpiar cuando quedó sesión previa (equipos compartidos). En visitas anónimas
        // no rotar CSRF: evita 419 si hay doble GET o Livewire autocompletar justo al cargar.
        if (CerrarSesionAplicacion::haySesionAutenticadaOLegacy($request)) {
            $teniaAuth = CerrarSesionAplicacion::teniaAutenticacionActiva($request);

            // No invalidar el id de sesión: el HTML ya trae el CSRF nuevo y una cookie distinta
            // provoca 419 si el usuario envía el formulario enseguida (p. ej. con autocompletar).
            CerrarSesionAplicacion::ejecutar($request, invalidarSesion: false);

            if ($teniaAuth) {
                session()->flash(
                    'error',
                    'No se pudo mantener la sesión activa. Ingrese nuevamente con DNI, contraseña, nivel y año lectivo. '
                    .'Si el problema continúa tras un intento correcto, revise que APP_URL en el servidor coincida exactamente con la URL del navegador (incluida la subcarpeta /ia/…).',
                );
            }
        }

        return $next($request);
    }
}
