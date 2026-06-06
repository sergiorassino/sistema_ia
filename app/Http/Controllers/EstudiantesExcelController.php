<?php

namespace App\Http\Controllers;

use App\Support\Listados\EstudiantesExcelExporter;
use App\Support\Listados\ListadoCursoConsulta;
use App\Support\Listados\EstudiantesExcelExportSpec;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\Listados\ListadoCursoExportParams;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EstudiantesExcelController extends Controller
{
    public function __invoke(Request $request, EstudiantesExcelExporter $exporter): StreamedResponse
    {
        $key = 'estudiantes-excel:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 120);

        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;

        if ($idNivel <= 0 || $idTerlec <= 0) {
            abort(403);
        }

        $spec = $this->resolverSpec($request);

        $resultado = $exporter->build($idTerlec, $ctx->terlecAno(), $spec);

        return response()->streamDownload(
            fn () => $exporter->escribirEnSalida($resultado['spreadsheet']),
            $resultado['filename'],
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            ],
        );
    }

    private function resolverSpec(Request $request): EstudiantesExcelExportSpec
    {
        $cursosInput = $request->query('cursos');
        if ($cursosInput === null || $cursosInput === '') {
            return new EstudiantesExcelExportSpec;
        }

        $validated = Validator::make(
            [
                'cursos' => $cursosInput,
                'campos' => $request->query('campos'),
                'condicion' => $request->query('condicion'),
            ],
            [
                'cursos' => ['required', 'string', 'max:8000'],
                'campos' => ['nullable', 'string', 'max:12000'],
                'condicion' => ['nullable', 'string', Rule::in(ListadoCursoCondicionFiltro::keys())],
            ]
        );

        if ($validated->fails()) {
            abort(404);
        }

        $data = $validated->validated();
        $filtroCondicion = ListadoCursoCondicionFiltro::normalize($data['condicion'] ?? null);

        $cursosPermitidos = ListadoCursoConsulta::cursosPermitidosEnContexto();

        if ($cursosPermitidos->isEmpty()) {
            abort(404);
        }

        $allowedById = $cursosPermitidos->keyBy(fn ($c) => (int) $c->Id);
        $cursoIds = ListadoCursoExportParams::resolverIdsCursos(trim((string) $data['cursos']), $allowedById);

        if ($cursoIds === []) {
            abort(404);
        }

        $camposRaw = isset($data['campos']) && is_string($data['campos']) ? $data['campos'] : '';
        $pedidos = array_filter(array_map('trim', explode(',', $camposRaw)));
        $campos = ListadoCursoExportParams::normalizarCamposSeleccion($pedidos, $filtroCondicion);

        return new EstudiantesExcelExportSpec(
            cursoIds: $cursoIds,
            campoKeys: $campos,
            filtroCondicion: $filtroCondicion,
        );
    }
}
