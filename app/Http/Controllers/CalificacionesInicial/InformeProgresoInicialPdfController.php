<?php

namespace App\Http\Controllers\CalificacionesInicial;

use App\Http\Controllers\Controller;
use App\Support\CalificacionesInicial\InformeProgresoInicialDatos;
use App\Support\CalificacionesInicial\InformeProgresoInicialTcpdf;
use App\Support\NivelSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Informe de Progreso Escolar — nivel inicial, una matrícula.
 */
class InformeProgresoInicialPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(
            NivelSistema::esInicial((int) schoolCtx()->idNivel),
            403,
            'Este informe corresponde al nivel inicial.'
        );

        $validated = $request->validate([
            'matricula' => ['required', 'integer', 'min:1'],
            'etapa' => ['nullable', 'integer', 'in:1,2'],
        ]);

        $idMatricula = (int) $validated['matricula'];
        $etapa = (int) ($validated['etapa'] ?? 1);

        $uid = (string) (auth()->id() ?? '');
        $key = 'staff-informe-progreso-inicial-pdf:'.$uid.':'.($request->ip() ?? '');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $data = InformeProgresoInicialDatos::buildForMatriculaEnContextoEscolar($idMatricula, $etapa);
        if (! $data['ok']) {
            abort(404, $data['error'] ?? 'No disponible.');
        }

        $slugBase = trim(((string) ($data['alumno']['apellido'] ?? '')).' '.((string) ($data['alumno']['nombre'] ?? '')));
        $slug = Str::slug('informe-progreso-escolar-'.$slugBase, '_');
        if ($slug === '') {
            $slug = 'informe_progreso_escolar';
        }

        $pdf = InformeProgresoInicialTcpdf::generar($data, schoolPdfHeaderData());

        return InformeProgresoInicialTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
