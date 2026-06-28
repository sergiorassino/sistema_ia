<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Alumnos\PortalFamiliaBoletinPrimEpq;
use App\Support\CalificacionesPrimario\Epq\BoletinPrimEpqDatos;
use App\Support\CalificacionesPrimario\Epq\BoletinPrimEpqTcpdf;
use App\Support\NivelSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Boletín (Prim) EPQ — portal familia (portada / calificaciones).
 */
class BoletinPrimEpqFamiliaPdfController extends Controller
{
    public function __invoke(Request $request, string $cara)
    {
        abort_unless(PortalFamiliaBoletinPrimEpq::habilitadoEnMenu(), 404);

        $caraPdf = PortalFamiliaBoletinPrimEpq::caraPdf($cara);
        abort_unless($caraPdf !== null, 404);

        abort_unless(
            NivelSistema::esPrimario((int) studentCtx()->idNivel),
            403,
            'Este informe corresponde al nivel primario.'
        );

        $key = 'alumnos-boletin-prim-epq-pdf:'.(auth('alumno')->id() ?? $request->ip()).':'.$cara;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $built = BoletinPrimEpqDatos::buildDatosParaAlumno();
        if (! ($built['ok'] ?? false)) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => $built['error'] ?? 'No hay datos disponibles para este ciclo lectivo.',
            ], 422);
        }

        $datos = $built['data'];
        $pdf = BoletinPrimEpqTcpdf::generarLote([$datos], $caraPdf);

        $slugBase = trim(((string) ($datos['apellido'] ?? '')).' '.((string) ($datos['nombre'] ?? '')));
        $slug = Str::slug('boletin_prim_epq_'.$cara.'_'.$slugBase, '_');
        if ($slug === '') {
            $slug = 'boletin_prim_epq_'.$cara;
        }

        return BoletinPrimEpqTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
