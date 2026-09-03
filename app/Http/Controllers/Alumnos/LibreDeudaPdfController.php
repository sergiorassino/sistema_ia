<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Alumnos\LibreDeudaDatos;
use App\Support\Alumnos\LibreDeudaTcpdf;
use App\Support\Aulica\AulicaDeudaConsulta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Constancia de libre deuda (PDF) para el alumno/familia en sesión.
 * Solo se emite si Áulica no registra deuda del estudiante ni del grupo familiar.
 */
class LibreDeudaPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(tenantAutogestionLibreDeudaHabilitada(), 404);

        $key = 'alumnos-libre-deuda-pdf:'.(auth('alumno')->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = LibreDeudaDatos::paraAutogestion();
        if ($datos === null) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => 'No hay matrícula registrada para este ciclo lectivo. Contacte a secretaría.',
            ], 422);
        }

        $deuda = (new AulicaDeudaConsulta)->paraEstudianteActual();
        if (! $deuda->consultaOk) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => $deuda->error !== ''
                    ? $deuda->error
                    : 'No se pudo consultar la deuda en Áulica. Intente más tarde.',
            ], 503);
        }

        if ($deuda->dniEstudiante === '' && $deuda->dniResponsable === '') {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => 'No se pudo verificar la deuda en Áulica. El legajo no tiene DNI o el servicio no está disponible. Contacte a secretaría.',
            ], 503);
        }

        if ($deuda->tieneDeuda()) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => $deuda->mensajeVisible(),
            ], 403);
        }

        $slug = Str::slug(
            'libre-deuda-'.trim($datos['apellido'].'-'.$datos['nombre']),
            '_',
        );
        if ($slug === '') {
            $slug = 'constancia_libre_deuda';
        }

        return LibreDeudaTcpdf::respuestaHttp(
            LibreDeudaTcpdf::generar($datos),
            $slug.'.pdf',
        );
    }
}
