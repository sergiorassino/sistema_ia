<?php

namespace App\Http\Controllers;

use App\Support\PermisosIaCatalog;
use App\Support\Security\OpaqueRouteToken;
use App\Support\Seguimiento\GabineteHistorialTcpdf;
use App\Support\Seguimiento\GabineteOrientacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class GabineteHistorialPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(tienePermiso(PermisosIaCatalog::SEGUIMIENTO_GABINETE), 403, 'Sin permiso para seguimiento de gabinete.');
        abort_unless(GabineteOrientacion::tablasDisponibles(), 404);

        $key = 'gabinete-historial-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_GABINETE_HISTORIAL);
        if ($payload === null) {
            abort(404);
        }

        $idLegajos = (int) ($payload['l'] ?? 0);
        if ($idLegajos < 1) {
            abort(404);
        }

        $actual = GabineteOrientacion::matriculaActualDelLegajo($idLegajos);
        abort_if($actual === null, 404);

        $header = schoolPdfHeaderData();
        $legajo = $actual->legajo;
        $alumnoNombre = trim((string) ($legajo?->apellido ?? '').' '.(string) ($legajo?->nombre ?? ''));
        $cursoLabel = $actual->curso?->nombreParaListado() ?? '';

        $registros = GabineteOrientacion::registrosDelLegajo($idLegajos)->map(function ($reg) {
            return [
                'fecha' => $reg->fecha?->format('d/m/Y') ?? '',
                'tipo' => (string) ($reg->tipo?->tipo ?? ''),
                'solipor' => (string) ($reg->solipor ?? ''),
                'motivo' => (string) ($reg->motivo ?? ''),
                'asistentes' => (string) ($reg->asistentes ?? ''),
                'conclusion' => (string) ($reg->conclusion ?? ''),
            ];
        })->all();

        $slug = Str::slug('historial-gabinete-'.$alumnoNombre, '_');
        if ($slug === '') {
            $slug = 'historial_gabinete';
        }

        $pdf = GabineteHistorialTcpdf::generar([
            'nombreInstitucion' => trim((string) ($header['insti'] ?? '')) ?: 'Institución',
            'alumnoNombre' => $alumnoNombre,
            'cursoLabel' => $cursoLabel,
            'logo_file' => $header['logo_file'] ?? null,
            'impresoEn' => now()->format('d/m/Y H:i'),
            'colorSemaforo' => GabineteOrientacion::colorDelLegajo($idLegajos),
            'registros' => $registros,
        ]);

        return GabineteHistorialTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
