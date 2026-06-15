<?php

namespace App\Http\Controllers\Cooperadora;

use App\Http\Controllers\Controller;
use App\Models\CoopEgreso;
use App\Support\Cooperadora\CooperadoraConfig;
use App\Support\Cooperadora\NumeroDocumentoCooperadora;
use App\Support\Cooperadora\OrdenPagoTcpdf;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\Security\OpaqueRouteToken;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class OrdenPagoPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosCooperadora::puedeEgresos(), 403);

        $key = 'coop:orden-pdf:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429);
        }
        RateLimiter::hit($key, 60);

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_COOP_ORDEN_PAGO);
        abort_unless($decoded !== null, 404);
        $id = (int) $decoded['id'];

        $egreso = CoopEgreso::query()
            ->with('proveedor:id,nombre')
            ->findOrFail($id);

        $pdf = OrdenPagoTcpdf::generar([
            'header' => CooperadoraConfig::datosPdfHeader(),
            'orden_numero_texto' => NumeroDocumentoCooperadora::formatearOrden((int) $egreso->orden_numero),
            'fecha_texto' => $egreso->fecha->format('d/m/Y'),
            'proveedor_nombre' => (string) ($egreso->proveedor?->nombre ?? ''),
            'importe_letras' => $egreso->importe_letras,
            'concepto' => $egreso->concepto,
            'importe' => (float) $egreso->importe,
            'firmante' => $egreso->firmante,
            'anulado' => (bool) $egreso->anulado,
        ]);

        return OrdenPagoTcpdf::respuestaHttp($pdf, 'orden-pago-cooperadora.pdf');
    }
}
