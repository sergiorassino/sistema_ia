<?php

namespace App\Http\Controllers\CalificacionesSecundario;

use App\Http\Controllers\Controller;
use App\Support\BoletinesSecundario\BoletinConsultaCalificacionesTcpdf;
use App\Support\ConsultaCalificacionesAlumno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Consulta de calificaciones (secundario) para docentes/secretaría — PDF TCPDF.
 * Misma plantilla y datos que el informe de progreso escolar ({@see ConsultaCalificacionesAlumno}),
 * con marca «SIN VALOR LEGAL» y sin firmas.
 */
class ConsultaCalificacionesSecundarioPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'matricula' => ['required', 'integer', 'min:1'],
        ]);
        $idMatricula = (int) $validated['matricula'];
        $uid = (string) (auth()->id() ?? '');
        $key = 'staff-consulta-calificaciones-sec-pdf:'.$uid.':'.($request->ip() ?? '');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $data = ConsultaCalificacionesAlumno::buildForMatriculaEnContextoEscolar($idMatricula);
        if (! $data['ok']) {
            abort(404, $data['error'] ?? 'No disponible.');
        }

        $slugBase = trim((string) ($data['alumnoLinea'] ?? ''));
        $slug = Str::slug('consulta-calificaciones-secundario-'.$slugBase, '_');
        if ($slug === '') {
            $slug = 'consulta_calificaciones_secundario';
        }

        $pdf = BoletinConsultaCalificacionesTcpdf::generarHoja(
            $data,
            schoolPdfHeaderData(),
            'Consulta de Calificaciones',
            true,
            false,
        );

        return BoletinConsultaCalificacionesTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
