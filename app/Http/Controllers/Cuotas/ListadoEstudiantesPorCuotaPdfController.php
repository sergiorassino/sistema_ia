<?php

namespace App\Http\Controllers\Cuotas;

use App\Http\Controllers\Controller;
use App\Support\Cuotas\ListadoEstudiantesPorCuotaDatos;
use App\Support\Cuotas\ListadoEstudiantesPorCuotaTcpdf;
use App\Support\PermisosCuotas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF «Listado de estudiantes por cuota» — Gestión de aranceles (Administración).
 */
class ListadoEstudiantesPorCuotaPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(PermisosCuotas::puedeListadoEstudiantesPorCuota(), 403);

        @ini_set('memory_limit', '512M');

        $key = 'cuotas-listado-estudiantes-cuota-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $filtros = ListadoEstudiantesPorCuotaDatos::normalizarFiltros($request->query());

        $datos = ListadoEstudiantesPorCuotaDatos::build($filtros);
        if ($datos === null) {
            abort(404);
        }

        $ano = (int) ($datos['anoContexto'] ?? schoolCtx()->terlecAno());
        $slug = Str::slug('listado-estudiantes-cuota-'.$ano, '_');
        if ($slug === '') {
            $slug = 'listado_estudiantes_por_cuota';
        }

        $pdf = ListadoEstudiantesPorCuotaTcpdf::generar($datos);

        return ListadoEstudiantesPorCuotaTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
