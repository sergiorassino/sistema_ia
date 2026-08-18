<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Support\ProfesorMenuPortal;
use App\Support\Pwa\PwaIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * start_url de cada PWA. No usar `/` ni el login: `/` da 404 en subcarpeta Apache
 * y el login limpia la sesión.
 */
class PwaInicioController extends Controller
{
    public function __invoke(?string $portal = null): RedirectResponse
    {
        $portal = PwaIdentity::normalizarPortal($portal);

        if ($portal === PwaIdentity::FAMILIAS) {
            if (Auth::guard('alumno')->check()) {
                return redirect()->route('alumnos.home');
            }

            return redirect()->route('alumnos.login');
        }

        if (Auth::guard('web')->check()) {
            return redirect()->route(ProfesorMenuPortal::rutaInicio());
        }

        return redirect()->route('login');
    }
}
