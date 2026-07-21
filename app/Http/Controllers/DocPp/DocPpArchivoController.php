<?php

namespace App\Http\Controllers\DocPp;

use App\Http\Controllers\Controller;
use App\Support\DocPp\DocPpConsulta;
use App\Support\DocPp\DocPpStorage;
use App\Support\PermisosIaCatalog;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DocPpArchivoController extends Controller
{
    public function __invoke(Request $request, string $ref): Response
    {
        abort_unless(auth()->check(), 403);
        abort_unless(tienePermiso(PermisosIaCatalog::PLANIFICACIONES_PROGRAMAS), 403);
        abort_unless(tenantDocPpHabilitado(), 404);

        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_DOC_PP_ARCHIVO);
        if ($payload === null) {
            abort(404);
        }

        $idDoc = (int) ($payload['d'] ?? 0);
        if ($idDoc <= 0) {
            abort(404);
        }

        $ctx = schoolCtx();
        $doc = DocPpConsulta::documentoEnContexto($idDoc, (int) $ctx->idNivel, (int) $ctx->idTerlec);
        if ($doc === null) {
            abort(404);
        }

        $nombre = trim((string) ($doc->nombre_archivo ?? ''));
        if ($nombre === '') {
            abort(404, 'No hay archivo cargado para este registro.');
        }

        $anio = (int) ($doc->terlec?->ano ?? $ctx->terlecAno() ?? 0);
        if ($anio <= 0 && (int) $doc->idTerlec > 0) {
            $anio = (int) (\App\Models\Terlec::query()->whereKey((int) $doc->idTerlec)->value('ano') ?? 0);
        }

        $ruta = DocPpStorage::rutaRelativaArchivo($anio, (string) $doc->tipo, (int) $doc->idNivel, $nombre);
        $disk = Storage::disk(DocPpStorage::DISK);

        if (! $disk->exists($ruta)) {
            abort(404, 'El archivo no está disponible en el servidor.');
        }

        return $disk->response($ruta, $nombre, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
