<?php

namespace App\Http\Controllers\CalificacionesSecundario;

use App\Http\Controllers\Controller;
use App\Support\CalificacionesSecundario\PlanillaCalificacionesTcpdf;
use App\Support\PlanillaCalificacionesSecundario;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PlanillaCalificacionesPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        @ini_set('memory_limit', '512M');

        $key = 'planilla-calificaciones-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $materiasInput = $request->query('materias');
        if (($materiasInput === null || $materiasInput === '') && $request->filled('materia')) {
            $materiasInput = (string) (int) $request->query('materia');
        }

        $validated = Validator::make(
            [
                'curso' => $request->query('curso'),
                'materias' => $materiasInput,
            ],
            [
                'curso' => ['required', 'integer', 'min:1'],
                'materias' => ['required', 'string', 'max:8000'],
            ],
        )->validate();

        $cursoId = (int) $validated['curso'];
        $materiasPermitidas = PlanillaCalificacionesSecundario::materiasDelCurso($cursoId);
        if ($materiasPermitidas->isEmpty()) {
            abort(404);
        }

        $materiaIds = PlanillaCalificacionesSecundario::resolverIdsMaterias(
            trim((string) $validated['materias']),
            $materiasPermitidas,
        );
        if ($materiaIds === []) {
            abort(404);
        }

        $payload = PlanillaCalificacionesSecundario::buildSecciones($cursoId, $materiaIds);
        $secciones = $payload['secciones'];
        $cursoLabel = $payload['cursoLabel'];
        $ano = $payload['ano'];

        if (count($secciones) === 1) {
            $slug = Str::slug(
                'planilla-calificaciones-'.($secciones[0]['materiaLabel'] ?? '').'-'.($cursoLabel ?? ''),
                '_',
            );
        } else {
            $slug = Str::slug(
                'planilla-calificaciones-'.($cursoLabel ?? '').'-'.count($secciones).'-materias',
                '_',
            );
        }
        if ($slug === '') {
            $slug = 'planilla_calificaciones';
        }

        $pdf = PlanillaCalificacionesTcpdf::generar([
            'pdfHeader' => schoolPdfHeaderData(),
            'ano' => $ano,
            'cursoLabel' => $cursoLabel,
            'secciones' => $secciones,
        ]);

        $binary = $pdf->Output($slug.'.pdf', 'S');

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$slug.'.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
