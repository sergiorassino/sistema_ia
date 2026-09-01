<?php

namespace App\Http\Controllers\PortalDocente;

use App\Http\Controllers\Controller;
use App\Models\Legajo;
use App\Support\Alumnos\FotoCarnetLegajo;
use App\Support\PortalDocente\CalificacionesDocenteSecundario;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sirve la foto carnet al docente (solo estudiantes de cursos asignados en ppc).
 */
class PortalDocenteFotoCarnetController extends Controller
{
    public function __invoke(Request $request, string $ref): BinaryFileResponse
    {
        abort_unless(FotoCarnetLegajo::habilitadaEnSolapasLegajo(), 404);
        abort_unless(Schema::hasColumn('legajos', FotoCarnetLegajo::COLUMNA), 404);

        $idLegajo = OpaqueRouteToken::decodePortalDocenteFotoCarnet($ref);
        abort_unless($idLegajo !== null && $idLegajo > 0, 404);

        $idProfesor = (int) (schoolCtx()->idProfesor ?? 0);
        abort_unless(
            CalificacionesDocenteSecundario::profesorTieneAlumnoEnCursosAsignados($idProfesor, $idLegajo),
            404,
        );

        $legajo = Legajo::query()->find($idLegajo);
        abort_unless($legajo !== null, 404);

        return FotoCarnetLegajo::respuestaHttp((string) ($legajo->fotoCarnet ?? ''));
    }
}
