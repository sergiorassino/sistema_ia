<?php

namespace App\Http\Controllers\CalificacionesPrimario;

use App\Http\Controllers\Controller;
use App\Support\BoletinSecundarioLoteParams;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;
use App\Support\CalificacionesPrimario\Epq\BoletinPrimEpqDatos;
use App\Support\CalificacionesPrimario\Epq\BoletinPrimEpqTcpdf;
use App\Support\CalificacionesPrimario\Epq\CalificacionesEpqCatalogo;
use App\Support\NivelSistema;
use App\Support\PortalDocente\CalificacionesPrimarioPortalDocente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Boletín (Prim) EPQ — lote de matrículas.
 */
class BoletinPrimEpqLotePdfController extends Controller
{
    public function __invoke(Request $request)
    {
        CalificacionesPrimarioModulos::abortSiImplementacionInactiva(
            CalificacionesPrimarioModulos::BOLETIN_PRIM,
            CalificacionesEpqCatalogo::IMPLEMENTACION,
        );

        abort_unless(
            NivelSistema::esPrimario((int) schoolCtx()->idNivel),
            403,
            'Este informe corresponde al nivel primario.',
        );

        $validated = $request->validate([
            'matriculas' => ['required', 'array', 'min:1', 'max:'.BoletinSecundarioLoteParams::MAX_MATRICULAS],
            'matriculas.*' => ['integer', 'min:1'],
            'cara' => ['nullable', 'string', 'in:anverso,reverso,completo'],
        ]);

        if (CalificacionesPrimarioPortalDocente::esPortalDocente()) {
            CalificacionesPrimarioPortalDocente::abortSiPortalBoletinPrimEpqInactivo();
        }

        $key = 'boletin-prim-epq-lote:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $cara = (string) ($validated['cara'] ?? 'completo');
        $hojas = [];

        foreach ($validated['matriculas'] as $idMatricula) {
            $idMatricula = (int) $idMatricula;
            if (CalificacionesPrimarioPortalDocente::esPortalDocente()) {
                CalificacionesPrimarioPortalDocente::abortSiProfesorSinMatricula($idMatricula);
            }

            $built = BoletinPrimEpqDatos::buildForMatriculaEnContexto($idMatricula);
            if ($built['ok'] ?? false) {
                $hojas[] = $built['data'];
            }
        }

        abort_if($hojas === [], 404, 'No hay datos para generar el boletín.');

        $pdf = BoletinPrimEpqTcpdf::generarLote($hojas, $cara);
        $slug = Str::slug('boletin_prim_lote_'.schoolCtx()->terlecAno(), '_');

        return BoletinPrimEpqTcpdf::respuestaHttp($pdf, ($slug !== '' ? $slug : 'boletin_prim_lote').'.pdf');
    }
}
