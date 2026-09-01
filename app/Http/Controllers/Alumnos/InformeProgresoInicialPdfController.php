<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Alumnos\PortalFamiliaInformeProgresoInicial;
use App\Support\CalificacionesInicial\InformeProgresoInicialDatos;
use App\Support\EntoVerNotasOff;
use App\Support\CalificacionesInicial\InformeProgresoInicialGenerador;
use App\Support\NivelSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Informe de Progreso Escolar (inicial) — portal familia.
 * Incluye marca «SIN VALOR LEGAL» en páginas de espacios curriculares (no aplica en secretaría ni docentes).
 */
class InformeProgresoInicialPdfController extends Controller
{
    public function __invoke(Request $request, int $etapa)
    {
        abort_unless(PortalFamiliaInformeProgresoInicial::habilitadoEnMenu(), 404);

        if ($bloqueo = EntoVerNotasOff::respuestaPdfSiConsultaBloqueada()) {
            return $bloqueo;
        }

        abort_unless(in_array($etapa, [1, 2], true), 404);
        abort_unless(
            NivelSistema::esInicial((int) studentCtx()->idNivel),
            403,
            'Este informe corresponde al nivel inicial.'
        );

        $key = 'alumnos-informe-progreso-inicial-pdf:'.(auth('alumno')->id() ?? $request->ip()).':'.$etapa;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $data = InformeProgresoInicialDatos::buildDatosParaAlumno($etapa);
        if (! $data['ok']) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => $data['error'] ?? 'No hay datos disponibles para este ciclo lectivo.',
            ], 422);
        }

        $alumno = (array) ($data['alumno'] ?? []);
        $slugBase = trim(((string) ($alumno['apellido'] ?? '')).' '.((string) ($alumno['nombre'] ?? '')));
        $slug = Str::slug('informe-progreso-escolar-etapa'.$etapa.'-'.$slugBase, '_');
        if ($slug === '') {
            $slug = 'informe_progreso_escolar_etapa'.$etapa;
        }

        $pdf = InformeProgresoInicialGenerador::generar($data, studentPdfHeaderData(), true);

        return InformeProgresoInicialGenerador::respuestaHttp($pdf, $slug.'.pdf');
    }
}
