<?php

namespace App\Http\Controllers\CalificacionesInicial;

use App\Http\Controllers\Controller;
use App\Support\BoletinSecundarioLoteParams;
use App\Support\CalificacionesInicial\CalificacionesInicialModulos;
use App\Support\CalificacionesInicial\Sfq\BoletinInicialSfqDatos;
use App\Support\CalificacionesInicial\Sfq\BoletinInicialSfqTcpdf;
use App\Support\CalificacionesInicial\Sfq\CalificacionesInicialSfqCatalogo;
use App\Support\NivelSistema;
use App\Support\PortalDocente\CalificacionesInicialSfqPortalDocente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Informes inicial SFQ — lote de matrículas.
 */
class BoletinInicialSfqLotePdfController extends Controller
{
    public function __invoke(Request $request)
    {
        CalificacionesInicialModulos::abortSiImplementacionInactiva(
            CalificacionesInicialModulos::BOLETIN,
            CalificacionesInicialSfqCatalogo::IMPLEMENTACION,
        );

        abort_unless(
            NivelSistema::esInicial((int) schoolCtx()->idNivel),
            403,
            'Este informe corresponde al nivel inicial.',
        );

        $validated = $request->validate([
            'matriculas' => ['required', 'array', 'min:1', 'max:'.BoletinSecundarioLoteParams::MAX_MATRICULAS],
            'matriculas.*' => ['integer', 'min:1'],
            'tipo' => ['required', 'string', Rule::in(CalificacionesInicialSfqCatalogo::TIPOS_INFORME)],
        ]);

        if (CalificacionesInicialSfqPortalDocente::esPortalDocente()) {
            CalificacionesInicialSfqPortalDocente::abortSiMenuBoletinInactivo();
        }

        $key = 'boletin-inic-sfq-lote:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $tipo = (string) $validated['tipo'];
        $hojas = [];

        foreach ($validated['matriculas'] as $idMatricula) {
            $idMatricula = (int) $idMatricula;
            if (CalificacionesInicialSfqPortalDocente::esPortalDocente()) {
                CalificacionesInicialSfqPortalDocente::abortSiProfesorSinMatricula($idMatricula);
            }

            $built = BoletinInicialSfqDatos::buildForMatriculaEnContexto($idMatricula, $tipo);
            if ($built['ok'] ?? false) {
                $hojas[] = $built['data'];
            }
        }

        abort_if($hojas === [], 404, 'No hay datos para generar el informe.');

        $pdf = BoletinInicialSfqTcpdf::generarLote($hojas);
        $meta = CalificacionesInicialSfqCatalogo::metaTipoInforme($tipo);
        $slugTipo = Str::slug((string) ($meta['etiqueta'] ?? $tipo), '_');
        $slug = Str::slug('informe_inicial_lote_'.schoolCtx()->terlecAno().'_'.$slugTipo, '_');

        return BoletinInicialSfqTcpdf::respuestaHttp($pdf, ($slug !== '' ? $slug : 'informe_inicial_lote').'.pdf');
    }
}
