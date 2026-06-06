<?php

namespace App\Http\Controllers\Cuotas;

use App\Http\Controllers\Controller;
use App\Support\Cuotas\ListadoPagosPorFechaDatos;
use App\Support\Cuotas\ListadoPagosPorFechaTcpdf;
use App\Support\PermisosCuotas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF «Listado de pagos por fecha» — Gestión de aranceles (Administración).
 */
class ListadoPagosPorFechaPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(PermisosCuotas::puedeListadoPagosPorFecha(), 403);

        @ini_set('memory_limit', '512M');

        $key = 'cuotas-listado-pagos-fecha-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $filtros = ListadoPagosPorFechaDatos::normalizarFiltros($request->query());

        $datos = ListadoPagosPorFechaDatos::build($filtros);
        if ($datos === null) {
            abort(404);
        }

        $ano = (int) ($datos['ano'] ?? schoolCtx()->terlecAno());
        $slug = Str::slug(
            'listado-pagos-'.$filtros['fechaDesde'].'-'.$filtros['fechaHasta'].'-'.$ano,
            '_',
        );
        if ($slug === '') {
            $slug = 'listado_pagos_por_fecha';
        }

        $pdf = ListadoPagosPorFechaTcpdf::generar($datos);

        return ListadoPagosPorFechaTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
