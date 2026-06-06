<?php

namespace App\Http\Controllers\Examenes;

use App\Support\Examenes\MateriasAdeudadasExporter;
use App\Support\Examenes\MateriasAdeudadasFiltros;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MateriasAdeudadasPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(tienePermiso(12), 403, 'Sin permiso para el módulo de exámenes.');

        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $key = 'materias-adeudadas-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = Validator::make($request->query(), MateriasAdeudadasFiltros::reglasValidacionPdf());
        if ($validated->fails()) {
            abort(404);
        }

        $data = $validated->validated();
        $agrupar = MateriasAdeudadasFiltros::normalizeAgrupar($data['agrupar'] ?? null);
        $condicion = isset($data['condicion']) ? (string) $data['condicion'] : null;
        $inscri = isset($data['inscri']) ? (string) $data['inscri'] : null;

        $ctx = schoolCtx();
        if (! $ctx->isValid() || ! MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion(MateriasAdeudadasPreparacion::MODULO_LISTADO)) {
            return redirect()
                ->route('examenes.materias-adeudadas')
                ->with('status', 'Seleccioná el turno y el año lectivo antes de generar el PDF.');
        }

        $filas = MateriasAdeudadasExporter::filas((int) $ctx->idNivel, $condicion, $inscri);
        $bloques = MateriasAdeudadasExporter::agrupar($filas, $agrupar);

        $tituloAgrupacion = $agrupar === MateriasAdeudadasFiltros::AGRUPAR_MATERIA_CURSO
            ? 'Por materia y curso'
            : 'Por estudiante';

        $filtrosActivos = [];
        if ($condicion !== null && $condicion !== '') {
            $filtrosActivos[] = 'Condición: '.$condicion;
        }
        if ($inscri !== null && $inscri !== '') {
            $filtrosActivos[] = 'Inscripto: '.($inscri === MateriasAdeudadasFiltros::INSCRI_SI ? 'Sí' : 'No');
        }

        $slug = Str::slug('materias-adeudadas-'.($ctx->terlecAno() ?? ''), '_');
        if ($slug === '') {
            $slug = 'materias_adeudadas';
        }

        $pdf = Pdf::loadView('examenes::pdf.materias-adeudadas', [
            'pdfHeader' => schoolPdfHeaderData(),
            'bloques' => $bloques,
            'totalFilas' => count($filas),
            'tituloAgrupacion' => $tituloAgrupacion,
            'porMateriaCurso' => $agrupar === MateriasAdeudadasFiltros::AGRUPAR_MATERIA_CURSO,
            'filtrosActivos' => $filtrosActivos,
            'nivelNombre' => $ctx->nivelNombre(),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('dpi', 72);

        return $pdf->stream($slug.'.pdf');
    }
}
