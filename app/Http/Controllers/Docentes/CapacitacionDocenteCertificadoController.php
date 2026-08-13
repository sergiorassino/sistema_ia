<?php

namespace App\Http\Controllers\Docentes;

use App\Http\Controllers\Controller;
use App\Support\CapacitacionDocente\CapacitacionDocenteService;
use App\Support\PermisosIaCatalog;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CapacitacionDocenteCertificadoController extends Controller
{
    public function __invoke(Request $request, string $ref): Response
    {
        abort_unless(auth()->check(), 403);
        abort_unless(tienePermiso(PermisosIaCatalog::CAPACITACION_DOCENTE), 403);
        abort_unless(CapacitacionDocenteService::tablaDisponible(), 503);

        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_CAPACITACION_DOCENTE_CERT);
        if ($payload === null) {
            abort(404);
        }

        $id = (int) ($payload['c'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $reg = CapacitacionDocenteService::scopedOrFail($id);
        $ruta = trim((string) ($reg->certificado_archivo ?? ''));
        if ($ruta === '' || ! CapacitacionDocenteService::existeCertificado($ruta)) {
            abort(404, 'El certificado no está disponible.');
        }

        $nombreDescarga = basename($ruta);
        if ($nombreDescarga === '' || $nombreDescarga === '.' || $nombreDescarga === '..') {
            $nombreDescarga = 'certificado-capacitacion.pdf';
        }

        return Storage::disk(CapacitacionDocenteService::DISK)->response($ruta, $nombreDescarga, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
