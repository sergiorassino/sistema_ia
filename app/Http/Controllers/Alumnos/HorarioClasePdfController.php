<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Horarios\HorarioCursoPdfExport;
use App\Support\InformeInasistencias;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Horario de clase del curso del alumno en el ciclo lectivo de autogestión (solo nivel secundario / medio).
 */
class HorarioClasePdfController extends Controller
{
    public function __invoke(Request $request)
    {
        if (! studentEsNivelSecundario()) {
            abort(403);
        }

        $key = 'alumnos-horario-clase-pdf:'.(auth('alumno')->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            abort(403);
        }

        $matricula = InformeInasistencias::matriculaAutogestion();
        if ($matricula === null || (int) ($matricula->idCursos ?? 0) <= 0) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => 'No hay matrícula registrada para este ciclo lectivo. Contacte a secretaría.',
            ], 422);
        }

        $cursoId = (int) $matricula->idCursos;
        $subtitulo = $ctx->nivelNombre().' · Ciclo '.($ctx->terlecAno() ?? '');

        return HorarioCursoPdfExport::stream(
            idNivel: (int) $ctx->idNivel,
            idTerlec: (int) $ctx->idTerlec,
            cursoIds: [$cursoId],
            forzadoTurno: 0,
            pdfHeader: studentPdfHeaderData(),
            subtituloNivelCiclo: $subtitulo,
        );
    }
}
