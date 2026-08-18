<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Support\ProfesorMenuPortal;
use App\Support\Pwa\PwaIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * start_url de la PWA: redirige si hay sesión; si no, elige portal (personal / familias).
 */
class PwaInicioController extends Controller
{
    public function __invoke(): RedirectResponse|View
    {
        if (Auth::guard('alumno')->check()) {
            return redirect()->route('alumnos.home');
        }

        if (Auth::guard('web')->check()) {
            return redirect()->route(ProfesorMenuPortal::rutaInicio());
        }

        $logo = null;
        try {
            $logo = entoInstitutionalLogoUrlFallback();
        } catch (\Throwable) {
            $logo = null;
        }

        return view('pwa.inicio', [
            'nombre' => PwaIdentity::nombre(),
            'logoUrl' => $logo ?: asset('img/3.png'),
        ]);
    }
}
