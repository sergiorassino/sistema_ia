<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Alumnos\FichaMatriculaDatos;
use App\Support\MatriculaBloqueos;

/**
 * El padre elige año actual o año próximo antes de imprimir la ficha.
 */
class FichaMatriculaElegirController extends Controller
{
    public function __invoke()
    {
        abort_unless(tenantAutogestionFichaMatriculaHabilitada(), 404);

        if (! tenantAutogestionFichaMatriculaElegirAnio()) {
            return redirect()->to(se_route_url('alumnos.ficha-matricula'));
        }

        $restriccion = MatriculaBloqueos::paraEstudianteActual();

        return view('alumnos.ficha-matricula-elegir', [
            'bloqueada' => $restriccion['bloqueada'],
            'mensaje' => $restriccion['mensaje'],
            'opciones' => $restriccion['bloqueada'] ? [] : FichaMatriculaDatos::opcionesCicloAutogestion(),
        ]);
    }
}
