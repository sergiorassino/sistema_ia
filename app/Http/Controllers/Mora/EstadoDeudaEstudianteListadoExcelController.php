<?php

namespace App\Http\Controllers\Mora;

use App\Http\Controllers\Controller;
use App\Support\Mora\EstadoDeudaEstudianteListadoExport;
use App\Support\Mora\EstadoDeudaListadoExcel;
use App\Support\Mora\EstadoDeudaListadoFiltros;
use App\Support\Mora\PermisosMora;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EstadoDeudaEstudianteListadoExcelController extends Controller
{
    public function __invoke(Request $request, string $ref): StreamedResponse
    {
        abort_unless(PermisosMora::puedeEstadoDeudaEstudiante(), 403);

        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_MORA_DEUDA_ESTUDIANTE_LISTADO_XLSX);
        if ($payload === null) {
            abort(404);
        }

        $key = 'mora-deuda-estudiante-listado-xlsx:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 120);

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $filtros = EstadoDeudaListadoFiltros::desdePayload($payload);
        $resultado = EstadoDeudaEstudianteListadoExport::excel($filtros);

        return response()->streamDownload(
            fn () => EstadoDeudaListadoExcel::escribirEnSalida($resultado['spreadsheet']),
            $resultado['filename'],
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            ],
        );
    }
}
