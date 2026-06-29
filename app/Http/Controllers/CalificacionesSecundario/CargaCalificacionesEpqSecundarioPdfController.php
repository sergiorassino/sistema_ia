<?php

namespace App\Http\Controllers\CalificacionesSecundario;

use App\Http\Controllers\Controller;
use App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos;
use App\Support\CalificacionesSecundario\Epq\CalificacionesEpqSecundarioCatalogo;
use App\Support\CalificacionesSecundario\Epq\CargaCalificacionesEpqSecundarioDatos;
use App\Support\CalificacionesSecundario\Epq\CargaCalificacionesEpqSecundarioTcpdf;
use App\Support\NivelSistema;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\CalificacionesDocenteSecundario;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CargaCalificacionesEpqSecundarioPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        CalificacionesSecundarioModulos::abortSiImplementacionInactiva(
            CalificacionesSecundarioModulos::CARGA,
            CalificacionesEpqSecundarioCatalogo::IMPLEMENTACION,
        );

        abort_unless(
            NivelSistema::esSecundario((int) schoolCtx()->idNivel),
            403,
            'Esta planilla corresponde al nivel secundario.',
        );

        if (CalificacionesDocenteSecundario::nivelEsSecundario() && request()->routeIs('portalDocente.*')) {
            // Portal docente: wrapper valida ppc.
        } else {
            PortalDocenteContext::abortSiStaffSinPermisoIa(PermisosIaCatalog::CALIF_CARGA);
        }

        @ini_set('memory_limit', '512M');

        $key = 'carga-calificaciones-epq-sec-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = Validator::make(
            [
                'curso' => $request->query('curso'),
                'materia' => $request->query('materia'),
            ],
            [
                'curso' => ['required', 'integer', 'min:1'],
                'materia' => ['required', 'integer', 'min:1'],
            ],
        )->validate();

        $cursoId = (int) $validated['curso'];
        $materiaId = (int) $validated['materia'];

        if (request()->routeIs('portalDocente.*')) {
            CalificacionesDocenteSecundario::abortSiProfesorSinMateria($materiaId, $cursoId);
        }

        $payload = CargaCalificacionesEpqSecundarioDatos::build($cursoId, $materiaId);

        $slug = Str::slug(
            'planilla-epq-'.($payload['materiaLabel'] ?? '').'-'.($payload['cursoLabel'] ?? ''),
            '_',
        );
        if ($slug === '') {
            $slug = 'planilla_calificaciones_epq_secundario';
        }

        $pdf = CargaCalificacionesEpqSecundarioTcpdf::generar($payload);

        return CargaCalificacionesEpqSecundarioTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
