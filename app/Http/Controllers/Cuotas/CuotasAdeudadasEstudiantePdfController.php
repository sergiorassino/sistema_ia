<?php

namespace App\Http\Controllers\Cuotas;

use App\Http\Controllers\Controller;
use App\Support\Cuotas\CuotasAdeudadasEstudianteDatos;
use App\Support\Cuotas\CuotasAdeudadasEstudianteTcpdf;
use App\Support\Cuotas\GestionAranceles;
use App\Support\PermisosCuotas;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF «Cuotas adeudadas» del estudiante — Gestión de aranceles (Administración).
 */
class CuotasAdeudadasEstudiantePdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_ADMIN_CUOTAS_ADEUDADAS);
        if ($decoded === null) {
            abort(404);
        }

        $idLegajo = (int) $decoded['legajo'];
        if ($idLegajo !== (int) $decoded['id'] || GestionAranceles::legajoParaGestion($idLegajo) === null) {
            abort(404);
        }

        $key = 'cuotas-adeudadas-estudiante-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = CuotasAdeudadasEstudianteDatos::paraAdministracion($idLegajo);
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
