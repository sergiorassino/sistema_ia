<?php

namespace App\Http\Controllers;

use App\Comunicaciones\ComunicacionesRepository;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $ctx = schoolCtx();
        $nombre = trim((Auth::user()->nombre ?? '').' '.(Auth::user()->apellido ?? ''));
        $modoPortalDocente = ProfesorMenuPortal::usaMenuDocentes(Auth::user());

        $bandeja = null;
        if (tienePermiso(3) || $modoPortalDocente) {
            $bandeja = ComunicacionesRepository::resumenBandejaProfesor(
                (int) $ctx->idProfesor,
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec,
            );
        }

        return view('dashboard', [
            'layout'            => $modoPortalDocente ? 'layouts.docente' : ProfesorMenuPortal::layoutStaff(),
            'modoPortalDocente' => $modoPortalDocente,
            'nombreUsuario'     => $nombre !== '' ? $nombre : 'Usuario',
            'bandeja'           => $bandeja,
        ]);
    }
}
