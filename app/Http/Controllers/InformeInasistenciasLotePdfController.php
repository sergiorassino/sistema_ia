<?php

namespace App\Http\Controllers;

use App\Models\Matricula;
use App\Support\InformeInasistencias;
use App\Support\InformeInasistenciasLoteParams;
use App\Support\InformeInasistenciasTcpdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Informes de inasistencias en un solo PDF (varias matrículas del mismo curso).
 */
class InformeInasistenciasLotePdfController extends Controller
{
    public function __invoke(Request $request)
    {
        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $key = 'informe-inasistencias-lote-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = $request->validate([
            'curso' => ['required', 'integer', 'min:1'],
            'matriculas' => ['required', 'array', 'min:1', 'max:'.InformeInasistenciasLoteParams::MAX_MATRICULAS],
            'matriculas.*' => ['integer', 'min:1'],
            'tipo' => ['nullable', 'integer', 'min:0'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $cursoId = (int) $validated['curso'];
        $ids = InformeInasistenciasLoteParams::resolverIdsMatriculasDesdeLista(
            array_map('intval', $validated['matriculas']),
            $cursoId,
        );

        if ($ids === []) {
            abort(404);
        }

        $idTipo = InformeInasistencias::tipoFiltroValido((int) ($validated['tipo'] ?? 0) ?: null);
        $desde = trim((string) ($validated['desde'] ?? ''));
        $hasta = trim((string) ($validated['hasta'] ?? ''));
        $ano = InformeInasistencias::anoLectivo();

        $hojas = [];
        foreach ($ids as $idMatricula) {
            /** @var Matricula|null $matricula */
            $matricula = Matricula::query()
                ->with(['legajo', 'curso'])
                ->where('idNivel', schoolCtx()->idNivel)
                ->where('idTerlec', schoolCtx()->idTerlec)
                ->find($idMatricula);

            if ($matricula === null) {
                continue;
            }

            $hojas[] = InformeInasistencias::datosPdf(
                $matricula,
                $idTipo,
                $ano,
                $desde !== '' ? $desde : null,
                $hasta !== '' ? $hasta : null,
            );
        }

        if ($hojas === []) {
            abort(404);
        }

        $cantidad = count($hojas);
        if ($cantidad === 1) {
            $slug = Str::slug('informe-inasistencias-'.($hojas[0]['alumnoLinea'] ?? ''), '_');
        } else {
            $slug = Str::slug('informes-inasistencias-'.$cantidad.'-alumnos', '_');
        }
        if ($slug === '') {
            $slug = 'informes_inasistencias';
        }

        $pdf = InformeInasistenciasTcpdf::generarLote($hojas, schoolPdfHeaderData());

        return InformeInasistenciasTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
