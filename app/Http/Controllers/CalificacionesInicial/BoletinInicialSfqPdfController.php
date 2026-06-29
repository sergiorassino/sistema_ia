<?php

namespace App\Http\Controllers\CalificacionesInicial;

use App\Http\Controllers\Controller;
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
 * Informes inicial SFQ — una matrícula.
 */
class BoletinInicialSfqPdfController extends Controller
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
            'matricula' => ['required', 'integer', 'min:1'],
            'tipo' => ['required', 'string', Rule::in(CalificacionesInicialSfqCatalogo::TIPOS_INFORME)],
        ]);

        $idMatricula = (int) $validated['matricula'];
        $tipo = (string) $validated['tipo'];

        if (CalificacionesInicialSfqPortalDocente::esPortalDocente()) {
            CalificacionesInicialSfqPortalDocente::abortSiMenuBoletinInactivo();
            CalificacionesInicialSfqPortalDocente::abortSiProfesorSinMatricula($idMatricula);
        }

        $key = 'boletin-inic-sfq-pdf:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $built = BoletinInicialSfqDatos::buildForMatriculaEnContexto($idMatricula, $tipo);
        if (! ($built['ok'] ?? false)) {
            abort(404, $built['error'] ?? 'No disponible.');
        }

        $datos = $built['data'];
        $pdf = BoletinInicialSfqTcpdf::generarHoja($datos);
        $meta = CalificacionesInicialSfqCatalogo::metaTipoInforme($tipo);
        $slugTipo = Str::slug((string) ($meta['etiqueta'] ?? $tipo), '_');
        $slug = Str::slug(
            'informe_inicial_'.($datos['apellido'] ?? '').'_'.($datos['nombre'] ?? '').'_'.$slugTipo,
            '_',
        );

        return BoletinInicialSfqTcpdf::respuestaHttp($pdf, ($slug !== '' ? $slug : 'informe_inicial').'.pdf');
    }
}
