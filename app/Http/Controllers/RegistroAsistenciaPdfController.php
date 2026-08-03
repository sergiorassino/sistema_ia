<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Support\Listados\ListadoCursoExportParams;
use App\Support\Listados\ListadoEstudiantesFormatoMes;
use App\Support\PermisosIaCatalog;
use App\Support\RegistroAsistencia\RegistroAsistenciaDatos;
use App\Support\RegistroAsistencia\RegistroAsistenciaTcpdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class RegistroAsistenciaPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(tienePermiso(PermisosIaCatalog::REGISTRO_ASISTENCIA), 403, 'Sin permiso para el Registro de Asistencia.');

        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $key = 'registro-asistencia-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = Validator::make(
            [
                'cursos' => $request->query('cursos'),
                'mes' => $request->query('mes'),
            ],
            [
                'cursos' => ['required', 'string', 'max:8000'],
                'mes' => ['required', 'integer', 'min:1', 'max:12'],
            ],
            [
                'cursos.required' => 'Debe seleccionar al menos un curso.',
                'mes.required' => 'Debe seleccionar el mes.',
            ]
        )->validate();

        $mes = ListadoEstudiantesFormatoMes::normalizarMes($validated['mes']);
        if ($mes < 1) {
            abort(404);
        }

        $ctx = schoolCtx();
        $cursosPermitidos = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden']);

        if ($cursosPermitidos->isEmpty()) {
            abort(404);
        }

        $allowedById = $cursosPermitidos->keyBy(fn (Curso $c) => (int) $c->Id);
        $cursoIds = ListadoCursoExportParams::resolverIdsCursos(trim((string) $validated['cursos']), $allowedById);
        if ($cursoIds === []) {
            abort(404);
        }

        $ordenados = [];
        foreach ($cursosPermitidos as $c) {
            $id = (int) $c->Id;
            if (in_array($id, $cursoIds, true)) {
                $ordenados[] = $id;
            }
        }

        $datos = RegistroAsistenciaDatos::build($ordenados, $mes);
        if ($datos['cursos'] === []) {
            abort(404);
        }

        $pdf = RegistroAsistenciaTcpdf::generar($datos);
        $nombre = 'registro-asistencia-'.$datos['ano'].'-'.str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'.pdf';

        return RegistroAsistenciaTcpdf::respuestaHttp($pdf, $nombre);
    }
}
