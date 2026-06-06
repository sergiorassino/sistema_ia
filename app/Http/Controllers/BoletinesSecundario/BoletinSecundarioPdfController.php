<?php

namespace App\Http\Controllers\BoletinesSecundario;

use App\Http\Controllers\Controller;
use App\Support\BoletinesSecundario\BoletinConsultaCalificacionesTcpdf;
use App\Support\ConsultaCalificacionesAlumno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Informe de progreso escolar (boletín oficial, secundario).
 * Misma grilla que la consulta de calificaciones, sin marca de agua y con firmas.
 */
class BoletinSecundarioPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'matricula' => ['required', 'integer', 'min:1'],
        ]);
        $idMatricula = (int) $validated['matricula'];
        $uid = (string) (auth()->id() ?? '');
        $key = 'staff-boletin-secundario-pdf:'.$uid.':'.($request->ip() ?? '');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $data = ConsultaCalificacionesAlumno::buildForMatriculaEnContextoEscolar($idMatricula);
        if (! $data['ok']) {
            abort(404, $data['error'] ?? 'No disponible.');
        }

        $slugBase = trim((string) ($data['alumnoLinea'] ?? ''));
        $slug = Str::slug('informe-progreso-escolar-'.$slugBase, '_');
        if ($slug === '') {
            $slug = 'informe_progreso_escolar';
        }

        $pdf = BoletinConsultaCalificacionesTcpdf::generarHoja(
            $data,
            schoolPdfHeaderData(),
            'Informe de Progreso Escolar',
            false,
            true,
        );

        return BoletinConsultaCalificacionesTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
