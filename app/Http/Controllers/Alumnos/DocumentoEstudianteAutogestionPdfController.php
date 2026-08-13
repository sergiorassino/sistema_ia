<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Models\DocEstudianteTipo;
use App\Models\Legajo;
use App\Support\Alumnos\DocumentosEstudianteAutogestion;
use App\Support\MatriculaBloqueos;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Visualiza el PDF de documentación del estudiante subido desde autogestión.
 */
class DocumentoEstudianteAutogestionPdfController extends Controller
{
    public function __invoke(Request $request, string $ref): Response
    {
        abort_unless(tenantAutogestionActualizacionDatosHabilitada(), 404);

        $restriccion = MatriculaBloqueos::paraEstudianteActual();
        if ($restriccion['bloqueada']) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => $restriccion['mensaje'],
            ], 403);
        }

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_DOC_ESTUDIANTE_AUTOGESTION);
        if ($decoded === null) {
            abort(404);
        }

        $ctx = studentCtx();
        if (! $ctx->isValid() || $decoded['legajo'] !== (int) $ctx->idLegajo) {
            abort(404);
        }

        $key = 'alumnos-doc-estudiante-pdf:'.(auth('alumno')->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $tipo = DocEstudianteTipo::query()
            ->activos()
            ->whereKey($decoded['id'])
            ->first();

        if ($tipo === null) {
            abort(404, 'El tipo de documento no está disponible.');
        }

        $legajo = Legajo::query()->whereKey((int) $ctx->idLegajo)->first();
        if ($legajo === null) {
            abort(404);
        }

        $dni = trim((string) ($legajo->dni ?? ''));
        $path = DocumentosEstudianteAutogestion::pathAlmacenadoResuelto($dni, (string) $tipo->clave);
        if ($path === null) {
            abort(404, 'No hay un documento subido para visualizar.');
        }

        $disk = Storage::disk(DocumentosEstudianteAutogestion::DISK);
        $nombreDescarga = DocumentosEstudianteAutogestion::nombreArchivoPdf($dni, (string) $tipo->clave);

        return $disk->response($path, $nombreDescarga, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="documento.pdf"',
        ]);
    }
}
