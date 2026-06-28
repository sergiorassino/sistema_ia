<?php

namespace App\Http\Controllers\CalificacionesPrimario;

use App\Http\Controllers\Controller;
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
 * Boletín (Prim) EPQ — una matrícula.
 */
class BoletinPrimEpqPdfController extends Controller
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
            'matricula' => ['required', 'integer', 'min:1'],
            'cara' => ['nullable', 'string', 'in:anverso,reverso,completo'],
        ]);

        $idMatricula = (int) $validated['matricula'];
        $cara = (string) ($validated['cara'] ?? 'completo');

        if (CalificacionesPrimarioPortalDocente::esPortalDocente()) {
            CalificacionesPrimarioPortalDocente::abortSiPortalBoletinPrimEpqInactivo();
            CalificacionesPrimarioPortalDocente::abortSiProfesorSinMatricula($idMatricula);
        }

        $key = 'boletin-prim-epq-pdf:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $built = BoletinPrimEpqDatos::buildForMatriculaEnContexto($idMatricula);
        if (! ($built['ok'] ?? false)) {
            abort(404, $built['error'] ?? 'No disponible.');
        }

        $datos = $built['data'];
        $pdf = match ($cara) {
            'anverso', 'reverso' => BoletinPrimEpqTcpdf::generarLote([$datos], $cara),
            default => BoletinPrimEpqTcpdf::generarCompleto($datos),
        };

        $slug = Str::slug('boletin_prim_'.($datos['apellido'] ?? '').'_'.($datos['nombre'] ?? ''), '_');

        return BoletinPrimEpqTcpdf::respuestaHttp($pdf, ($slug !== '' ? $slug : 'boletin_prim').'.pdf');
    }
}
