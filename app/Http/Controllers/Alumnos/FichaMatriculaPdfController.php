<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Alumnos\FichaMatriculaDatos;
use App\Support\Alumnos\FichaMatriculaConAceptacionTcpdf;
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

        $datos = FichaMatriculaDatos::paraAutogestion();
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
            $slug = 'ficha_matricula';
        }

        /** @var array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string} $header */
        $header = $datos['header'] ?? studentPdfHeaderData();

        $pdf = FichaMatriculaConAceptacionTcpdf::generar($datos, $header);

        return FichaMatriculaConAceptacionTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
