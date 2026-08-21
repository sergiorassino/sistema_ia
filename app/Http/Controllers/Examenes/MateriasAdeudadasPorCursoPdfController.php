<?php

namespace App\Http\Controllers\Examenes;

use App\Http\Controllers\Controller;
use App\Support\Examenes\MateriasAdeudadasPorCurso;
use App\Support\Examenes\MateriasAdeudadasPorCursoTcpdf;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class MateriasAdeudadasPorCursoPdfController extends Controller
{
    public function __invoke(Request $request, string $ref): Response|RedirectResponse
    {
        abort_unless(tienePermiso(12), 403, 'Sin permiso para el módulo de exámenes.');

        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $key = 'materias-adeudadas-por-curso-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $payload = OpaqueRouteToken::decodePayload(
            $ref,
            OpaqueRouteToken::PURPOSE_MATERIAS_ADEUDADAS_POR_CURSO,
        );
        $idsRaw = $payload['c'] ?? [];
        if (! is_array($idsRaw)) {
            $idsRaw = [(int) $idsRaw];
        }

        $ctx = schoolCtx();
        if (! $ctx->isValid() || ! MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion(MateriasAdeudadasPreparacion::MODULO_LISTADO)) {
            return redirect()
                ->route('examenes.materias-adeudadas')
                ->with('status', 'Seleccioná el turno y el año lectivo antes de generar el PDF.');
        }

        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;

        $idsCursos = MateriasAdeudadasPorCurso::filtrarIdsPermitidos($idsRaw, $idNivel, $idTerlec);
        if ($idsCursos === []) {
            abort(404);
        }

        $hojas = MateriasAdeudadasPorCurso::datosPdfLote($idsCursos, $idNivel, $idTerlec);
        if ($hojas === []) {
            abort(404);
        }

        $cant = count($hojas);
        if ($cant === 1) {
            $slug = Str::slug('adeudadas-por-curso-'.($hojas[0]['cursoLabel'] ?? '').'-'.($ctx->terlecAno() ?? ''), '_');
        } else {
            $slug = Str::slug('adeudadas-por-curso-'.$cant.'-cursos-'.($ctx->terlecAno() ?? ''), '_');
        }
        if ($slug === '') {
            $slug = 'adeudadas_por_curso';
        }

        $pdf = MateriasAdeudadasPorCursoTcpdf::generarLote($hojas, schoolPdfHeaderData());

        return MateriasAdeudadasPorCursoTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
