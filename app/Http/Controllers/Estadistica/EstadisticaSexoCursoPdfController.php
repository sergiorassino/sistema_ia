<?php

namespace App\Http\Controllers\Estadistica;

use App\Http\Controllers\Controller;
use App\Support\Estadistica\EstadisticaSexoCursoDatos;
use App\Support\Estadistica\EstadisticaSexoCursoTcpdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class EstadisticaSexoCursoPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        EstadisticaSexoCursoDatos::asegurarAcceso();

        $key = 'estadistica-sexo-curso-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = EstadisticaSexoCursoDatos::armar();
        $ano = $datos['ano'] !== '' ? $datos['ano'] : 'ciclo';
        $pdf = EstadisticaSexoCursoTcpdf::generar($datos);

        return EstadisticaSexoCursoTcpdf::respuestaHttp($pdf, 'estadistica-sexo-curso-'.$ano.'.pdf');
    }
}
