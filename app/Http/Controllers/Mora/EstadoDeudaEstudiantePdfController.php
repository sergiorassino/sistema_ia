<?php

namespace App\Http\Controllers\Mora;

use App\Http\Controllers\Controller;
use App\Support\Mora\EstadoDeudaEstudianteDatos;
use App\Support\Mora\EstadoDeudaFamiliarTcpdf;
use App\Support\Mora\PermisosMora;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF «Estado de deuda» por estudiante — Gestión de mora.
 */
class EstadoDeudaEstudiantePdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosMora::puedeEstadoDeudaEstudiante(), 403);

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_MORA_ESTADO_DEUDA_ESTUDIANTE);
        if ($decoded === null) {
            abort(404);
        }

        $idLegajo = (int) $decoded['id'];
        if ($idLegajo !== (int) $decoded['legajo'] || $idLegajo <= 0) {
            abort(404);
        }

        $key = 'mora-estado-deuda-estudiante-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = EstadoDeudaEstudianteDatos::paraEstudiante($idLegajo);
        if ($datos === null) {
            abort(404);
        }

        $slug = Str::slug(
            'estado-deuda-'.trim((string) ($datos['familiaEtiqueta'] ?? 'estudiante')),
            '_',
        );
        if ($slug === '') {
            $slug = 'estado_deuda_estudiante';
        }

        $pdf = EstadoDeudaFamiliarTcpdf::generar($datos);

        return EstadoDeudaFamiliarTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
