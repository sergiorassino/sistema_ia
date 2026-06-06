<?php

namespace App\Http\Controllers;

use App\Support\Listados\EstudiantesDatosExporter;
use App\Support\Navegacion\MenuSecretariaPerfil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EstudiantesDatosExcelController extends Controller
{
    public function __invoke(Request $request, EstudiantesDatosExporter $exporter): StreamedResponse
    {
        MenuSecretariaPerfil::abortSiNoViajesSalidasEducativas();

        $key = 'estudiantes-datos-xlsx:'.(auth()->id() ?? $request->ip());
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

        $resultado = $exporter->buildXlsx($matriculaIds);

        return response()->streamDownload(
            fn () => $exporter->escribirEnSalida($resultado['spreadsheet']),
            $resultado['filename'],
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            ],
        );
    }
}
