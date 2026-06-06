<?php

namespace App\Support\Auth;

use App\Support\ProfesorMenuPortal;
use App\Support\SchoolContext;
use App\Support\StudentContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cierra por completo la sesión web (secretaría, docentes y alumnos).
 *
 * Usado en logout explícito y al abrir una pantalla de login, para evitar
 * que una cookie de sesión previa permita entrar sin credenciales en equipos compartidos.
 */
final class CerrarSesionAplicacion
{
    /**
     * @param  bool  $invalidarSesion  Si es false, conserva el id de sesión (cookie) y solo vacía datos + CSRF.
     *                                  Usar false al abrir login: evita 419 si el navegador envía el POST
     *                                  antes de aplicar la cookie de sesión nueva tras invalidate().
     */
    public static function ejecutar(?Request $request = null, bool $invalidarSesion = true): void
    {
        $request ??= request();

        SchoolContext::clear();
        StudentContext::clear();
        ProfesorMenuPortal::limpiarAutogestionDocente();

        Auth::guard('alumno')->logout();
        Auth::logout();

        if ($invalidarSesion) {
            $request->session()->invalidate();
        } else {
            $request->session()->flush();
        }

        $request->session()->regenerateToken();
    }
}
