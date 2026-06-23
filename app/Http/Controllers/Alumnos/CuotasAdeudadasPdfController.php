<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Cuotas\CuotasAdeudadasEstudianteDatos;
use App\Support\Cuotas\CuotasAdeudadasEstudianteTcpdf;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF «Cuotas adeudadas» — portal familia (aranceles escolares).
 */
class CuotasAdeudadasPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(tenantAutogestionArancelesEscolaresHabilitada(), 404);

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_ALUMNOS_CUOTAS_ADEUDADAS);
        if ($decoded === null) {
            abort(404);
        }

        $ctx = studentCtx();
        if (! $ctx->isValid() || $decoded['legajo'] !== (int) $ctx->idLegajo) {
            abort(404);
        }

        $key = 'alumnos-cuotas-adeudadas-pdf:'.(auth('alumno')->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = CuotasAdeudadasEstudianteDatos::paraAutogestion();
        if ($datos === null) {
            abort(404);
        }

        $slug = Str::slug(
            'cuotas-adeudadas-'.trim((string) ($datos['apellidoNombre'] ?? '')),
            '_',
        );
        if ($slug === '') {
            $slug = 'cuotas_adeudadas';
        }

        $pdf = CuotasAdeudadasEstudianteTcpdf::generar($datos);

        return CuotasAdeudadasEstudianteTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
