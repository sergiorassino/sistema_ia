<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Alumnos\PortalFamiliaBoletinInicialSfq;
use App\Support\CalificacionesInicial\Sfq\BoletinInicialSfqDatos;
use App\Support\CalificacionesInicial\Sfq\BoletinInicialSfqTcpdf;
use App\Support\CalificacionesInicial\Sfq\CalificacionesInicialSfqCatalogo;
use App\Support\EntoVerNotasOff;
use App\Support\NivelSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Informes pedagógicos inicial SFQ — portal familia (solo el alumno en sesión).
 * Incluye marca «SIN VALOR LEGAL» (no aplica en impresión desde secretaría o docentes).
 */
class BoletinInicialSfqFamiliaPdfController extends Controller
{
    public function __invoke(Request $request, string $tipo)
    {
        abort_unless(PortalFamiliaBoletinInicialSfq::habilitadoEnMenu(), 404);
        abort_unless(CalificacionesInicialSfqCatalogo::esTipoInformeValido($tipo), 404);

        if ($bloqueo = EntoVerNotasOff::respuestaPdfSiConsultaBloqueada()) {
            return $bloqueo;
        }

        abort_unless(
            NivelSistema::esInicial((int) studentCtx()->idNivel),
            403,
            'Este informe corresponde al nivel inicial.'
        );

        $key = 'alumnos-informe-pedagogico-inicial-sfq-pdf:'.(auth('alumno')->id() ?? $request->ip()).':'.$tipo;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $built = BoletinInicialSfqDatos::buildDatosParaAlumno($tipo);
        if (! ($built['ok'] ?? false)) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => $built['error'] ?? 'No hay datos disponibles para este ciclo lectivo.',
            ], 422);
        }

        $datos = $built['data'];
        $pdf = BoletinInicialSfqTcpdf::generarHoja($datos, true);
        $meta = CalificacionesInicialSfqCatalogo::metaTipoInforme($tipo);
        $slugTipo = Str::slug((string) ($meta['etiqueta'] ?? $tipo), '_');
        $slug = Str::slug(
            'informe_pedagogico_'.($datos['apellido'] ?? '').'_'.($datos['nombre'] ?? '').'_'.$slugTipo,
            '_',
        );

        return BoletinInicialSfqTcpdf::respuestaHttp(
            $pdf,
            ($slug !== '' ? $slug : 'informe_pedagogico_inicial').'.pdf',
        );
    }
}
