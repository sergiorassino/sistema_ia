<?php

namespace App\Http\Controllers\Cooperadora;

use App\Http\Controllers\Controller;
use App\Models\CoopIngreso;
use App\Support\Cooperadora\CooperadoraConfig;
use App\Support\Cooperadora\NumeroDocumentoCooperadora;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\Cooperadora\ReciboTcpdf;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ReciboPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosCooperadora::puedeIngresos(), 403);

        $key = 'coop:recibo-pdf:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429);
        }
        RateLimiter::hit($key, 60);

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_COOP_RECIBO);
        abort_unless($decoded !== null, 404);
        $id = (int) $decoded['id'];

        $ingreso = CoopIngreso::query()
            ->where('anulado', false)
            ->findOrFail($id);

        $pdf = ReciboTcpdf::generar([
            'header' => CooperadoraConfig::datosPdfHeader(),
            'recibo_numero_texto' => NumeroDocumentoCooperadora::formatearRecibo((int) $ingreso->recibo_numero),
            'fecha_texto' => $ingreso->fecha->format('d/m/Y'),
            'pagador_nombre' => $ingreso->pagador_nombre,
            'importe_letras' => $ingreso->importe_letras,
            'concepto' => $ingreso->concepto,
            'importe' => (float) $ingreso->importe,
        ]);

        return ReciboTcpdf::respuestaHttp($pdf, 'recibo-cooperadora.pdf');
    }
}
