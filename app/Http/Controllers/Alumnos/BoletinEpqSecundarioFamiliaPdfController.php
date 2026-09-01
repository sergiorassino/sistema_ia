<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Alumnos\PortalFamiliaBoletinEpqSecundario;
use App\Support\CalificacionesSecundario\Epq\BoletinEpqSecundarioDatos;
use App\Support\CalificacionesSecundario\Epq\BoletinEpqSecundarioTcpdf;
use App\Support\EntoVerNotasOff;
use App\Support\NivelSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Informe de calificaciones EPQ secundario — portal familia.
 */
class BoletinEpqSecundarioFamiliaPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(PortalFamiliaBoletinEpqSecundario::habilitadoEnMenu(), 404);

        if ($bloqueo = EntoVerNotasOff::respuestaPdfSiConsultaBloqueada()) {
            return $bloqueo;
        }

        abort_unless(
            NivelSistema::esSecundario((int) studentCtx()->idNivel),
            403,
            'Este informe corresponde al nivel secundario.'
        );

        $key = 'alumnos-boletin-sec-epq-pdf:'.(auth('alumno')->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $built = BoletinEpqSecundarioDatos::buildDatosParaAlumno();
        if (! ($built['ok'] ?? false)) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => $built['error'] ?? 'No hay datos disponibles para este ciclo lectivo.',
            ], 422);
        }

        $datos = $built['data'];
        $pdf = BoletinEpqSecundarioTcpdf::generar($datos);

        $slug = Str::slug('informe_calificaciones_'.($datos['apellido'] ?? '').'_'.($datos['nombre'] ?? ''), '_');

        return BoletinEpqSecundarioTcpdf::respuestaHttp($pdf, ($slug !== '' ? $slug : 'informe_calificaciones').'.pdf');
    }
}
