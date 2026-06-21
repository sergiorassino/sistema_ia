<?php

namespace App\Http\Controllers\Cuotas;

use App\Http\Controllers\Controller;
use App\Models\CuotaPago;
use App\Support\Cuotas\ComprobantePagoImputacionDatos;
use App\Support\Cuotas\ComprobantePagoImputacionTcpdf;
use App\Support\Cuotas\GestionAranceles;
use App\Support\PermisosCuotas;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Comprobante de pago PDF tras imputación manual — Gestión de aranceles.
 */
class ComprobantePagoImputacionPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $decodedMulti = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_ADMIN_COMPROBANTE_PAGO_IMPUTACION_MULTI);
        if ($decodedMulti !== null) {
            return $this->respuestaMulti($decodedMulti, $request);
        }

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_ADMIN_COMPROBANTE_PAGO_IMPUTACION);
        if ($decoded === null) {
            abort(404);
        }

        $idLegajo = (int) $decoded['legajo'];
        if (GestionAranceles::legajoParaGestion($idLegajo) === null) {
            abort(404);
        }

        $key = 'cuotas-comprobante-imputacion-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $pago = CuotaPago::query()
            ->with(['tipoPago:id,tipoPago'])
            ->find((int) $decoded['id']);

        if ($pago === null) {
            abort(404);
        }

        $idCuotaGenerada = (int) ($pago->idCuotasGeneradas ?? 0);
        if ($idCuotaGenerada <= 0 || GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo) === null) {
            abort(404);
        }

        $datos = ComprobantePagoImputacionDatos::paraPago($pago, $idLegajo);
        if ($datos === null) {
            abort(404);
        }

        $slug = Str::slug(
            'comprobante-pago-'.trim((string) ($datos['apellidoNombre'] ?? '')).'-'.trim((string) ($datos['cuotaNombre'] ?? '')),
            '_',
        );
        if ($slug === '') {
            $slug = 'comprobante_pago';
        }

        $pdf = ComprobantePagoImputacionTcpdf::generar($datos);

        return ComprobantePagoImputacionTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function respuestaMulti(array $decoded, Request $request)
    {
        $idLegajo = (int) ($decoded['l'] ?? 0);
        if ($idLegajo <= 0 || GestionAranceles::legajoParaGestion($idLegajo) === null) {
            abort(404);
        }

        $idsPagos = $decoded['p'] ?? [];
        if (! is_array($idsPagos) || $idsPagos === []) {
            abort(404);
        }

        $key = 'cuotas-comprobante-imputacion-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $pagos = CuotaPago::query()
            ->with(['tipoPago:id,tipoPago'])
            ->whereIn('id', array_map('intval', $idsPagos))
            ->get()
            ->sortBy(fn (CuotaPago $pago) => array_search((int) $pago->id, array_map('intval', $idsPagos), true))
            ->values();

        if ($pagos->isEmpty()) {
            abort(404);
        }

        foreach ($pagos as $pago) {
            $idCuotaGenerada = (int) ($pago->idCuotasGeneradas ?? 0);
            if ($idCuotaGenerada <= 0 || GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo) === null) {
                abort(404);
            }
        }

        $datos = ComprobantePagoImputacionDatos::paraPagos($pagos, $idLegajo);
        if ($datos === null) {
            abort(404);
        }

        $slug = Str::slug(
            'comprobante-pago-'.trim((string) ($datos['apellidoNombre'] ?? '')).'-varias-cuotas',
            '_',
        );
        if ($slug === '') {
            $slug = 'comprobante_pago';
        }

        $pdf = ComprobantePagoImputacionTcpdf::generar($datos);

        return ComprobantePagoImputacionTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
