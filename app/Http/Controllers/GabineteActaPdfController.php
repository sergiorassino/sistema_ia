<?php

namespace App\Http\Controllers;

use App\Support\PermisosIaCatalog;
use App\Support\Security\OpaqueRouteToken;
use App\Support\Seguimiento\GabineteActaTcpdf;
use App\Support\Seguimiento\GabineteOrientacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class GabineteActaPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(tienePermiso(PermisosIaCatalog::SEGUIMIENTO_GABINETE), 403, 'Sin permiso para seguimiento de gabinete.');
        abort_unless(GabineteOrientacion::tablasDisponibles(), 404);

        $key = 'gabinete-acta-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $payload = OpaqueRouteToken::decodePayload($ref, OpaqueRouteToken::PURPOSE_GABINETE_ACTA);
        if ($payload === null) {
            abort(404);
        }

        $id = (int) ($payload['g'] ?? 0);
        $idLegajos = (int) ($payload['l'] ?? 0);
        if ($id < 1 || $idLegajos < 1) {
            abort(404);
        }

        $reg = GabineteOrientacion::registroEnAlcance($id);
        if ((int) ($reg->matricula?->idLegajos ?? 0) !== $idLegajos) {
            abort(404);
        }

        $header = schoolPdfHeaderData();
        $legajo = $reg->matricula?->legajo;
        $curso = $reg->matricula?->curso;

        $alumnoNombre = trim((string) ($legajo?->apellido ?? '').' '.(string) ($legajo?->nombre ?? ''));
        $cursoLabel = $curso?->nombreParaListado() ?? '';
        $nroLegajo = GabineteOrientacion::nroLegajo($legajo);

        $fecha = $reg->fecha?->format('d/m/Y') ?? now()->format('d/m/Y');
        $localidad = trim((string) ($header['localidad'] ?? ''));
        $lineaLugarFecha = $localidad !== '' ? $localidad.', '.$fecha : $fecha;

        $slug = Str::slug('acta-gabinete-'.$alumnoNombre.'-'.$fecha, '_');
        if ($slug === '') {
            $slug = 'acta_gabinete';
        }

        $pdf = GabineteActaTcpdf::generar([
            'nombreInstitucion' => trim((string) ($header['insti'] ?? '')) ?: 'Institución',
            'alumnoNombre' => $alumnoNombre,
            'cursoLabel' => $cursoLabel,
            'nroLegajo' => $nroLegajo,
            'lineaLugarFecha' => $lineaLugarFecha,
            'solipor' => (string) ($reg->solipor ?? ''),
            'motivo' => (string) ($reg->motivo ?? ''),
            'asistentes' => (string) ($reg->asistentes ?? ''),
            'conclusion' => (string) ($reg->conclusion ?? ''),
        ]);

        return GabineteActaTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
