<?php

namespace App\Http\Controllers;

use App\Support\Listados\ListadoFamiliasExport;
use App\Support\Listados\ListadoFamiliasFiltros;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListadoFamiliasExcelController extends Controller
{
    public function __invoke(Request $request, string $ref): StreamedResponse
    {
        abort_unless(puedeConsultarLegajosEstudiantes(), 403);
        abort_unless((int) schoolCtx()->idTerlec > 0, 403);

        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_LISTADO_FAMILIAS_XLSX);
        if ($payload === null) {
            abort(404);
        }

        $key = 'listado-familias-xlsx:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 120);

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $filtros = ListadoFamiliasFiltros::desdePayload($payload);
        $resultado = ListadoFamiliasExport::excel($filtros);

        return response()->streamDownload(
            fn () => ListadoFamiliasExport::escribirEnSalida($resultado['spreadsheet']),
            $resultado['filename'],
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            ],
        );
    }
}
