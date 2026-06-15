<?php

namespace App\Http\Controllers\Cooperadora;

use App\Http\Controllers\Controller;
use App\Support\Cooperadora\PagosEstudianteCooperadoraDatos;
use App\Support\Cooperadora\PagosEstudianteCooperadoraTcpdf;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PagosEstudianteCooperadoraPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosCooperadora::puedeIngresos(), 403);

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_COOP_PAGOS_ESTUDIANTE);
        if ($decoded === null) {
            abort(404);
        }

        $idLegajo = (int) $decoded['legajo'];
        if ($idLegajo !== (int) $decoded['id']) {
            abort(404);
        }

        $key = 'coop:pagos-estudiante-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = PagosEstudianteCooperadoraDatos::paraLegajo($idLegajo);
        if ($datos === null) {
            abort(404);
        }

        $slug = Str::slug(
            'pagos-cooperadora-'.trim((string) ($datos['apellidoNombre'] ?? '')),
            '_',
        );
        if ($slug === '') {
            $slug = 'pagos_cooperadora';
        }

        $pdf = PagosEstudianteCooperadoraTcpdf::generar($datos);

        return PagosEstudianteCooperadoraTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
