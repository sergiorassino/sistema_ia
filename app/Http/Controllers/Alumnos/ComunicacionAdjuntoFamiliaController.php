<?php

namespace App\Http\Controllers\Alumnos;

use App\Comunicaciones\ComunicacionesRepository;
use App\Http\Controllers\Controller;
use App\Models\ComMensaje;
use App\Support\Comunicaciones\ComunicacionAdjuntoStorage;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Apertura del adjunto de un mensaje de comunicación — portal de alumnos (familia).
 *
 * La ruta usa un token opaco; el controlador revalida que la familia tenga
 * acceso al hilo correspondiente al mensaje.
 */
class ComunicacionAdjuntoFamiliaController extends Controller
{
    public function __invoke(Request $request, string $ref): StreamedResponse
    {
        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_COMUNICACION_ADJUNTO);
        abort_if($payload === null, 404);

        $idMensaje = (int) ($payload['m'] ?? 0);
        abort_if($idMensaje <= 0, 404);

        $mensaje = ComMensaje::with('hilo')->find($idMensaje);
        abort_if($mensaje === null, 404);
        abort_if(! $mensaje->tieneAdjunto(), 404);

        $hilo = $mensaje->hilo;
        abort_if($hilo === null, 404);

        // Revalidar alcance: la familia puede ver este hilo
        $ctx = studentCtx();
        abort_unless(
            ComunicacionesRepository::familiaPuedeVerHilo(
                (int) $hilo->id,
                (int) $ctx->idLegajo,
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec
            ),
            404
        );

        $ruta = ComunicacionAdjuntoStorage::ruta((string) $mensaje->adjunto_ruta);
        abort_if($ruta === null, 404);

        return ComunicacionAdjuntoStorage::download($ruta, (string) $mensaje->adjunto_nombre);
    }
}
