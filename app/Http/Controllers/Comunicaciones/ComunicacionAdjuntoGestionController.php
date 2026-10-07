<?php

namespace App\Http\Controllers\Comunicaciones;

use App\Comunicaciones\ComunicacionesRepository;
use App\Http\Controllers\Controller;
use App\Models\ComMensaje;
use App\Support\Comunicaciones\ComunicacionAdjuntoStorage;
use App\Support\ComunicacionesRutasGestion;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Apertura del adjunto de un mensaje de comunicación — portal de gestión (Secretaría / Docentes).
 *
 * La ruta usa un token opaco; el controlador revalida que el usuario logueado
 * tenga acceso al hilo (mismo nivel y ciclo que la sesión).
 */
class ComunicacionAdjuntoGestionController extends Controller
{
    public function __invoke(Request $request, string $ref): StreamedResponse
    {
        abort_unless(ComunicacionesRutasGestion::accesoBandejaGestion(), 403);

        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_COMUNICACION_ADJUNTO);
        abort_if($payload === null, 404);

        $idMensaje = (int) ($payload['m'] ?? 0);
        abort_if($idMensaje <= 0, 404);

        $mensaje = ComMensaje::with('hilo')->find($idMensaje);
        abort_if($mensaje === null, 404);
        abort_if(! $mensaje->tieneAdjunto(), 404);

        // Revalidar alcance: el hilo debe pertenecer al nivel y ciclo de la sesión del usuario
        $ctx = schoolCtx();
        $hilo = $mensaje->hilo;
        abort_if($hilo === null, 404);
        abort_unless(
            (int) $hilo->id_nivel  === (int) $ctx->idNivel &&
            (int) $hilo->id_terlec === (int) $ctx->idTerlec,
            404
        );

        // Adicionalmente, verificar que el usuario pueda ver el hilo
        abort_unless(
            ComunicacionesRepository::profesorPuedeVerHilo(
                (int) $hilo->id,
                (int) $ctx->idProfesor,
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec
            ) || tienePermiso(8),
            404
        );

        $ruta = ComunicacionAdjuntoStorage::ruta((string) $mensaje->adjunto_ruta);
        abort_if($ruta === null, 404);

        return ComunicacionAdjuntoStorage::download($ruta, (string) $mensaje->adjunto_nombre);
    }
}
