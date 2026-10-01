<?php

namespace App\Http\Controllers\Estadistica;

use App\Http\Controllers\Controller;
use App\Support\Estadistica\EstadisticaPorEdadDatos;
use App\Support\Estadistica\EstadisticaPorEdadTcpdf;
use App\Support\NivelSistema;
use App\Support\PermisosIaCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF «Estadísticas por edad» — Menú de Secretaría.
 */
class EstadisticaPorEdadPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ESTADISTICA_POR_EDAD), 403);
        abort_unless(NivelSistema::esNivelPedagogico((int) (schoolCtx()->idNivel ?? 0)), 403);

        @ini_set('memory_limit', '256M');

        $key = 'estadistica-por-edad-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $fecha = EstadisticaPorEdadDatos::parseFecha($request->query('fecha'));
        if ($fecha === null) {
            abort(404);
        }

        $datos = EstadisticaPorEdadDatos::build($fecha);
        if ($datos === null) {
            abort(404);
        }

        $slug = Str::slug('estadistica-por-edad-'.$fecha->format('Y-m-d'), '-');
        if ($slug === '') {
            $slug = 'estadistica-por-edad';
        }

        $pdf = EstadisticaPorEdadTcpdf::generar($datos);

        return EstadisticaPorEdadTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
