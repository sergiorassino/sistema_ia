<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Cuotas\ComprobanteAfipDatos;
use App\Support\Cuotas\ComprobanteAfipTcpdf;
use App\Support\Cuotas\ComprobantesAfipCuotaService;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Comprobante electrónico AFIP PDF — portal familia (aranceles escolares).
 */
class ComprobanteAfipPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(tenantAutogestionArancelesEscolaresHabilitada(), 404);
        abort_unless(ComprobantesAfipCuotaService::moduloDisponible(), 404);

        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            abort(404);
        }

        $datos = $this->datosDesdeReferencia($ref, (int) $ctx->idLegajo);
        if ($datos === null) {
            abort(404);
        }

        $key = 'alumnos-comprobante-afip-pdf:'.(auth('alumno')->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

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

    /**
     * @return array<string, mixed>|null
     */
    private function datosDesdeReferencia(string $ref, int $idLegajoSesion): ?array
    {
        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_ALUMNOS_COMPROBANTE_AFIP_REG);
        if ($payload !== null) {
            $idComprobanteAfip = (int) ($payload['a'] ?? 0);
            $idCuotaGenerada = (int) ($payload['c'] ?? 0);
            $idLegajo = (int) ($payload['l'] ?? 0);

            if ($idComprobanteAfip <= 0 || $idCuotaGenerada <= 0 || $idLegajo <= 0 || $idLegajo !== $idLegajoSesion) {
                return null;
            }

            return ComprobanteAfipDatos::paraAutogestion($idComprobanteAfip, $idCuotaGenerada);
        }

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_ALUMNOS_COMPROBANTE_AFIP_REG);
        if ($decoded === null || $decoded['legajo'] !== $idLegajoSesion) {
            return null;
        }

        return ComprobanteAfipDatos::paraComprobanteRegistro((int) $decoded['id'], $idLegajoSesion);
    }
}
