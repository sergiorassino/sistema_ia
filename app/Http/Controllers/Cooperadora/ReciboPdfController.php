<?php

namespace App\Http\Controllers\Cooperadora;

use App\Http\Controllers\Controller;
use App\Support\Cooperadora\CooperadoraConfig;
use App\Support\Cooperadora\NumeroDocumentoCooperadora;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\Cooperadora\ReciboIngresosGrupo;
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

        $ingresos = ReciboIngresosGrupo::ingresosDelRecibo($id);
        abort_if($ingresos->isEmpty(), 404);

        $lider = $ingresos->first();
        $pdfDatos = ReciboIngresosGrupo::datosPdf($ingresos);

        $pdf = ReciboTcpdf::generar([
            'header' => CooperadoraConfig::datosPdfHeader(),
            'recibo_numero_texto' => NumeroDocumentoCooperadora::formatearRecibo((int) $lider->recibo_numero),
            'fecha_texto' => $lider->fecha->format('d/m/Y'),
            'pagador_nombre' => $lider->pagador_nombre,
            'importe_letras' => $pdfDatos['importe_letras'],
            'importe' => $pdfDatos['importe_total'],
            'lineas' => $pdfDatos['lineas'],
        ]);

        return ReciboTcpdf::respuestaHttp($pdf, 'recibo-cooperadora.pdf');
    }
}
