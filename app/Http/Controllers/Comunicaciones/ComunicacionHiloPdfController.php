<?php

namespace App\Http\Controllers\Comunicaciones;

use App\Comunicaciones\ComunicacionesGestionSession;
use App\Http\Controllers\Controller;
use App\Support\Comunicaciones\ComunicacionHiloPdfDatos;
use App\Support\Comunicaciones\ComunicacionHiloTcpdf;
use App\Support\ComunicacionesRutasGestion;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ComunicacionHiloPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(ComunicacionesRutasGestion::accesoBandejaGestion(), 403, 'Sin permiso para ver comunicaciones.');

        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_COMUNICACION_HILO);
        if ($payload === null) {
            abort(404);
        }

        $idHilo = (int) ($payload['h'] ?? 0);
        $idProfesorToken = (int) ($payload['p'] ?? 0);
        if ($idHilo <= 0 || $idProfesorToken <= 0) {
            abort(404);
        }

        $ctx = schoolCtx();
        if ($idProfesorToken !== (int) $ctx->idProfesor) {
            abort(404);
        }

        abort_unless(ComunicacionesGestionSession::puedeVerHilo($idHilo), 404);

        $key = 'com-hilo-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = ComunicacionHiloPdfDatos::paraHilo(
            $idHilo,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec
        );
        if ($datos === null) {
            abort(404);
        }

        $slug = Str::slug('conversacion-'.(string) ($datos['asunto'] ?? 'comunicado'), '_');
        if ($slug === '') {
            $slug = 'conversacion_comunicado';
        }

        $pdf = ComunicacionHiloTcpdf::generar($datos, schoolPdfHeaderData());

        return ComunicacionHiloTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
