<?php

namespace App\Http\Controllers\CalificacionesSecundario;

use App\Http\Controllers\Controller;
use App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos;
use App\Support\CalificacionesSecundario\Epq\CalificacionesEpqSecundarioCatalogo;
use App\Support\CalificacionesSecundario\Epq\PlanillaCalificacionesEpqSecundarioDatos;
use App\Support\CalificacionesSecundario\Epq\PlanillaCalificacionesEpqSecundarioTcpdf;
use App\Support\NivelSistema;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PlanillaCalificacionesEpqSecundarioPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        CalificacionesSecundarioModulos::abortSiImplementacionInactiva(
            CalificacionesSecundarioModulos::PLANILLA,
            CalificacionesEpqSecundarioCatalogo::IMPLEMENTACION,
        );

        abort_unless(
            NivelSistema::esSecundario((int) schoolCtx()->idNivel),
            403,
            'Esta planilla corresponde al nivel secundario.',
        );

        if (! request()->routeIs('portalDocente.*')) {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para generar planillas.',
            );
        }

        @ini_set('memory_limit', '512M');

        $key = 'planilla-calificaciones-epq-sec-pdf:'.(auth()->id() ?? $request->ip());
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

        $soloPortal = request()->routeIs('portalDocente.*');
        $materiasPermitidas = PlanillaCalificacionesEpqSecundarioDatos::materiasDisponibles($soloPortal);
        if ($materiasPermitidas->isEmpty()) {
            abort(404);
        }

        $allowedIds = $materiasPermitidas->pluck('id')->map(fn ($id) => (int) $id)->all();
        $idMaterias = PlanillaCalificacionesEpqSecundarioDatos::resolverIdsMaterias(
            trim((string) $validated['materias']),
            $allowedIds,
        );
        if ($idMaterias === []) {
            abort(404);
        }

        $ordenados = PlanillaCalificacionesEpqSecundarioDatos::ordenarIdsMaterias($idMaterias, $materiasPermitidas);
        $hojas = PlanillaCalificacionesEpqSecundarioDatos::buildHojas($ordenados);
        if ($hojas === []) {
            abort(404);
        }

        $contexto = PlanillaCalificacionesEpqSecundarioDatos::contextoPdf();

        if (count($hojas) === 1) {
            $slug = Str::slug(
                'planilla-'.($hojas[0]['materia'] ?? '').'-'.($hojas[0]['curso'] ?? ''),
                '_',
            );
        } else {
            $slug = Str::slug('planilla-calificaciones-'.count($hojas).'-materias', '_');
        }
        if ($slug === '') {
            $slug = 'planilla_calificaciones_epq_secundario';
        }

        $pdf = PlanillaCalificacionesEpqSecundarioTcpdf::generar($contexto, $hojas);

        return PlanillaCalificacionesEpqSecundarioTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
