<?php

namespace App\Http\Controllers\PortalDocente;

use App\Http\Controllers\CalificacionesSecundario\PlanillaCalificacionesPdfController;
use App\Http\Controllers\Controller;
use App\Support\PortalDocente\CalificacionesDocenteSecundario;
use Illuminate\Http\Request;

/**
 * PDF de planilla de calificaciones — Menú de Docentes (solo materias asignadas en ppc).
 */
class PortalDocentePlanillaCalificacionesPdfController extends Controller
{
    public function __invoke(Request $request, int $curso, int $materia)
    {
        CalificacionesDocenteSecundario::abortSiNoEsSecundario();
        CalificacionesDocenteSecundario::abortSiProfesorSinMateria($materia, $curso);

        $request->merge([
            'curso' => $curso,
            'materia' => $materia,
        ]);

        return app(PlanillaCalificacionesPdfController::class)($request);
    }
}
