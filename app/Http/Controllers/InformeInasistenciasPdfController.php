<?php

namespace App\Http\Controllers;

use App\Models\Matricula;
use App\Support\InformeInasistencias;
use App\Support\InformeInasistenciasTcpdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InformeInasistenciasPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(tenantSecretariaInformeInasistenciasHabilitada(), 404);

        $validated = $request->validate([
            'matricula' => ['required', 'integer', 'min:1'],
            'tipo' => ['nullable', 'integer', 'min:0'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'ambito' => ['nullable', 'string', Rule::in([
                InformeInasistencias::AMBITO_TODAS,
                InformeInasistencias::AMBITO_CLASE,
                InformeInasistencias::AMBITO_EDUCACION_FISICA,
            ])],
        ]);

        $idMatricula = (int) $validated['matricula'];

        $key = 'informe-inasistencias-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();

        /** @var Matricula $matricula */
        $matricula = Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->findOrFail($idMatricula);

        $idTipo = InformeInasistencias::tipoFiltroValido((int) ($validated['tipo'] ?? 0) ?: null);
        [$desde, $hasta] = InformeInasistencias::rangoSolicitado(
            $validated['desde'] ?? null,
            $validated['hasta'] ?? null,
        );
        $ambito = InformeInasistencias::ambitoFiltroValido($validated['ambito'] ?? null);

        $datos = InformeInasistencias::datosPdf(
            $matricula,
            $idTipo,
            InformeInasistencias::anoLectivo(),
            $desde,
            $hasta,
            $ambito,
        );

        $slug = Str::slug('informe-inasistencias-'.$datos['alumnoLinea'], '_');
        if ($slug === '') {
            $slug = 'informe_inasistencias';
        }

        $pdf = InformeInasistenciasTcpdf::generar($datos, schoolPdfHeaderData());

        return InformeInasistenciasTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
