<?php

namespace App\Http\Controllers\Horarios;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Support\Horarios\HorarioCursoPdfExport;
use App\Support\Listados\ListadoCursoExportParams;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class HorarioCursoPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $key = 'horario-curso-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429);
        }
        RateLimiter::hit($key, 60);

        $cursosInput = $request->query('cursos');
        if (($cursosInput === null || $cursosInput === '') && $request->filled('curso')) {
            $cursosInput = (string) (int) $request->query('curso');
        }

        $validated = Validator::make(
            ['cursos' => $cursosInput],
            ['cursos' => ['required', 'string', 'max:8000']],
        )->validate();

        $ctx = schoolCtx();
        $cursosPermitidos = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);

        if ($cursosPermitidos->isEmpty()) {
            abort(404);
        }

        $allowedById = $cursosPermitidos->keyBy(fn (Curso $c) => (int) $c->Id);
        $cursoIds = ListadoCursoExportParams::resolverIdsCursos(trim((string) $validated['cursos']), $allowedById);

        if ($cursoIds === []) {
            abort(404);
        }

        $subtitulo = schoolCtx()->nivelNombre().' · Ciclo '.schoolCtx()->terlecAno();

        return HorarioCursoPdfExport::stream(
            idNivel: (int) $ctx->idNivel,
            idTerlec: (int) $ctx->idTerlec,
            cursoIds: $cursoIds,
            forzadoTurno: (int) $request->query('turno', 0),
            pdfHeader: schoolPdfHeaderData(),
            subtituloNivelCiclo: $subtitulo,
        );
    }
}
