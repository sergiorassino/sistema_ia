<?php

namespace App\Http\Controllers\PlanificacionesProgramas;

use App\Http\Controllers\Controller;
use App\Support\PermisosIaCatalog;
use App\Support\PlanificacionesProgramas\PlanificacionesProgramasConsulta;
use App\Support\PlanificacionesProgramas\PlanificacionesProgramasStorage;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PlanificacionesProgramasArchivoController extends Controller
{
    public function __invoke(Request $request, string $ref): Response
    {
        abort_unless(auth()->check(), 403);
        abort_unless(tienePermiso(PermisosIaCatalog::PLANIFICACIONES_PROGRAMAS), 403);

        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_PLANIFICACIONES_PROGRAMAS_ARCHIVO);
        if ($payload === null) {
            abort(404);
        }

        $idMateria = (int) ($payload['m'] ?? 0);
        $tipo = (string) ($payload['t'] ?? '');
        if ($idMateria <= 0 || ! PlanificacionesProgramasStorage::tipoValido($tipo)) {
            abort(404);
        }

        $ctx = schoolCtx();
        $fila = PlanificacionesProgramasConsulta::materiaEnContexto($idMateria, (int) $ctx->idNivel, (int) $ctx->idTerlec);
        if ($fila === null) {
            abort(404);
        }

        $cols = PlanificacionesProgramasStorage::columnasPorTipo($tipo);
        $nombre = trim((string) ($fila->{$cols['nombre']} ?? ''));
        if ((int) ($fila->{$cols['flag']} ?? 0) !== 1 || $nombre === '') {
            abort(404, 'No hay archivo cargado para este registro.');
        }

        $anio = (int) ($fila->ano_lectivo ?? $ctx->terlecAno() ?? 0);
        $idNivel = (int) ($fila->idNivel ?? 0);
        $ruta = PlanificacionesProgramasStorage::rutaRelativaArchivo($anio, $tipo, $idNivel, $nombre);
        $disk = Storage::disk(PlanificacionesProgramasStorage::DISK);

        if (! $disk->exists($ruta)) {
            abort(404, 'El archivo no está disponible en el servidor.');
        }

        return $disk->response($ruta, $nombre, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
