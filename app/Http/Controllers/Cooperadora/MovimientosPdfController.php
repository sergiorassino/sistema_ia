<?php

namespace App\Http\Controllers\Cooperadora;

use App\Http\Controllers\Controller;
use App\Support\Cooperadora\CooperadoraConfig;
use App\Support\Cooperadora\MovimientosConsulta;
use App\Support\Cooperadora\MovimientosFiltros;
use App\Support\Cooperadora\MovimientosTcpdf;
use App\Support\Cooperadora\PermisosCooperadora;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class MovimientosPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(PermisosCooperadora::puedeMovimientos(), 403);

        $key = 'coop:movimientos-pdf:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 15)) {
            abort(429);
        }
        RateLimiter::hit($key, 60);

        $validated = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'tipo_mov' => ['nullable', 'in:ingreso,egreso'],
            'id_rubro' => ['nullable', 'integer', 'min:1'],
            'id_item' => ['nullable', 'integer', 'min:1'],
            'id_proveedor' => ['nullable', 'integer', 'min:1'],
            'tipo_ingreso' => ['nullable', 'in:por_alumno,eventual,uniforme'],
            'id_medio_pago' => ['nullable', 'integer', 'min:1'],
            'busqueda' => ['nullable', 'string', 'max:120'],
        ]);

        $filtros = MovimientosFiltros::desde($validated);
        $filas = MovimientosConsulta::listado($validated['desde'], $validated['hasta'], $filtros);
        $resumen = MovimientosConsulta::conSaldoAcumulado($filas);

        $pdf = MovimientosTcpdf::generar([
            'header' => CooperadoraConfig::datosPdfHeader(),
            'fecha_desde_texto' => Carbon::parse($validated['desde'])->format('d/m/Y'),
            'fecha_hasta_texto' => Carbon::parse($validated['hasta'])->format('d/m/Y'),
            'filas' => $resumen['filas_con_saldo']->all(),
            'total_ingresos' => $resumen['total_ingresos'],
            'total_egresos' => $resumen['total_egresos'],
            'saldo' => $resumen['saldo'],
        ]);

        return MovimientosTcpdf::respuestaHttp($pdf, 'movimientos-cooperadora.pdf');
    }
}
