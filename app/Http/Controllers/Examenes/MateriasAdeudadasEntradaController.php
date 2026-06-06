<?php

namespace App\Http\Controllers\Examenes;

use App\Http\Controllers\Controller;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use Illuminate\Http\RedirectResponse;

/**
 * Entrada desde el menú: dispara preparación y recálculo de condiciones en el módulo destino.
 */
class MateriasAdeudadasEntradaController extends Controller
{
    public function listado(): RedirectResponse
    {
        abort_unless(tienePermiso(12), 403, 'Sin permiso para el módulo de exámenes.');

        MateriasAdeudadasPreparacion::solicitarFormularioPreparacion(
            MateriasAdeudadasPreparacion::MODULO_LISTADO,
        );

        return redirect()->route('examenes.materias-adeudadas');
    }

    public function gestion(): RedirectResponse
    {
        abort_unless(tienePermiso(12), 403, 'Sin permiso para el módulo de exámenes.');

        MateriasAdeudadasPreparacion::solicitarFormularioPreparacion(
            MateriasAdeudadasPreparacion::MODULO_GESTION,
        );

        return redirect()->route('examenes.materias-adeudadas.gestion');
    }

    public function actaVolante(): RedirectResponse
    {
        abort_unless(tienePermiso(12), 403, 'Sin permiso para el módulo de exámenes.');

        MateriasAdeudadasPreparacion::solicitarFormularioPreparacion(
            MateriasAdeudadasPreparacion::MODULO_ACTA_VOLANTE,
        );

        return redirect()->route('examenes.acta-volante-previos');
    }

    public function permisoExamen(): RedirectResponse
    {
        abort_unless(tienePermiso(12), 403, 'Sin permiso para el módulo de exámenes.');

        MateriasAdeudadasPreparacion::solicitarFormularioPreparacion(
            MateriasAdeudadasPreparacion::MODULO_PERMISO_EXAMEN,
        );

        return redirect()->route('examenes.permiso-examen');
    }
}
