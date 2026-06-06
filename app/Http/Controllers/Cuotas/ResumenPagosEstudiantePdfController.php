<?php

namespace App\Http\Controllers\Cuotas;

use App\Http\Controllers\Controller;
use App\Support\Cuotas\GestionAranceles;
use App\Support\Cuotas\ResumenPagosEstudianteDatos;
use App\Support\Cuotas\ResumenPagosEstudianteTcpdf;
use App\Support\PermisosCuotas;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF «Resumen de pagos» del estudiante — Gestión de aranceles.
 */
class ResumenPagosEstudiantePdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_ADMIN_RESUMEN_PAGOS);
        if ($decoded === null) {
            abort(404);
        }

        $idLegajo = (int) $decoded['legajo'];
        if ($idLegajo !== (int) $decoded['id'] || GestionAranceles::legajoParaGestion($idLegajo) === null) {
            abort(404);
        }

        $key = 'cuotas-resumen-pagos-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = ResumenPagosEstudianteDatos::paraLegajo($idLegajo);
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
