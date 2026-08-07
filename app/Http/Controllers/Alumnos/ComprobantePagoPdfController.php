<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Alumnos\ArancelesEscolares;
use App\Support\Alumnos\ComprobantePagoDatos;
use App\Support\Alumnos\ComprobantePagoPdf;
use App\Support\Cuotas\Siro\SiroConfiguracionIncompletaException;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Comprobante de pago de aranceles en PDF para el alumno/familia en sesión.
 */
class ComprobantePagoPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(tenantAutogestionArancelesEscolaresHabilitada(), 404);

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_COMPROBANTE_PAGO);
        if ($decoded === null) {
            abort(404);
        }

        $ctx = studentCtx();
        if (! $ctx->isValid() || $decoded['legajo'] !== (int) $ctx->idLegajo) {
            abort(404);
        }

        $id = $decoded['id'];

        $key = 'alumnos-comprobante-pago-pdf:'.(auth('alumno')->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $registro = ArancelesEscolares::cuotaPendienteParaAutogestion($id);
        if ($registro === null) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => 'No se encontró la cuota pendiente solicitada o ya no tiene saldo a abonar.',
            ], 422);
        }

        if (ArancelesEscolares::cuotaVencidaParaReimpresion($registro)) {
            return redirect()
                ->route('alumnos.aranceles-escolares')
                ->with('aranceles_cuota_vencida', ArancelesEscolares::mensajeCuotaVencidaReimpresion());
        }

        try {
            $datos = ComprobantePagoDatos::paraAutogestion($id);
        } catch (SiroConfiguracionIncompletaException $e) {
            return redirect()
                ->route('alumnos.aranceles-escolares')
                ->with('aranceles_siro_config', $e->getMessage());
        }
        if ($datos === null) {
            return response()->view('errors.alumno-pdf', [
                'mensaje' => 'No se encontró la cuota pendiente solicitada o ya no tiene saldo a abonar.',
            ], 422);
        }

        $nombreCuota = trim((string) ($datos['nombreCuota'] ?? $datos['cuota'] ?? ''));
        $slug = Str::slug(
            'comprobante-pago-'.trim(($datos['apellido'] ?? '').'-'.($datos['nombre'] ?? '').'-'.$nombreCuota),
            '_',
        );
        if ($slug === '') {
            $slug = 'comprobante_pago';
        }

        $pdf = ComprobantePagoPdf::generar($datos);

        return ComprobantePagoPdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
