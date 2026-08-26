<?php

namespace App\Http\Controllers\Horarios;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Support\Horarios\ProfesoresPresentesConsulta;
use App\Support\Horarios\ProfesoresPresentesTcpdf;
use App\Support\Listados\ListadoCursoExportParams;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProfesoresPresentesPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $key = 'profesores-presentes-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = Validator::make(
            [
                'dia' => $request->query('dia'),
                'horaInicio' => $request->query('horaInicio'),
                'horaFin' => $request->query('horaFin'),
                'cursos' => $request->query('cursos'),
            ],
            [
                'dia' => ['required', 'integer', 'min:1', 'max:7'],
                'horaInicio' => ['required', 'string', 'max:8'],
                'horaFin' => ['required', 'string', 'max:8'],
                'cursos' => ['required', 'string', 'max:8000'],
            ]
        );

        if ($validated->fails()) {
            abort(404);
        }

        $data = $validated->validated();

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
        $cursoIds = ListadoCursoExportParams::resolverIdsCursos(trim((string) $data['cursos']), $allowedById);
        if ($cursoIds === []) {
            abort(404);
        }

        $datos = ProfesoresPresentesConsulta::consultar(
            (int) $data['dia'],
            (string) $data['horaInicio'],
            (string) $data['horaFin'],
            $cursoIds,
        );

        if (! $datos['ok']) {
            abort(404);
        }

        $subtitulo = trim($ctx->nivelNombre().' · Ciclo '.$ctx->terlecAno());
        $diaSlug = Str::slug((string) ($datos['diaLabel'] ?? 'dia'), '_') ?: 'dia';
        $nombre = 'profesores_presentes_'.$diaSlug.'.pdf';

        $pdf = ProfesoresPresentesTcpdf::generar($datos, schoolPdfHeaderData(), $subtitulo);

        return ProfesoresPresentesTcpdf::respuestaHttp($pdf, $nombre);
    }
}
