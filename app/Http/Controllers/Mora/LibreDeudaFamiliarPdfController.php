<?php

namespace App\Http\Controllers\Mora;

use App\Http\Controllers\Controller;
use App\Support\Mora\LibreDeudaFamiliarDatos;
use App\Support\Mora\LibreDeudaFamiliarTcpdf;
use App\Support\Mora\PermisosMora;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Constancia PDF de libre deuda — Gestión de mora.
 */
class LibreDeudaFamiliarPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosMora::puedeLibreDeudaFamiliar(), 403);

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_MORA_LIBRE_DEUDA_FAMILIAR);
        if ($decoded === null) {
            abort(404);
        }

        $idLegajo = (int) $decoded['id'];
        if ($idLegajo !== (int) $decoded['legajo'] || $idLegajo <= 0) {
            abort(404);
        }

        $key = 'mora-libre-deuda-familiar-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = LibreDeudaFamiliarDatos::paraEstudiante($idLegajo);
        if ($datos === null) {
            abort(404);
        }

        if (LibreDeudaFamiliarDatos::tieneDeuda($idLegajo)) {
            abort(403, 'El estudiante registra cuotas pendientes. No se emite la constancia.');
        }

        $slug = Str::slug(
            'libre-deuda-'.trim((string) ($datos['apellido'] ?? '').'-'.(string) ($datos['nombre'] ?? '')),
            '_',
        );
        if ($slug === '') {
            $slug = 'constancia_libre_deuda';
        }

        return LibreDeudaFamiliarTcpdf::respuestaHttp(
            LibreDeudaFamiliarTcpdf::generar($datos),
            $slug.'.pdf',
        );
    }
}
