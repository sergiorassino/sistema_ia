<?php

namespace App\Http\Controllers\Cuotas;

use App\Http\Controllers\Controller;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionPlanillaDatos;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionPlanillaTcpdf;
use App\Support\PermisosCuotas;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF de planilla de descarga SIRO — Medios de pago (Administración).
 */
class SiroDescargaRendicionPlanillaPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosCuotas::puedeSiroDescargaRendicion(), 403);

        $decoded = OpaqueRouteToken::decodePayload(
            $ref,
            OpaqueRouteToken::PURPOSE_ADMIN_SIRO_DESCARGA_PLANILLA,
        );
        if ($decoded === null) {
            abort(404);
        }

        $nroPlanilla = (int) ($decoded['n'] ?? 0);
        if ($nroPlanilla < 1) {
            abort(404);
        }

        @ini_set('memory_limit', '512M');

        $key = 'siro-descarga-planilla-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = SiroDescargaRendicionPlanillaDatos::build($nroPlanilla);
        if ($datos === null) {
            abort(404);
        }

        $slug = Str::slug('planilla-siro-'.$datos['nroPlanillaEtiqueta'], '_');
        if ($slug === '') {
            $slug = 'planilla_siro';
        }

        $pdf = SiroDescargaRendicionPlanillaTcpdf::generar($datos);

        return SiroDescargaRendicionPlanillaTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
