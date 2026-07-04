<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\ComprobantesAfipCuotaService;
use App\Support\Cuotas\GestionAranceles;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\PermisosCuotas;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Comprobantes AFIP (facturas y notas de crédito) de un pago imputado.
 */
class ComprobantesAfipCuota extends Component
{
    public int $idLegajo;

    public int $idCuotaGenerada;

    public int $idCuotaPago;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);
        abort_unless(ComprobantesAfipCuotaService::moduloDisponible(), 404);

        $idLegajo = ContextoEstudianteSesion::legajo(ContextoEstudianteSesion::CUOTAS_GESTION);
        $idCuotaGenerada = ContextoEstudianteSesion::cuotaGenerada(ContextoEstudianteSesion::CUOTAS_GESTION);
        $idCuotaPago = ContextoEstudianteSesion::cuotaPago(ContextoEstudianteSesion::CUOTAS_GESTION);
        $enDevengamiento = tenantCuotasFacturacionAfipEnDevengamiento();

        $cuotaValida = $idLegajo !== null
            && $idCuotaGenerada !== null
            && GestionAranceles::legajoParaGestion($idLegajo) !== null
            && GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo) !== null;

        if (! $cuotaValida) {
            abort(404);
        }

        if (! $enDevengamiento) {
            abort_if(
                $idCuotaPago === null
                || ComprobantesAfipCuotaService::pagoParaGestion($idCuotaPago, $idLegajo, $idCuotaGenerada) === null,
                404,
            );
        }

        $this->idLegajo = $idLegajo;
        $this->idCuotaGenerada = $idCuotaGenerada;
        $this->idCuotaPago = (int) ($idCuotaPago ?? 0);
    }

    public function generarFactura(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $key = 'cuotas:comprobantes-afip:factura:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $resultado = ComprobantesAfipCuotaService::generarFactura(
            $this->idCuotaPago,
            $this->idLegajo,
            $this->idCuotaGenerada,
        );
        if (! $resultado['ok']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['mensaje']);

            return;
        }

        $this->dispatch('se-swal-exito', mensaje: $resultado['mensaje']);
        $this->abrirPdfSiCorresponde($resultado);
    }

    public function emitirNotaCredito(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $key = 'cuotas:comprobantes-afip:nc:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $resultado = ComprobantesAfipCuotaService::emitirNotaCredito(
            $this->idCuotaPago,
            $this->idLegajo,
            $this->idCuotaGenerada,
        );
        if (! $resultado['ok']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['mensaje']);

            return;
        }

        $this->dispatch('se-swal-exito', mensaje: $resultado['mensaje']);
        $this->abrirPdfSiCorresponde($resultado);
    }

    /**
     * @param  array{ok: bool, mensaje: string, idComprobanteAfip?: int}  $resultado
     */
    private function abrirPdfSiCorresponde(array $resultado): void
    {
        $idComprobante = (int) ($resultado['idComprobanteAfip'] ?? 0);
        if ($idComprobante <= 0) {
            return;
        }

        $url = se_route_url('cuotas.comprobante-afip', [
            'ref' => OpaqueRouteToken::forComprobanteAfipRegistro($idComprobante, $this->idLegajo),
        ]);
        $this->dispatch('cuotas-comprobantes-afip-abrir-pdf', url: $url);
    }

    public function render()
    {
        $registro = GestionAranceles::cuotaDelLegajo($this->idCuotaGenerada, $this->idLegajo);
        $pago = ComprobantesAfipCuotaService::pagoParaGestion(
            $this->idCuotaPago,
            $this->idLegajo,
            $this->idCuotaGenerada,
        );
        $puedeFacturar = ComprobantesAfipCuotaService::puedeGenerarFactura(
            $this->idCuotaPago,
            $this->idLegajo,
            $this->idCuotaGenerada,
        );
        $puedeNc = ComprobantesAfipCuotaService::puedeEmitirNotaCredito(
            $this->idCuotaPago,
            $this->idLegajo,
            $this->idCuotaGenerada,
        );

        $facturaVigente = tenantCuotasFacturacionAfipEnDevengamiento()
            ? ComprobantesAfipCuotaService::facturaVigentePorCuotaGenerada($this->idCuotaGenerada)
            : ComprobantesAfipCuotaService::facturaVigente($this->idCuotaPago);

        return view('livewire.cuotas.comprobantes-afip-cuota', [
            'registro' => $registro,
            'pago' => $pago,
            'encabezado' => GestionAranceles::encabezadoEstudiante($this->idLegajo),
            'comprobantes' => ComprobantesAfipCuotaService::comprobantes(
                $this->idCuotaPago,
                $this->idLegajo,
                $this->idCuotaGenerada,
            ),
            'puedeGenerarFactura' => $puedeFacturar['ok'],
            'mensajeFactura' => $puedeFacturar['mensaje'],
            'puedeNotaCredito' => $puedeNc['ok'],
            'mensajeNotaCredito' => $puedeNc['mensaje'],
            'facturaVigente' => $facturaVigente,
            'enDevengamiento' => tenantCuotasFacturacionAfipEnDevengamiento(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Comprobantes AFIP']);
    }
}
