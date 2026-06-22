<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\InformeInasistencias;
use App\Support\InformeInasistenciasTcpdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Informe de inasistencias en PDF (misma plantilla que secretaría), solo del alumno en sesión.
 */
class InformeInasistenciasController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(tenantAutogestionInformeInasistenciasHabilitada(), 404);

        $key = 'alumnos-informe-inasistencias-pdf:'.(auth('alumno')->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $matricula = InformeInasistencias::matriculaAutogestion();
        if ($matricula === null) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => 'No hay matrícula registrada para este ciclo lectivo. Contacte a secretaría.',
            ], 422);
        }

        $idTipo = InformeInasistencias::tipoFiltroValido((int) $request->query('tipo', 0) ?: null);
        $datos = InformeInasistencias::datosPdf($matricula, $idTipo, InformeInasistencias::anoLectivoAutogestion());

        $slug = Str::slug('informe-inasistencias-'.$datos['alumnoLinea'], '_');
        if ($slug === '') {
            $slug = 'informe_inasistencias';
        }

        $pdf = InformeInasistenciasTcpdf::generar($datos, studentPdfHeaderData());

        return InformeInasistenciasTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
