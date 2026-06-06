<?php

namespace App\Http\Controllers\CalificacionesPrimario;

use App\Http\Controllers\Controller;
use App\Support\CalificacionesPrimario\BoletinIpeDatos;
use App\Support\CalificacionesPrimario\BoletinIpeTcpdf;
use App\Support\NivelSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Informe de Progreso Escolar (IPE) — primario, una matrícula.
 */
class BoletinIpePdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(
            NivelSistema::esPrimario((int) schoolCtx()->idNivel),
            403,
            'Este informe corresponde al nivel primario.'
        );

        $validated = $request->validate([
            'matricula' => ['required', 'integer', 'min:1'],
            'etapa' => ['nullable', 'integer', 'in:1,2'],
        ]);

        $idMatricula = (int) $validated['matricula'];
        $etapa = (int) ($validated['etapa'] ?? 1);

        $uid = (string) (auth()->id() ?? '');
        $key = 'staff-boletin-ipe-primario-pdf:'.$uid.':'.($request->ip() ?? '');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $data = BoletinIpeDatos::buildForMatriculaEnContextoEscolar($idMatricula, $etapa);
        if (! $data['ok']) {
            abort(404, $data['error'] ?? 'No disponible.');
        }

        $slugBase = trim((string) ($data['alumnoLinea'] ?? ''));
        $slug = Str::slug('informe-progreso-escolar-'.$slugBase, '_');
        if ($slug === '') {
            $slug = 'informe_progreso_escolar';
        }

        $pdf = BoletinIpeTcpdf::generarHoja($data, schoolPdfHeaderData());

        return BoletinIpeTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
