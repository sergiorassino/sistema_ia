<?php

namespace App\Http\Controllers;

use App\Support\Alumnos\DescargaDocumentacionCurso;
use App\Support\PermisosIaCatalog;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ZIP de la documentación del estudiante de un curso.
 */
class DescargaDocumentacionCursoZipController extends Controller
{
    public function __invoke(Request $request, string $ref): BinaryFileResponse
    {
        abort_unless(tienePermiso(PermisosIaCatalog::DESCARGA_DOCUMENTACION_CURSO), 403);

        $ctx = schoolCtx();
        if ((int) $ctx->idNivel < 1 || (int) $ctx->idTerlec < 1) {
            abort(403, 'Seleccione nivel y ciclo lectivo en el contexto activo.');
        }

        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_DESCARGA_DOCUMENTACION_CURSO);
        $idCurso = (int) ($payload['c'] ?? 0);
        if ($payload === null || $idCurso < 1) {
            abort(404);
        }

        $key = 'documentacion-curso-zip:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 6)) {
            abort(429, 'Demasiadas descargas. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        if (! class_exists(\ZipArchive::class)) {
            abort(500, 'La extensión ZIP no está disponible en el servidor.');
        }

        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $zip = DescargaDocumentacionCurso::escribirZip($idCurso);
        if ($zip === null) {
            abort(404, 'Este curso no tiene documentación cargada.');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response()->download($zip['ruta'], $zip['nombre'], [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }
}
