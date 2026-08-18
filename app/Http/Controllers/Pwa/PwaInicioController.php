<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Support\ProfesorMenuPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Compatibilidad: la PWA ya no usa esta pantalla. Redirige al portal con sesión o al login de personal.
 */
class PwaInicioController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        if (Auth::guard('alumno')->check()) {
            return redirect()->route('alumnos.home');
        }

        if (Auth::guard('web')->check()) {
            return redirect()->route(ProfesorMenuPortal::rutaInicio());
        }

        return redirect()->route('login');
    }
}
