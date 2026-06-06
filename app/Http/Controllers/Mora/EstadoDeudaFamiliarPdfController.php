<?php

namespace App\Http\Controllers\Mora;

use App\Http\Controllers\Controller;
use App\Support\Mora\EstadoDeudaFamiliarDatos;
use App\Support\Mora\EstadoDeudaFamiliarTcpdf;
use App\Support\Mora\PermisosMora;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF «Estado de deuda» por familia — Gestión de mora.
 */
class EstadoDeudaFamiliarPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosMora::puedeEstadoDeudaFamiliar(), 403);

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_MORA_ESTADO_DEUDA);
        if ($decoded === null) {
            abort(404);
        }

        $idFamilia = (int) $decoded['id'];
        if ($idFamilia !== (int) $decoded['legajo'] || EstadoDeudaFamiliarDatos::familiaValida($idFamilia) === null) {
            abort(404);
        }

        $key = 'mora-estado-deuda-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = EstadoDeudaFamiliarDatos::paraFamilia($idFamilia);
        if ($datos === null) {
            abort(404);
        }

        $slug = Str::slug(
            'estado-deuda-'.trim((string) ($datos['familiaEtiqueta'] ?? 'familia')),
            '_',
        );
        if ($slug === '') {
            $slug = 'estado_deuda_familiar';
        }

        $pdf = EstadoDeudaFamiliarTcpdf::generar($datos);

        return EstadoDeudaFamiliarTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
