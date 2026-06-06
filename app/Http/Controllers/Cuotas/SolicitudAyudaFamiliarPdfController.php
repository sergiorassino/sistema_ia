<?php

namespace App\Http\Controllers\Cuotas;

use App\Http\Controllers\Controller;
use App\Support\Cuotas\GestionAranceles;
use App\Support\Cuotas\SolicitudAyudaFamiliarDatos;
use App\Support\Cuotas\SolicitudAyudaFamiliarTcpdf;
use App\Support\PermisosCuotas;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF «Solicitud de Ayuda Familiar» — Menú de Administración (becas).
 */
class SolicitudAyudaFamiliarPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosCuotas::puedeSolicitudAyudaFamiliar(), 403);

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_ADMIN_SOLICITUD_AYUDA_FAMILIAR);
        if ($decoded === null) {
            abort(404);
        }

        $idLegajo = (int) $decoded['legajo'];
        $nro = (int) $decoded['id'];
        if ($nro < 1 || GestionAranceles::legajoParaGestion($idLegajo) === null) {
            abort(404);
        }

        $key = 'cuotas-solicitud-ayuda-familiar-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = SolicitudAyudaFamiliarDatos::paraPdf($idLegajo, $nro);
        if ($datos === null) {
            abort(404);
        }

        $slug = Str::slug('solicitud-ayuda-familiar-'.$nro, '_');
        if ($slug === '') {
            $slug = 'solicitud_ayuda_familiar';
        }

        $pdf = SolicitudAyudaFamiliarTcpdf::generar($datos);

        return SolicitudAyudaFamiliarTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
