<?php

namespace App\Http\Controllers\Mora;

use App\Http\Controllers\Controller;
use App\Support\Mora\GestionMorososFiltros;
use App\Support\Mora\ListadoMorososDatos;
use App\Support\Mora\ListadoMorososTcpdf;
use App\Support\Mora\PermisosMora;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF «Listado de deuda» — Gestión de morosos.
 */
class ListadoMorososPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosMora::puedeGestionMorosos(), 403);

        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_MORA_LISTADO_DEUDA);
        if ($payload === null) {
            abort(404);
        }

        $key = 'mora-listado-deuda-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 15)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        @ini_set('memory_limit', '512M');

        try {
            $filtros = GestionMorososFiltros::normalizarDesdeLivewire($payload);
        } catch (\Illuminate\Validation\ValidationException) {
            abort(404);
        }

        $datos = ListadoMorososDatos::build($filtros);
        if ($datos === null) {
            abort(404, 'No hay registros de deuda para los filtros indicados.');
        }

        $slug = Str::slug('listado-deuda-'.($datos['fechaCalculo'] ?? ''), '_');
        if ($slug === '') {
            $slug = 'listado_deuda';
        }

        $pdf = ListadoMorososTcpdf::generar($datos);

        return ListadoMorososTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
