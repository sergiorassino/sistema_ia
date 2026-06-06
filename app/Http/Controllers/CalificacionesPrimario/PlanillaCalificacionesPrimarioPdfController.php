<?php

namespace App\Http\Controllers\CalificacionesPrimario;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Support\CalificacionesPrimario\PlanillaCalificacionesPrimarioDatos;
use App\Support\CalificacionesPrimario\PlanillaCalificacionesPrimarioTcpdf;
use App\Support\Listados\ListadoCursoExportParams;
use App\Support\NivelSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PlanillaCalificacionesPrimarioPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(
            NivelSistema::esPrimario((int) schoolCtx()->idNivel),
            403,
            'Esta planilla corresponde al nivel primario.'
        );

        @ini_set('memory_limit', '512M');

        $key = 'planilla-calificaciones-primario-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $cursosInput = $request->query('cursos');
        if (($cursosInput === null || $cursosInput === '') && $request->filled('curso')) {
            $cursosInput = (string) (int) $request->query('curso');
        }

        $validated = Validator::make(
            [
                'cursos' => $cursosInput,
                'etapa' => $request->query('etapa'),
            ],
            [
                'cursos' => ['required', 'string', 'max:8000'],
                'etapa' => ['required', 'integer', 'in:1,2,9'],
            ],
        )->validate();

        $etapa = (int) $validated['etapa'];
        $ctx = schoolCtx();

        $cursosPermitidos = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get();

        if ($cursosPermitidos->isEmpty()) {
            abort(404);
        }

        $allowedById = $cursosPermitidos->keyBy(fn (Curso $c) => (int) $c->Id);
        $cursoIds = ListadoCursoExportParams::resolverIdsCursos(trim((string) $validated['cursos']), $allowedById);
        if ($cursoIds === []) {
            abort(404);
        }

        $ordenados = PlanillaCalificacionesPrimarioDatos::ordenarIdsCursos($cursoIds, $cursosPermitidos);
        $secciones = PlanillaCalificacionesPrimarioDatos::buildSecciones($ordenados, $etapa);
        if ($secciones === []) {
            abort(404);
        }

        $contexto = PlanillaCalificacionesPrimarioDatos::contextoPdf($etapa);

        if (count($secciones) === 1) {
            $slug = Str::slug('planilla-calificaciones-'.($secciones[0]['cursoLabel'] ?? ''), '_');
        } else {
            $slug = Str::slug('planilla-calificaciones-'.count($secciones).'-cursos-etapa-'.$etapa, '_');
        }
        if ($slug === '') {
            $slug = 'planilla_calificaciones_primario';
        }

        $pdf = PlanillaCalificacionesPrimarioTcpdf::generar($contexto, $secciones);

        return PlanillaCalificacionesPrimarioTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
