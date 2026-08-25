<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Support\ProfesorMenuPortal;
use App\Support\Pwa\PwaIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * start_url de cada PWA (bajo /pwa-personal o /pwa-familias). No usar `/` ni el login:
 * `/` da 404 en subcarpeta Apache y el login limpia la sesión.
 */
class PwaInicioController extends Controller
{
    public function __invoke(?string $portal = null): RedirectResponse
    {
        $desdePrefijo = request()->attributes->get('se_pwa_portal');
        if (is_string($desdePrefijo) && PwaIdentity::esPortal($desdePrefijo)) {
            $portal = $desdePrefijo;
        } else {
            $portal = PwaIdentity::normalizarPortal($portal);
        }

        if ($portal === PwaIdentity::FAMILIAS) {
            if (Auth::guard('alumno')->check()) {
                return redirect()->to(se_route_url('alumnos.home'));
            }

            return redirect()->to(se_route_url('alumnos.login'));
        }

        if (Auth::guard('web')->check()) {
            return redirect()->route(ProfesorMenuPortal::rutaInicio());
        }

        return redirect()->route('login');
    }
}
