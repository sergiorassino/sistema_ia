<?php

namespace App\Http\Controllers;

use App\Support\Listados\EstudiantesDatosConsulta;
use App\Support\Listados\EstudiantesDatosExporter;
use App\Support\Listados\EstudiantesDatosTcpdf;
use App\Support\Navegacion\MenuSecretariaPerfil;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class EstudiantesDatosPdfController extends Controller
{
    public function __invoke(Request $request, EstudiantesDatosExporter $exporter)
    {
        MenuSecretariaPerfil::abortSiNoViajesSalidasEducativas();

        $key = 'estudiantes-datos-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 120);

        $ctx = schoolCtx();
        if ((int) $ctx->idNivel <= 0 || (int) $ctx->idTerlec <= 0) {
            abort(403);
        }

        $validated = Validator::make(
            ['matriculas' => $request->query('matriculas')],
            ['matriculas' => ['required', 'string', 'max:12000']],
        )->validate();

        $matriculaIds = collect(explode(',', trim((string) $validated['matriculas'])))
            ->map(fn ($v) => (int) trim((string) $v))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($matriculaIds === []) {
            abort(404);
        }

        $filas = $exporter->filas($matriculaIds);
        if ($filas->isEmpty()) {
            abort(404);
        }

        $pdf = EstudiantesDatosTcpdf::generar([
            'filas' => $filas,
            'exporter' => $exporter,
            'nivelNombre' => SchoolAlcancePedagogico::etiquetaNivelParaInformes(),
            'ano' => $ctx->terlecAno(),
            'totalAlumnos' => $filas->count(),
            'pdfHeader' => schoolPdfHeaderData(),
        ]);

        return EstudiantesDatosTcpdf::respuestaHttp(
            $pdf,
            EstudiantesDatosConsulta::nombreArchivoPdf(),
        );
    }
}
