<?php

namespace App\Http\Controllers\Abm;

use App\Http\Controllers\Controller;
use App\Models\Legajo;
use App\Support\Alumnos\FotoCarnetLegajo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sirve la foto carnet del legajo (ABM secretaría, auth + permiso de consulta).
 */
class FotoCarnetLegajoController extends Controller
{
    public function __invoke(Request $request, int $id): BinaryFileResponse
    {
        abort_unless(puedeConsultarLegajosEstudiantes(), 403);
        abort_unless($id > 0, 404);

        if (! Schema::hasColumn('legajos', FotoCarnetLegajo::COLUMNA)) {
            abort(404);
        }

        $legajo = Legajo::query()->find($id);
        abort_unless($legajo !== null, 404);

        $pathRel = trim((string) ($legajo->fotoCarnet ?? ''));
        $abs = FotoCarnetLegajo::rutaAbsoluta($pathRel);
        abort_unless($abs !== null && is_file($abs), 404);

        $mime = match (strtolower(pathinfo($abs, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            default => 'image/jpeg',
        };

        return response()->file($abs, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
