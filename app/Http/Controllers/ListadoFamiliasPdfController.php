<?php

namespace App\Http\Controllers;

use App\Support\Listados\ListadoFamiliasExport;
use App\Support\Listados\ListadoFamiliasFiltros;
use App\Support\Listados\ListadoFamiliasTcpdf;
use App\Support\PermisosIaCatalog;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ListadoFamiliasPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(tienePermiso(PermisosIaCatalog::LISTADO_FAMILIAS), 403);
        abort_unless((int) schoolCtx()->idTerlec > 0, 403);

        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_LISTADO_FAMILIAS_PDF);
        if ($payload === null) {
            abort(404);
        }

        $key = 'listado-familias-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 15)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $filtros = ListadoFamiliasFiltros::desdePayload($payload);
        $datos = ListadoFamiliasExport::datosPdf($filtros);
        $slug = Str::slug('listado-familias-'.schoolCtx()->terlecAno(), '_');
        if ($slug === '') {
            $slug = 'listado_familias';
        }

        $pdf = ListadoFamiliasTcpdf::generar($datos);

        return ListadoFamiliasTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
