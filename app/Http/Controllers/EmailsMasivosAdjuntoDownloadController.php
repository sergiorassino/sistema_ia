<?php

namespace App\Http\Controllers;

use App\Support\EmailsMasivos\EmailsMasivosAdjuntosStorage;
use App\Support\PermisosIaCatalog;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailsMasivosAdjuntoDownloadController extends Controller
{
    public function __invoke(Request $request, string $ref): StreamedResponse
    {
        abort_unless(tienePermiso(PermisosIaCatalog::EMAILS_MASIVOS_ESTUDIANTES), 403);

        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_EMAILS_MASIVOS_ADJUNTO);
        abort_if($payload === null, 404);

        $idTerlec = (int) ($payload['t'] ?? 0);
        $idEmailEscrito = (int) ($payload['e'] ?? 0);
        $nombre = (string) ($payload['n'] ?? '');

        $ctx = schoolCtx();
        abort_unless($idTerlec === (int) $ctx->idTerlec, 404);
        abort_if($idEmailEscrito <= 0 || $nombre === '', 404);

        $path = EmailsMasivosAdjuntosStorage::rutaArchivo($idTerlec, $idEmailEscrito, $nombre);
        abort_if($path === null, 404);

        $disk = Storage::disk(EmailsMasivosAdjuntosStorage::DISK);

        return $disk->download($path, $nombre);
    }
}
