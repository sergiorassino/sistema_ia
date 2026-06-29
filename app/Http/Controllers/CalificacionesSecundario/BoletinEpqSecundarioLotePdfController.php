<?php

namespace App\Http\Controllers\CalificacionesSecundario;

use App\Http\Controllers\Controller;
use App\Support\BoletinSecundarioLoteParams;
use App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos;
use App\Support\CalificacionesSecundario\Epq\BoletinEpqSecundarioDatos;
use App\Support\CalificacionesSecundario\Epq\BoletinEpqSecundarioTcpdf;
use App\Support\CalificacionesSecundario\Epq\CalificacionesEpqSecundarioCatalogo;
use App\Support\NivelSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Informe de calificaciones EPQ secundario — lote (dos informes por hoja A4).
 */
class BoletinEpqSecundarioLotePdfController extends Controller
{
    public function __invoke(Request $request)
    {
        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        CalificacionesSecundarioModulos::abortSiImplementacionInactiva(
            CalificacionesSecundarioModulos::BOLETIN,
            CalificacionesEpqSecundarioCatalogo::IMPLEMENTACION,
        );

        abort_unless(
            NivelSistema::esSecundario((int) schoolCtx()->idNivel),
            403,
            'Este informe corresponde al nivel secundario.',
        );

        $uid = (string) (auth()->id() ?? '');
        $key = 'boletin-epq-secundario-lote:'.$uid.':'.($request->ip() ?? '');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = $request->validate([
            'curso' => ['required', 'integer', 'min:1'],
            'matriculas' => ['required', 'array', 'min:1', 'max:'.BoletinSecundarioLoteParams::MAX_MATRICULAS],
            'matriculas.*' => ['integer', 'min:1'],
        ]);

        $cursoId = (int) $validated['curso'];
        $ids = BoletinSecundarioLoteParams::resolverIdsMatriculasDesdeLista(
            array_map('intval', $validated['matriculas']),
            $cursoId,
        );

        if ($ids === []) {
            abort(404);
        }

        $hojas = [];
        foreach ($ids as $idMatricula) {
            $built = BoletinEpqSecundarioDatos::buildForMatriculaEnContexto($idMatricula);
            if ($built['ok'] ?? false) {
                $hojas[] = $built['data'];
            }
        }

        abort_if($hojas === [], 404, 'No hay datos para generar el informe.');

        $pdf = BoletinEpqSecundarioTcpdf::generarLote($hojas);

        $cantidad = count($hojas);
        if ($cantidad === 1) {
            $d = $hojas[0];
            $slug = Str::slug('informe_calificaciones_'.($d['apellido'] ?? '').'_'.($d['nombre'] ?? ''), '_');
        } else {
            $slug = Str::slug('informes_calificaciones_'.$cantidad.'_alumnos', '_');
        }

        return BoletinEpqSecundarioTcpdf::respuestaHttp($pdf, ($slug !== '' ? $slug : 'informes_calificaciones').'.pdf');
    }
}
