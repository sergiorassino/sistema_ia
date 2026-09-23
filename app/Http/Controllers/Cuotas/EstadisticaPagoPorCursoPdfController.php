<?php

namespace App\Http\Controllers\Cuotas;

use App\Http\Controllers\Controller;
use App\Support\Cuotas\EstadisticaPagoPorCursoDatos;
use App\Support\Cuotas\EstadisticaPagoPorCursoTcpdf;
use App\Support\PermisosCuotas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF «Estadística de pago por curso y cuota» — Resúmenes (Administración).
 */
class EstadisticaPagoPorCursoPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(PermisosCuotas::puedeEstadisticaPagoPorCurso(), 403);

        @ini_set('memory_limit', '512M');

        $key = 'cuotas-estadistica-pago-curso-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $filtros = EstadisticaPagoPorCursoDatos::normalizarFiltros($request->query());
        if ($filtros === null) {
            abort(404);
        }

        $datos = EstadisticaPagoPorCursoDatos::build($filtros['fecha'], $filtros['idNivel']);
        if ($datos === null) {
            abort(404);
        }

        $ano = (int) ($datos['ano'] ?? schoolCtx()->terlecAno());
        $slug = Str::slug('estadistica-pago-por-curso-'.$filtros['fecha']->format('Y-m-d').'-'.$ano, '_');
        if ($slug === '') {
            $slug = 'estadistica_pago_por_curso';
        }

        $pdf = EstadisticaPagoPorCursoTcpdf::generar($datos);

        return EstadisticaPagoPorCursoTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
