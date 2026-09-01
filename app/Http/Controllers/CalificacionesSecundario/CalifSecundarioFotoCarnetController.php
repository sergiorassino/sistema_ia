<?php

namespace App\Http\Controllers\CalificacionesSecundario;

use App\Http\Controllers\Controller;
use App\Models\Legajo;
use App\Support\Alumnos\FotoCarnetLegajo;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\PortalDocenteContext;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sirve la foto carnet en carga de calificaciones (Menú de Secretaría).
 */
class CalifSecundarioFotoCarnetController extends Controller
{
    public function __invoke(Request $request, string $ref): BinaryFileResponse
    {
        PortalDocenteContext::abortSiStaffSinPermisoIa(PermisosIaCatalog::CALIF_CARGA);
        abort_unless(FotoCarnetLegajo::habilitadaEnSolapasLegajo(), 404);
        abort_unless(Schema::hasColumn('legajos', FotoCarnetLegajo::COLUMNA), 404);

        $idLegajo = OpaqueRouteToken::decodeCalifSecundarioFotoCarnet($ref);
        abort_unless($idLegajo !== null && $idLegajo > 0, 404);
        abort_unless($this->legajoVisibleEnCarga($idLegajo), 404);

        $legajo = Legajo::query()->find($idLegajo);
        abort_unless($legajo !== null, 404);

        return FotoCarnetLegajo::respuestaHttp((string) ($legajo->fotoCarnet ?? ''));
    }

    private function legajoVisibleEnCarga(int $idLegajo): bool
    {
        $ctx = schoolCtx();

        return DB::table('calificaciones as c')
            ->join('cursos as cur', 'cur.Id', '=', 'c.idCursos')
            ->where('c.idLegajos', $idLegajo)
            ->where('c.idTerlec', (int) $ctx->idTerlec)
            ->where('cur.idNivel', (int) $ctx->idNivel)
            ->where('cur.idTerlec', (int) $ctx->idTerlec)
            ->exists();
    }
}
