<?php

namespace App\Http\Controllers;

use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\Listados\ListadoCursoConsulta;
use App\Support\Listados\ListadoCursoExportParams;
use App\Support\Listados\ListadoCursoPdfFieldCatalog;
use App\Support\Listados\ListadoCursoTcpdf;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ListadoCursoPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $key = 'listado-curso-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $cursosInput = $request->query('cursos');
        if (($cursosInput === null || $cursosInput === '') && $request->filled('curso')) {
            $cursosInput = (string) (int) $request->query('curso');
        }

        $validated = Validator::make(
            [
                'cursos' => $cursosInput,
                'campos' => $request->query('campos'),
                'condicion' => $request->query('condicion'),
                'subtitulo' => $request->query('subtitulo'),
            ],
            [
                'cursos' => ['required', 'string', 'max:8000'],
                'campos' => ['nullable', 'string', 'max:12000'],
                'condicion' => ['nullable', 'string', Rule::in(ListadoCursoCondicionFiltro::keys())],
                'subtitulo' => ['nullable', 'string', 'max:200'],
            ]
        );

        if ($validated->fails()) {
            abort(404);
        }

        $data = $validated->validated();
        $cursosParam = trim((string) $data['cursos']);
        $camposRaw = isset($data['campos']) && is_string($data['campos']) ? $data['campos'] : '';
        $filtroCondicion = ListadoCursoCondicionFiltro::normalize($data['condicion'] ?? null);

        $ctx = schoolCtx();

        $cursosPermitidos = ListadoCursoConsulta::cursosPermitidosEnContexto();

        if ($cursosPermitidos->isEmpty()) {
            abort(404);
        }

        $allowedById = $cursosPermitidos->keyBy(fn ($c) => (int) $c->Id);

        $cursoIds = ListadoCursoExportParams::resolverIdsCursos($cursosParam, $allowedById);
        if ($cursoIds === []) {
            abort(404);
        }

        $pedidos = array_filter(array_map('trim', explode(',', $camposRaw)));
        $campos = ListadoCursoExportParams::normalizarCamposSeleccion($pedidos, $filtroCondicion);

        $select = array_merge(
            ['matricula.idCursos as __id_curso'],
            ListadoCursoPdfFieldCatalog::selectExpressions($campos)
        );

        $idsCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery($filtroCondicion);

        $query = DB::table('matricula')
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->whereIn('matricula.idCursos', $cursoIds)
            ->whereIn('matricula.idCondiciones', $idsCondiciones)
            ->where('matricula.idTerlec', $ctx->idTerlec)
            ->whereNull('matricula.fechaBaja');

        ListadoCursoConsulta::aplicarFiltroMatriculaNivel($query);

        $query
            ->orderBy('matricula.idCursos')
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre');

        if (ListadoCursoPdfFieldCatalog::needsCondicionesJoin($campos)) {
            $query->leftJoin('condiciones', 'condiciones.id', '=', 'matricula.idCondiciones');
        }

        $camposVisibles = ListadoCursoPdfFieldCatalog::fusionarApellidoNombre($campos);
        $columnasMeta = ListadoCursoPdfFieldCatalog::columnsForPdf($campos);

        $filas = $query->select($select)->get();
        $porCurso = $filas->groupBy(fn ($r) => (int) $r->__id_curso);

        $bloques = [];
        foreach ($cursosPermitidos as $c) {
            if (! in_array((int) $c->Id, $cursoIds, true)) {
                continue;
            }
            $bloques[] = [
                'cursoLabel' => $c->nombreParaListado(),
                'alumnos' => $porCurso->get((int) $c->Id, collect()),
            ];
        }

        $nivelNombre = SchoolAlcancePedagogico::etiquetaNivelParaInformes();
        $ano = $ctx->terlecAno();

        $modoEstudiantesPdf = ListadoCursoCondicionFiltro::etiquetaModoEstudiantesPdf($filtroCondicion);

        $slug = Str::slug('listado-estudiantes-'.($ano ?? ''), '_');
        if ($slug === '') {
            $slug = 'listado_estudiantes';
        }

        $subtitulo = ListadoCursoExportParams::normalizarSubtitulo($data['subtitulo'] ?? null);

        $pdf = ListadoCursoTcpdf::generar([
            'bloques' => $bloques,
            'modoEstudiantesPdf' => $modoEstudiantesPdf,
            'nivelNombre' => $nivelNombre,
            'ano' => $ano,
            'subtitulo' => $subtitulo,
            'columnasMeta' => $columnasMeta,
            'campos' => $camposVisibles,
            'apaisado' => count($camposVisibles) > 7,
            'pdfHeader' => schoolPdfHeaderData(),
        ]);

        return ListadoCursoTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
