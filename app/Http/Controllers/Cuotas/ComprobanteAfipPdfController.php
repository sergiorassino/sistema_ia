<?php

namespace App\Http\Controllers\Cuotas;

use App\Http\Controllers\Controller;
use App\Models\CuotaPago;
use App\Support\Cuotas\ComprobanteAfipDatos;
use App\Support\Cuotas\ComprobanteAfipTcpdf;
use App\Support\Cuotas\GestionAranceles;
use App\Support\PermisosCuotas;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Comprobante electrónico AFIP PDF (imputación o reimpresión por registro).
 */
class ComprobanteAfipPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $key = 'cuotas-comprobante-afip-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $decodedReg = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_ADMIN_COMPROBANTE_AFIP_REG);
        if ($decodedReg !== null) {
            $idLegajo = (int) $decodedReg['legajo'];
            if (GestionAranceles::legajoParaGestion($idLegajo) === null) {
                abort(404);
            }

            $datos = ComprobanteAfipDatos::paraComprobanteRegistro((int) $decodedReg['id'], $idLegajo);
            if ($datos === null) {
                abort(404);
            }

            return $this->respuestaPdf($datos);
        }

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_ADMIN_COMPROBANTE_AFIP);
        if ($decoded === null) {
            abort(404);
        }

        $idLegajo = (int) $decoded['legajo'];
        if (GestionAranceles::legajoParaGestion($idLegajo) === null) {
            abort(404);
        }

        $pago = CuotaPago::query()->find((int) $decoded['id']);
        if ($pago === null) {
            abort(404);
        }

        $idCuotaGenerada = (int) ($pago->idCuotasGeneradas ?? 0);
        if ($idCuotaGenerada <= 0 || GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo) === null) {
            abort(404);
        }

        $datos = ComprobanteAfipDatos::paraPago($pago, $idLegajo);
        if ($datos === null) {
            abort(404);
        }

        return $this->respuestaPdf($datos);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function respuestaPdf(array $datos)
    {
        $slug = Str::slug(
            'comprobante-afip-'.trim((string) ($datos['nombreCliente'] ?? '')).'-'.trim((string) ($datos['concepto'] ?? '')),
            '_',
        );
        if ($slug === '') {
            $slug = 'comprobante_afip';
        }

        $pdf = ComprobanteAfipTcpdf::generar($datos);

        return ComprobanteAfipTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
