<?php

namespace App\Http\Controllers\Alumnos;

use App\Comunicaciones\ComunicacionesFamiliaSession;
use App\Comunicaciones\ComunicacionesRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Entrada externa (email, push): valida acceso, guarda hilo en sesión y redirige
 * a la URL sin identificador numérico.
 */
class AbrirHiloComunicacionFamiliaController extends Controller
{
    public function __invoke(int $id): RedirectResponse
    {
        $ctx = studentCtx();
        abort_unless(
            ComunicacionesRepository::familiaPuedeVerHilo(
                $id,
                (int) $ctx->idLegajo,
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec
            ),
            404
        );

        ComunicacionesFamiliaSession::abrir($id);

        return redirect()->route('alumnos.comunicaciones.hilo');
    }
}
