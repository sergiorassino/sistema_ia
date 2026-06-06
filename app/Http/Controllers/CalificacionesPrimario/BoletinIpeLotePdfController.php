<?php

namespace App\Http\Controllers\CalificacionesPrimario;

use App\Http\Controllers\Controller;
use App\Support\BoletinSecundarioLoteParams;
use App\Support\CalificacionesPrimario\BoletinIpeDatos;
use App\Support\CalificacionesPrimario\BoletinIpeTcpdf;
use App\Support\NivelSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * IPE primario: varios informes en un solo PDF (mismo curso).
 */
class BoletinIpeLotePdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(
            NivelSistema::esPrimario((int) schoolCtx()->idNivel),
            403,
            'Este informe corresponde al nivel primario.'
        );

        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $uid = (string) (auth()->id() ?? '');
        $key = 'staff-boletin-ipe-primario-lote-pdf:'.$uid.':'.($request->ip() ?? '');
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
        $ids = BoletinSecundarioLoteParams::resolverIdsMatriculasDesdeLista(
            array_map('intval', $validated['matriculas']),
            $cursoId,
        );

        if ($ids === []) {
            abort(404);
        }

        $hojas = [];
        foreach ($ids as $idMatricula) {
            $data = BoletinIpeDatos::buildForMatriculaEnContextoEscolar($idMatricula, $etapa);
            if ($data['ok']) {
                $hojas[] = $data;
            }
        }

        if ($hojas === []) {
            abort(404);
        }

        $cantidad = count($hojas);
        if ($cantidad === 1) {
            $slugBase = trim((string) ($hojas[0]['alumnoLinea'] ?? ''));
            $slug = Str::slug('informe-progreso-escolar-'.$slugBase, '_');
        } else {
            $slug = Str::slug('informes-progreso-escolar-'.$cantidad.'-alumnos', '_');
        }
        if ($slug === '') {
            $slug = 'informes_progreso_escolar';
        }

        $pdf = BoletinIpeTcpdf::generarLote($hojas, schoolPdfHeaderData());

        return BoletinIpeTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
