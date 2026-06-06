<?php

namespace App\Http\Controllers\Cuotas;

use App\Http\Controllers\Controller;
use App\Support\Alumnos\ArancelesEscolares;
use App\Support\Alumnos\ComprobantePagoDatos;
use App\Support\Alumnos\ComprobantePagoTcpdf;
use App\Support\Cuotas\GestionAranceles;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\PermisosCuotas;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Cupón de pago PDF desde Gestión de aranceles (Administración).
 */
class ComprobantePagoCuotasPdfController extends Controller
{
    public function __invoke(Request $request, string $ref)
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);
        abort_unless(tenantAutogestionArancelesEscolaresHabilitada(), 404);

        $decoded = OpaqueRouteToken::decode($ref, OpaqueRouteToken::PURPOSE_ADMIN_COMPROBANTE_PAGO);
        if ($decoded === null) {
            abort(404);
        }

        $idLegajo = $decoded['legajo'];
        if (GestionAranceles::legajoParaGestion($idLegajo) === null) {
            abort(404);
        }

        $key = 'cuotas-comprobante-pago-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $id = $decoded['id'];
        $registro = ArancelesEscolares::cuotaPendienteParaAdministracion($id, $idLegajo);
        if ($registro === null) {
            abort(404, 'No se encontró la cuota pendiente solicitada.');
        }

        if (ArancelesEscolares::cuotaVencidaParaReimpresion($registro)) {
            ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::CUOTAS_GESTION, [
                'idLegajos' => $idLegajo,
                'idCuotaGenerada' => 0,
            ]);

            return redirect()
                ->route('cuotas.estudiante')
                ->with('cuotas_cuota_vencida', ArancelesEscolares::mensajeCuotaVencidaReimpresion());
        }

        $datos = ComprobantePagoDatos::paraAdministracion($id, $idLegajo);
        if ($datos === null) {
            abort(404);
        }

        $nombreCuota = trim((string) ($datos['cuotaNombre'] ?? ''));
        $slug = Str::slug(
            'comprobante-pago-'.trim(($datos['apellido'] ?? '').'-'.($datos['nombre'] ?? '').'-'.$nombreCuota),
            '_',
        );
        if ($slug === '') {
            $slug = 'comprobante_pago';
        }

        $pdf = ComprobantePagoTcpdf::generar($datos);

        return ComprobantePagoTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
