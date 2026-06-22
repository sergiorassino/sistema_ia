<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Alumnos\PortalFamiliaBoletinIpe;
use App\Support\CalificacionesPrimario\BoletinIpePrimarioGenerador;
use App\Support\NivelSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Boletín IPE / síntesis y calificaciones — primario, portal familia (misma variante que secretaría).
 * Incluye marca «SIN VALOR LEGAL» (no aplica en impresión desde secretaría o docentes).
 */
class BoletinIpePrimarioPdfController extends Controller
{
    public function __invoke(Request $request, int $etapa)
    {
        abort_unless(PortalFamiliaBoletinIpe::habilitadoEnMenu(), 404);
        abort_unless(in_array($etapa, [1, 2], true), 404);
        abort_unless(
            NivelSistema::esPrimario((int) studentCtx()->idNivel),
            403,
            'Este informe corresponde al nivel primario.'
        );

        $key = 'alumnos-boletin-ipe-primario-pdf:'.(auth('alumno')->id() ?? $request->ip()).':'.$etapa;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $data = BoletinIpePrimarioGenerador::buildDatosParaAlumno($etapa);
        if (! $data['ok']) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => $data['error'] ?? 'No hay datos disponibles para este ciclo lectivo.',
            ], 422);
        }

        $prefijo = BoletinIpePrimarioGenerador::prefijoArchivoPdf();
        $slugBase = trim((string) ($data['alumnoLinea'] ?? ''));
        $slug = Str::slug($prefijo.'-etapa'.$etapa.'-'.$slugBase, '_');
        if ($slug === '') {
            $slug = $prefijo.'_etapa'.$etapa;
        }

        $pdf = BoletinIpePrimarioGenerador::generarHoja($data, studentPdfHeaderData(), true);

        return BoletinIpePrimarioGenerador::respuestaHttp($pdf, $slug.'.pdf');
    }
}
