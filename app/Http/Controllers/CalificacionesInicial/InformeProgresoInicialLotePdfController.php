<?php

namespace App\Http\Controllers\CalificacionesInicial;

use App\Http\Controllers\Controller;
use App\Support\BoletinSecundarioLoteParams;
use App\Support\CalificacionesInicial\InformeProgresoInicialDatos;
use App\Support\CalificacionesInicial\InformeProgresoInicialGenerador;
use App\Support\NivelSistema;
use App\Support\PortalDocente\CalificacionesInicialPortalDocente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Informe de Progreso Escolar (inicial): varios informes en un solo PDF (mismo curso).
 */
class InformeProgresoInicialLotePdfController extends Controller
{
    public function __invoke(Request $request)
    {
        if (CalificacionesInicialPortalDocente::esPortalDocente()) {
            CalificacionesInicialPortalDocente::abortSiMenuInactivo(CalificacionesInicialPortalDocente::MENU_INFORME_PROGRESO);
        }

        abort_unless(
            NivelSistema::esInicial((int) schoolCtx()->idNivel),
            403,
            'Este informe corresponde al nivel inicial.'
        );

        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $uid = (string) (auth()->id() ?? '');
        $key = 'staff-informe-progreso-inicial-lote-pdf:'.$uid.':'.($request->ip() ?? '');
        if (RateLimiter::tooManyAttempts($key, 15)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = $request->validate([
            'curso' => ['required', 'integer', 'min:1'],
            'etapa' => ['nullable', 'integer', 'in:1,2'],
            'matriculas' => ['required', 'array', 'min:1', 'max:'.BoletinSecundarioLoteParams::MAX_MATRICULAS],
            'matriculas.*' => ['integer', 'min:1'],
        ]);

        $cursoId = (int) $validated['curso'];
        $etapa = (int) ($validated['etapa'] ?? 1);

        if (CalificacionesInicialPortalDocente::esPortalDocente()) {
            CalificacionesInicialPortalDocente::abortSiProfesorSinCurso($cursoId);
        }

        $ids = BoletinSecundarioLoteParams::resolverIdsMatriculasDesdeLista(
            array_map('intval', $validated['matriculas']),
            $cursoId,
        );

        if ($ids === []) {
            abort(404);
        }

        $hojas = [];
        foreach ($ids as $idMatricula) {
            $data = InformeProgresoInicialDatos::buildForMatriculaEnContextoEscolar($idMatricula, $etapa);
            if ($data['ok']) {
                $hojas[] = $data;
            }
        }

        if ($hojas === []) {
            abort(404);
        }

        $cantidad = count($hojas);
        if ($cantidad === 1) {
            $a = $hojas[0]['alumno'] ?? [];
            $slugBase = trim(((string) ($a['apellido'] ?? '')).' '.((string) ($a['nombre'] ?? '')));
            $slug = Str::slug('informe-progreso-escolar-'.$slugBase, '_');
        } else {
            $slug = Str::slug('informes-progreso-escolar-'.$cantidad.'-alumnos', '_');
        }
        if ($slug === '') {
            $slug = 'informes_progreso_escolar';
        }

        $pdf = InformeProgresoInicialGenerador::generarLote($hojas, schoolPdfHeaderData());

        return InformeProgresoInicialGenerador::respuestaHttp($pdf, $slug.'.pdf');
    }
}
