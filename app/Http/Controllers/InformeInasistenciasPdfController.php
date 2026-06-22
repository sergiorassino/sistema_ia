<?php

namespace App\Http\Controllers;

use App\Support\PermisosIaCatalog;
use App\Models\Matricula;
use App\Support\InformeInasistencias;
use App\Support\InformeInasistenciasTcpdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class InformeInasistenciasPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(
            tienePermiso(PermisosIaCatalog::INASISTENCIAS_ESTUDIANTES_GESTION),
            403,
            'Sin permiso para gestión de inasistencias de estudiantes.'
        );
        abort_unless(tenantSecretariaInformeInasistenciasHabilitada(), 404);

        $validated = $request->validate([
            'matricula' => ['required', 'integer', 'min:1'],
            'tipo' => ['nullable', 'integer', 'min:0'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
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
        $desde = trim((string) ($validated['desde'] ?? ''));
        $hasta = trim((string) ($validated['hasta'] ?? ''));

        $datos = InformeInasistencias::datosPdf(
            $matricula,
            $idTipo,
            InformeInasistencias::anoLectivo(),
            $desde !== '' ? $desde : null,
            $hasta !== '' ? $hasta : null,
        );

        $slug = Str::slug('informe-inasistencias-'.$datos['alumnoLinea'], '_');
        if ($slug === '') {
            $slug = 'informe_inasistencias';
        }

        $pdf = InformeInasistenciasTcpdf::generar($datos, schoolPdfHeaderData());

        return InformeInasistenciasTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
