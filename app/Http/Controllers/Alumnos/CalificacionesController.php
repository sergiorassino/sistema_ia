<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Alumnos\PortalFamiliaBoletinIpe;
use App\Support\BoletinesSecundario\BoletinConsultaCalificacionesTcpdf;
use App\Support\ConsultaCalificacionesAlumno;
use App\Support\EntoVerNotasOff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Consulta de calificaciones del portal alumno — PDF TCPDF (A4 apaisado).
 * Misma plantilla que el informe institucional; marca «SIN VALOR LEGAL» y sin firmas.
 */
class CalificacionesController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(PortalFamiliaBoletinIpe::consultaSecundariaVisible(), 404);

        if ($bloqueo = EntoVerNotasOff::respuestaPdfSiConsultaBloqueada()) {
            return $bloqueo;
        }

        $key = 'alumnos-consulta-calificaciones-pdf:'.(auth('alumno')->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $data = ConsultaCalificacionesAlumno::build();
        if (! $data['ok']) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => $data['error'] ?? 'No hay datos disponibles para este ciclo lectivo.',
            ], 422);
        }

        $slugBase = trim((string) ($data['alumnoLinea'] ?? ''));
        $slug = Str::slug('consulta-calificaciones-'.$slugBase, '_');
        if ($slug === '') {
            $slug = 'consulta_calificaciones';
        }

        $pdf = BoletinConsultaCalificacionesTcpdf::generarHoja(
            $data,
            studentPdfHeaderData(),
            'Consulta de Calificaciones',
            true,
            false,
        );

        return BoletinConsultaCalificacionesTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
