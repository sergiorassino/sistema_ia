<?php

namespace App\Http\Controllers;

use App\Support\Listados\ListadoDocentesConsulta;
use App\Support\Listados\ListadoDocentesExportParams;
use App\Support\Listados\ListadoDocentesPdfFieldCatalog;
use App\Support\Listados\ListadoDocentesTcpdf;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ListadoDocentesPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(puedeConsultarLegajosDocentes(), 403);

        $key = 'listado-docentes-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = Validator::make(
            [
                'roles' => $request->query('roles'),
                'campos' => $request->query('campos'),
                'subtitulo' => $request->query('subtitulo'),
            ],
            [
                'roles' => ['required', 'string', 'max:2000'],
                'campos' => ['nullable', 'string', 'max:12000'],
                'subtitulo' => ['nullable', 'string', 'max:200'],
            ]
        );

        if ($validated->fails()) {
            abort(404);
        }

        $data = $validated->validated();
        $rolesParam = trim((string) $data['roles']);
        $camposRaw = isset($data['campos']) && is_string($data['campos']) ? $data['campos'] : '';

        $rolesPermitidos = ListadoDocentesConsulta::rolesDisponibles();
        if ($rolesPermitidos->isEmpty()) {
            abort(404);
        }

        $allowedById = $rolesPermitidos->keyBy(fn ($r) => (int) $r->id);
        $roleIds = ListadoDocentesExportParams::resolverIdsRoles($rolesParam, $allowedById);
        if ($roleIds === []) {
            abort(404);
        }

        $pedidos = array_filter(array_map('trim', explode(',', $camposRaw)));
        $campos = ListadoDocentesExportParams::normalizarCamposSeleccion($pedidos);

        $idNivel = ListadoDocentesConsulta::idNivelLegajos();
        if ($idNivel < 1) {
            abort(404);
        }

        $select = array_merge(
            ['profesores.id as __id_profesor', 'profesores.IdTipoProf as __id_tipo_prof'],
            ListadoDocentesPdfFieldCatalog::selectExpressions($campos)
        );

        $query = DB::table('profesores')
            ->where('profesores.nivel', $idNivel)
            ->where(function ($w) use ($roleIds) {
                $w->whereIn('profesores.IdTipoProf', $roleIds);
                if (ListadoDocentesConsulta::incluyeSinRolEnRoles($roleIds)) {
                    $w->orWhereNull('profesores.IdTipoProf');
                }
            })
            ->orderBy('profesores.apellido')
            ->orderBy('profesores.nombre');

        if (ListadoDocentesPdfFieldCatalog::needsProfesorTipoJoin($campos)) {
            $query->leftJoin('profesortipo', 'profesortipo.id', '=', 'profesores.IdTipoProf');
        }

        $camposVisibles = ListadoDocentesPdfFieldCatalog::fusionarApellidoNombre($campos);
        $columnasMeta = ListadoDocentesPdfFieldCatalog::columnsForPdf($campos);

        $docentes = $query->select($select)->get();

        $rolesResumen = ListadoDocentesConsulta::etiquetaRolesSeleccionados($rolesPermitidos, $roleIds);

        $nivelNombre = SchoolAlcancePedagogico::etiquetaNivelParaInformes();
        $ano = schoolCtx()->terlecAno();

        $slug = Str::slug('listado-docentes-'.($ano ?? ''), '_');
        if ($slug === '') {
            $slug = 'listado_docentes';
        }

        $subtitulo = ListadoDocentesExportParams::normalizarSubtitulo($data['subtitulo'] ?? null);

        $pdf = ListadoDocentesTcpdf::generar([
            'docentes' => $docentes,
            'rolesResumen' => $rolesResumen,
            'nivelNombre' => $nivelNombre,
            'ano' => $ano,
            'subtitulo' => $subtitulo,
            'columnasMeta' => $columnasMeta,
            'campos' => $camposVisibles,
            'apaisado' => count($camposVisibles) > 7,
            'pdfHeader' => schoolPdfHeaderData(),
        ]);

        return ListadoDocentesTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
