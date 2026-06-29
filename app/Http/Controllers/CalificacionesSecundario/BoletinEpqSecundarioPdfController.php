<?php

namespace App\Http\Controllers\CalificacionesSecundario;

use App\Http\Controllers\Controller;
use App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos;
use App\Support\CalificacionesSecundario\Epq\BoletinEpqSecundarioDatos;
use App\Support\CalificacionesSecundario\Epq\BoletinEpqSecundarioTcpdf;
use App\Support\CalificacionesSecundario\Epq\CalificacionesEpqSecundarioCatalogo;
use App\Support\NivelSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Informe de calificaciones EPQ secundario — una matrícula.
 */
class BoletinEpqSecundarioPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        CalificacionesSecundarioModulos::abortSiImplementacionInactiva(
            CalificacionesSecundarioModulos::BOLETIN,
            CalificacionesEpqSecundarioCatalogo::IMPLEMENTACION,
        );

        abort_unless(
            NivelSistema::esSecundario((int) schoolCtx()->idNivel),
            403,
            'Este informe corresponde al nivel secundario.',
        );

        $validated = $request->validate([
            'matricula' => ['required', 'integer', 'min:1'],
        ]);

        $idMatricula = (int) $validated['matricula'];
        $uid = (string) (auth()->id() ?? '');
        $key = 'boletin-epq-secundario-pdf:'.$uid.':'.($request->ip() ?? '');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $built = BoletinEpqSecundarioDatos::buildForMatriculaEnContexto($idMatricula);
        if (! ($built['ok'] ?? false)) {
            abort(404, $built['error'] ?? 'No disponible.');
        }

        $datos = $built['data'];
        $pdf = BoletinEpqSecundarioTcpdf::generar($datos);

        $slug = Str::slug('informe_calificaciones_'.($datos['apellido'] ?? '').'_'.($datos['nombre'] ?? ''), '_');

        return BoletinEpqSecundarioTcpdf::respuestaHttp($pdf, ($slug !== '' ? $slug : 'informe_calificaciones').'.pdf');
    }
}
