<?php

namespace App\Http\Controllers\MatriculaWeb;

use App\Http\Controllers\Controller;
use App\Support\MatriculaWeb\MatriculaWebDocumentos;
use App\Support\PermisosMatriculaWeb;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sirve el PDF de aceptación del nivel (secretaría con permiso; luego también portal alumno).
 */
class DocumentoAceptacionArchivoController extends Controller
{
    public function __invoke(Request $request, string $tipo): Response
    {
        abort_unless(MatriculaWebDocumentos::claveValida($tipo), 404);

        if (auth('alumno')->check()) {
            abort_unless(tenantAutogestionActualizacionDatosHabilitada(), 404);

            $idNivel = (int) (studentCtx()->idNivel ?? 0);
            abort_unless($idNivel > 0, 403);
        } else {
            abort_unless(auth()->check(), 403);
            $idNivel = (int) (schoolCtx()->idNivel ?? 0);
            abort_unless($idNivel > 0 && PermisosMatriculaWeb::tiene(), 403);
        }

        $path = MatriculaWebDocumentos::pathAlmacenado($tipo, $idNivel);
        if ($path === null) {
            abort(404, 'No hay documento cargado para este nivel.');
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404, 'El archivo no está disponible en el servidor.');
        }

        $nombre = MatriculaWebDocumentos::nombreRegistrado($tipo, $idNivel) ?? 'documento.pdf';

        return $disk->response($path, $nombre, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
