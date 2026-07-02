<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Alumnos\FichaMatriculaConAceptacionTcpdf;
use App\Support\Alumnos\FichaMatriculaDatos;
use App\Support\Alumnos\FichaMatriculaMontecristoDatos;
use App\Support\Alumnos\FichaMatriculaSanJoseTcpdf;
use App\Support\Alumnos\FichaMatriculaSolicitudMontecristoTcpdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Ficha de matrícula en PDF para el alumno/familia en sesión (ciclo de autogestión).
 */
class FichaMatriculaPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(tenantAutogestionFichaMatriculaHabilitada(), 404);

        $key = 'alumnos-ficha-matricula-pdf:'.(auth('alumno')->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $implementacion = (string) config('tenant.autogestion.ficha_matricula.implementacion', '');

        $datos = match ($implementacion) {
            'sanfranciscoasis' => FichaMatriculaDatos::paraAutogestion(),
            'montecristo', 'sanjose' => FichaMatriculaMontecristoDatos::paraAutogestion(),
            default => null,
        };

        if ($datos === null) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => 'No hay matrícula registrada para este ciclo lectivo. Contacte a secretaría.',
            ], 422);
        }

        $slug = Str::slug(
            'ficha-matricula-'.trim($datos['apellido'].'-'.$datos['nombre']),
            '_',
        );
        if ($slug === '') {
            $slug = in_array($implementacion, ['montecristo', 'sanjose'], true)
                ? 'ficha_solicitud_matricula'
                : 'ficha_matricula';
        }

        return match ($implementacion) {
            'sanfranciscoasis' => FichaMatriculaConAceptacionTcpdf::respuestaHttp(
                FichaMatriculaConAceptacionTcpdf::generar(
                    $datos,
                    $datos['header'] ?? studentPdfHeaderData(),
                ),
                $slug.'.pdf',
            ),
            'montecristo' => FichaMatriculaSolicitudMontecristoTcpdf::respuestaHttp(
                FichaMatriculaSolicitudMontecristoTcpdf::generar($datos),
                $slug.'.pdf',
            ),
            'sanjose' => FichaMatriculaSanJoseTcpdf::respuestaHttp(
                FichaMatriculaSanJoseTcpdf::generar($datos),
                $slug.'.pdf',
            ),
            default => abort(404),
        };
    }
}
