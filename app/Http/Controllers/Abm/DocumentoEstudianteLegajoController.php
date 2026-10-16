<?php

namespace App\Http\Controllers\Abm;

use App\Http\Controllers\Controller;
use App\Models\DocEstudianteTipo;
use App\Models\Legajo;
use App\Support\Alumnos\DocumentosEstudianteAutogestion;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Muestra un archivo de documentación del estudiante desde el ABM de legajos.
 */
class DocumentoEstudianteLegajoController extends Controller
{
    public function __invoke(Request $request, string $ref): BinaryFileResponse
    {
        abort_unless(puedeConsultarLegajosEstudiantes(), 403);

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_LEGAJO_DOC_ESTUDIANTE);
        if ($decoded === null) {
            abort(404);
        }

        $key = 'legajos-doc-estudiante-archivo:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $tipo = DocEstudianteTipo::query()
            ->activos()
            ->whereKey($decoded['id'])
            ->first();
        if ($tipo === null) {
            abort(404);
        }

        $legajo = Legajo::query()->whereKey((int) $decoded['legajo'])->first();
        if ($legajo === null) {
            abort(404);
        }

        $dni = trim((string) ($legajo->dni ?? ''));
        $indice = max(1, (int) ($decoded['n'] ?? 1));
        $path = DocumentosEstudianteAutogestion::pathParte($dni, (string) $tipo->clave, $indice);
        if ($path === null) {
            abort(404);
        }

        $disk = Storage::disk(DocumentosEstudianteAutogestion::DISK);
        $absoluta = $disk->path($path);
        if (! is_file($absoluta)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($absoluta, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => null,
        };
        if ($mime === null) {
            abort(404);
        }

        $nombre = 'documento.'.($extension === 'jpeg' ? 'jpg' : $extension);

        return response()->file($absoluta, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            'Cache-Control' => 'private, max-age=120',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }
}
