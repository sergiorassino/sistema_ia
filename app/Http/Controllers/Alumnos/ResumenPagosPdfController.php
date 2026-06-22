<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Cuotas\ResumenPagosEstudianteDatos;
use App\Support\Cuotas\ResumenPagosEstudianteTcpdf;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF «Resumen de pagos» — portal familia (aranceles escolares).
 */
class ResumenPagosPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(tenantAutogestionArancelesEscolaresHabilitada(), 404);

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_ALUMNOS_RESUMEN_PAGOS);
        if ($decoded === null) {
            abort(404);
        }

        $ctx = studentCtx();
        if (! $ctx->isValid() || $decoded['legajo'] !== (int) $ctx->idLegajo) {
            abort(404);
        }

        $key = 'alumnos-resumen-pagos-pdf:'.(auth('alumno')->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = ResumenPagosEstudianteDatos::paraAutogestion();
        if ($datos === null) {
            abort(404);
        }

        $slug = Str::slug(
            'resumen-pagos-'.trim((string) ($datos['apellidoNombre'] ?? '')),
            '_',
        );
        if ($slug === '') {
            $slug = 'resumen_pagos';
        }

        $pdf = ResumenPagosEstudianteTcpdf::generar($datos);

        return ResumenPagosEstudianteTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
