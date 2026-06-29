<?php

namespace App\Http\Controllers\PortalDocente;

use App\Http\Controllers\CalificacionesSecundario\CargaCalificacionesEpqSecundarioPdfController;
use App\Http\Controllers\Controller;
use App\Support\PortalDocente\CalificacionesDocenteSecundario;
use Illuminate\Http\Request;

/**
 * PDF planilla EPQ secundario — Menú de Docentes.
 */
class PortalDocenteCargaCalificacionesEpqSecundarioPdfController extends Controller
{
    public function __invoke(Request $request, int $curso, int $materia)
    {
        CalificacionesDocenteSecundario::abortSiNoEsSecundario();
        CalificacionesDocenteSecundario::abortSiProfesorSinMateria($materia, $curso);

        $request->merge([
            'curso' => $curso,
            'materia' => $materia,
        ]);

        return app(CargaCalificacionesEpqSecundarioPdfController::class)($request);
    }
}
