<?php

namespace App\Http\Controllers\CalificacionesPrimario;

use App\Http\Controllers\Controller;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;
use App\Support\CalificacionesPrimario\Epq\CalificacionesEpqCatalogo;
use App\Support\CalificacionesPrimario\Epq\PlanillaCalificacionesEpqDatos;
use App\Support\CalificacionesPrimario\Epq\PlanillaCalificacionesEpqTcpdf;
use App\Support\NivelSistema;
use App\Support\PortalDocente\CalificacionesPrimarioPortalDocente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PlanillaCalificacionesEpqPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        CalificacionesPrimarioModulos::abortSiImplementacionInactiva(
            CalificacionesPrimarioModulos::PLANILLA,
            CalificacionesEpqCatalogo::IMPLEMENTACION,
        );

        abort_unless(
            NivelSistema::esPrimario((int) schoolCtx()->idNivel),
            403,
            'Esta planilla corresponde al nivel primario.',
        );

        if (CalificacionesPrimarioPortalDocente::esPortalDocente()) {
            abort_unless(
                (bool) config('tenant.portal_docente.menu.primario.planilla', false),
                404,
            );
        }

        @ini_set('memory_limit', '512M');

        $key = 'planilla-calificaciones-epq-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $materiasInput = $request->query('materias');
        if (($materiasInput === null || $materiasInput === '') && $request->filled('materia')) {
            $materiasInput = (string) (int) $request->query('materia');
        }

        $validated = Validator::make(
            ['materias' => $materiasInput],
            ['materias' => ['required', 'string', 'max:8000']],
        )->validate();

        $materiasPermitidas = PlanillaCalificacionesEpqDatos::materiasDisponibles(
            CalificacionesPrimarioPortalDocente::esPortalDocente(),
        );
        if ($materiasPermitidas->isEmpty()) {
            abort(404);
        }

        $allowedIds = $materiasPermitidas->pluck('id')->map(fn ($id) => (int) $id)->all();
        $idMaterias = PlanillaCalificacionesEpqDatos::resolverIdsMaterias(
            trim((string) $validated['materias']),
            $allowedIds,
        );
        if ($idMaterias === []) {
            abort(404);
        }

        $ordenados = PlanillaCalificacionesEpqDatos::ordenarIdsMaterias($idMaterias, $materiasPermitidas);
        $hojas = PlanillaCalificacionesEpqDatos::buildHojas($ordenados);
        if ($hojas === []) {
            abort(404);
        }

        $contexto = PlanillaCalificacionesEpqDatos::contextoPdf();

        if (count($hojas) === 1) {
            $slug = Str::slug(
                'planilla-'.($hojas[0]['materia'] ?? '').'-'.($hojas[0]['curso'] ?? ''),
                '_',
            );
        } else {
            $slug = Str::slug('planilla-calificaciones-'.count($hojas).'-materias', '_');
        }
        if ($slug === '') {
            $slug = 'planilla_calificaciones_epq';
        }

        $pdf = PlanillaCalificacionesEpqTcpdf::generar($contexto, $hojas);

        return PlanillaCalificacionesEpqTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
