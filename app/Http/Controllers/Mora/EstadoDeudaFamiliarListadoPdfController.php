<?php

namespace App\Http\Controllers\Mora;

use App\Http\Controllers\Controller;
use App\Support\Mora\EstadoDeudaFamiliarListadoExport;
use App\Support\Mora\EstadoDeudaListadoFiltros;
use App\Support\Mora\EstadoDeudaListadoTcpdf;
use App\Support\Mora\PermisosMora;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class EstadoDeudaFamiliarListadoPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosMora::puedeEstadoDeudaFamiliar(), 403);

        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_MORA_DEUDA_FAMILIAR_LISTADO_PDF);
        if ($payload === null) {
            abort(404);
        }

        $key = 'mora-deuda-familiar-listado-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 15)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $filtros = EstadoDeudaListadoFiltros::desdePayload($payload);
        $datos = EstadoDeudaFamiliarListadoExport::datosPdf($filtros);
        $slug = Str::slug('listado-deuda-familiar-'.schoolCtx()->terlecAno(), '_');
        if ($slug === '') {
            $slug = 'listado_deuda_familiar';
        }

        $pdf = EstadoDeudaListadoTcpdf::generar($datos);

        return EstadoDeudaListadoTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
