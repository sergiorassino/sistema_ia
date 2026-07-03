<?php

namespace App\Http\Controllers;

use App\Comunicaciones\ComunicacionesRepository;
use App\Support\Alumnos\ArancelesEscolares;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $ctx = schoolCtx();
        $usuario = Auth::user();
        $nombre = trim(($usuario->nombre ?? '').' '.($usuario->apellido ?? ''));
        $dni = ArancelesEscolares::formatearDni($usuario->dni ?? '');
        $modoPortalDocente = ProfesorMenuPortal::usaMenuDocentes($usuario);

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
            'dniUsuario'        => $dni !== '' ? $dni : '—',
            'bandeja'           => $bandeja,
        ]);
    }
}
