<?php

namespace App\Http\Controllers\Comunicaciones;

use App\Comunicaciones\ComunicacionesGestionSession;
use App\Http\Controllers\Controller;
use App\Support\ComunicacionesRutasGestion;
use Illuminate\Http\RedirectResponse;

/**
 * Entrada externa (enlace directo, favoritos): valida acceso, guarda hilo en sesión y redirige
 * a la URL sin identificador numérico.
 */
class AbrirHiloComunicacionGestionController extends Controller
{
    public function __invoke(int $id): RedirectResponse
    {
        abort_unless(ComunicacionesGestionSession::puedeVerHilo($id), 404);

        ComunicacionesGestionSession::abrir($id);

        return redirect()->route(ComunicacionesRutasGestion::nombreRuta('hilo'));
    }
}
