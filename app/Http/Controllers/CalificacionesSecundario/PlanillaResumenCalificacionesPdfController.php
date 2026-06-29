<?php

namespace App\Http\Controllers\CalificacionesSecundario;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Support\Listados\ListadoCursoExportParams;
use App\Support\PermisosIaCatalog;
use App\Support\PlanillaResumenCalificacionesSecundario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PlanillaResumenCalificacionesPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(
            tienePermiso(PermisosIaCatalog::CALIF_PLANILLA_RESUMEN),
            403,
            'Sin permiso para planilla resumen de calificaciones.',
        );

        @ini_set('memory_limit', '512M');

        $key = 'planilla-resumen-calificaciones-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $cursosInput = $request->query('cursos');
        if (($cursosInput === null || $cursosInput === '') && $request->filled('curso')) {
            $cursosInput = (string) (int) $request->query('curso');
        }

        $validated = Validator::make(
            ['cursos' => $cursosInput],
            ['cursos' => ['required', 'string', 'max:8000']],
        )->validate();

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

        $ordenados = [];
        foreach ($cursosPermitidos as $c) {
            $id = (int) $c->Id;
            if (in_array($id, $cursoIds, true)) {
                $ordenados[] = $id;
            }
        }

        $secciones = PlanillaResumenCalificacionesSecundario::buildSecciones($ordenados);
        if ($secciones === []) {
            abort(404);
        }

        $ano = $secciones[0]['ano'] ?? null;

        if (count($secciones) === 1) {
            $slug = Str::slug('planilla-resumen-'.($secciones[0]['cursoLabel'] ?? ''), '_');
        } else {
            $slug = Str::slug('planilla-resumen-'.count($secciones).'-cursos', '_');
        }
        if ($slug === '') {
            $slug = 'planilla_resumen';
        }

        $pdf = Pdf::loadView('pdf.planilla-resumen-calificaciones', [
            'pdfHeader' => schoolPdfHeaderData(),
            'ano' => $ano,
            'secciones' => $secciones,
        ])->setPaper('a4', 'landscape');

        $response = $pdf->stream($slug.'.pdf');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
