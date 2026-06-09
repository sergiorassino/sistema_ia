<?php

namespace App\Http\Controllers;

use App\Support\Listados\ListadoDocentesConsulta;
use App\Support\Listados\ListadoDocentesExcelExportSpec;
use App\Support\Listados\ListadoDocentesExcelExporter;
use App\Support\Listados\ListadoDocentesExportParams;
use App\Support\PermisosIaCatalog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListadoDocentesExcelController extends Controller
{
    public function __invoke(Request $request, ListadoDocentesExcelExporter $exporter): StreamedResponse
    {
        abort_unless(tienePermiso(PermisosIaCatalog::LEGAJOS_DOCENTES), 403);

        $key = 'listado-docentes-excel:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 120);

        $spec = $this->resolverSpec($request);
        $resultado = $exporter->build(schoolCtx()->terlecAno(), $spec);

        return response()->streamDownload(
            fn () => $exporter->escribirEnSalida($resultado['spreadsheet']),
            $resultado['filename'],
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            ],
        );
    }

    private function resolverSpec(Request $request): ListadoDocentesExcelExportSpec
    {
        $rolesInput = $request->query('roles');
        if ($rolesInput === null || $rolesInput === '') {
            return new ListadoDocentesExcelExportSpec;
        }

        $validated = Validator::make(
            [
                'roles' => $rolesInput,
                'campos' => $request->query('campos'),
            ],
            [
                'roles' => ['required', 'string', 'max:2000'],
                'campos' => ['nullable', 'string', 'max:12000'],
            ]
        );

        if ($validated->fails()) {
            abort(404);
        }

        $data = $validated->validated();

        $rolesPermitidos = ListadoDocentesConsulta::rolesDisponibles();
        if ($rolesPermitidos->isEmpty()) {
            abort(404);
        }

        $allowedById = $rolesPermitidos->keyBy(fn ($r) => (int) $r->id);
        $roleIds = ListadoDocentesExportParams::resolverIdsRoles(trim((string) $data['roles']), $allowedById);
        if ($roleIds === []) {
            abort(404);
        }

        $camposRaw = isset($data['campos']) && is_string($data['campos']) ? $data['campos'] : '';
        $pedidos = array_filter(array_map('trim', explode(',', $camposRaw)));
        $campos = ListadoDocentesExportParams::normalizarCamposSeleccion($pedidos);

        return new ListadoDocentesExcelExportSpec(
            roleIds: $roleIds,
            campoKeys: $campos,
        );
    }
}
