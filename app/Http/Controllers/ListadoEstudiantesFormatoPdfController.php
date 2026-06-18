<?php

namespace App\Http\Controllers;

use App\Support\Listados\ListadoCursoConsulta;
use App\Support\Listados\ListadoCursoExportParams;
use App\Support\Listados\ListadoEstudiantesFormatoCalendarioTcpdf;
use App\Support\Listados\ListadoEstudiantesFormatoCatalog;
use App\Support\Listados\ListadoEstudiantesFormatoCuadriculadoTcpdf;
use App\Support\Listados\ListadoEstudiantesFormatoDatos;
use App\Support\Listados\ListadoEstudiantesFormatoMes;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ListadoEstudiantesFormatoPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $key = 'listado-estudiantes-formato-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = Validator::make(
            [
                'cursos' => $request->query('cursos'),
                'modelo' => $request->query('modelo'),
                'mes' => $request->query('mes'),
                'ano' => $request->query('ano'),
            ],
            [
                'cursos' => ['required', 'string', 'max:8000'],
                'modelo' => ['required', 'string', Rule::in(ListadoEstudiantesFormatoCatalog::keys())],
                'mes' => ['nullable', 'integer', 'min:1', 'max:12'],
                'ano' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            ]
        );

        if ($validated->fails()) {
            abort(404);
        }

        $data = $validated->validated();
        $modelo = ListadoEstudiantesFormatoCatalog::normalize($data['modelo']);

        $cursosPermitidos = ListadoCursoConsulta::cursosPermitidosEnContexto();
        if ($cursosPermitidos->isEmpty()) {
            abort(404);
        }

        $allowedById = $cursosPermitidos->keyBy(fn ($c) => (int) $c->Id);
        $cursoIds = ListadoCursoExportParams::resolverIdsCursos(trim((string) $data['cursos']), $allowedById);
        if ($cursoIds === []) {
            abort(404);
        }

        $ctx = schoolCtx();
        $ano = ListadoEstudiantesFormatoMes::normalizarAno($data['ano'] ?? null);
        if ($ano < 1) {
            $ano = (int) ($ctx->terlecAno() ?? 0);
        }

        $mes = ListadoEstudiantesFormatoMes::normalizarMes($data['mes'] ?? null);
        if (ListadoEstudiantesFormatoCatalog::requiereMes($modelo) && $mes < 1) {
            abort(404);
        }

        $datosPdf = ListadoEstudiantesFormatoDatos::contextoPdf($cursoIds);
        $datosPdf['mes'] = $mes;
        $datosPdf['ano'] = $ano;

        $pdf = match ($modelo) {
            ListadoEstudiantesFormatoCatalog::MODELO_CALENDARIO => ListadoEstudiantesFormatoCalendarioTcpdf::generar($datosPdf),
            default => ListadoEstudiantesFormatoCuadriculadoTcpdf::generar($datosPdf),
        };

        $slugPartes = ['listado-estudiantes-formato', $modelo];
        if ($modelo === ListadoEstudiantesFormatoCatalog::MODELO_CALENDARIO && $mes > 0) {
            $slugPartes[] = $mes;
        }
        if ($ano > 0) {
            $slugPartes[] = $ano;
        }

        $slug = Str::slug(implode('-', $slugPartes), '_');
        if ($slug === '') {
            $slug = 'listado_estudiantes_formato';
        }

        $clasePdf = $modelo === ListadoEstudiantesFormatoCatalog::MODELO_CALENDARIO
            ? ListadoEstudiantesFormatoCalendarioTcpdf::class
            : ListadoEstudiantesFormatoCuadriculadoTcpdf::class;

        return $clasePdf::formatoRespuestaHttp($pdf, $slug.'.pdf');
    }
}
