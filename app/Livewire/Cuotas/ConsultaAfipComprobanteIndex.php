<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\ConsultaAfipComprobanteService;
use App\Support\PermisosCuotas;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Consulta en AFIP de facturas y notas de crédito por número de comprobante.
 */
class ConsultaAfipComprobanteIndex extends Component
{
    public string $tipo = ConsultaAfipComprobanteService::TIPO_FACTURA;

    public string $numeroComprobante = '';

    /** @var array<string, mixed>|null */
    public ?array $resultadoAfip = null;

    /** @var array<string, mixed>|null */
    public ?array $registroLocal = null;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeConsultaAfipComprobante(), 404);
    }

    public function consultar(): void
    {
        abort_unless(PermisosCuotas::puedeConsultaAfipComprobante(), 403);

        $key = 'cuotas:consulta-afip:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 15)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiadas consultas. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate([
            'tipo' => 'required|in:'.ConsultaAfipComprobanteService::TIPO_FACTURA.','.ConsultaAfipComprobanteService::TIPO_NOTA_CREDITO,
            'numeroComprobante' => 'required|string|max:20',
        ], [
            'tipo.required' => 'Seleccione el tipo de comprobante.',
            'tipo.in' => 'Tipo de comprobante inválido.',
            'numeroComprobante.required' => 'Ingrese el número de comprobante.',
        ]);

        $this->resultadoAfip = null;
        $this->registroLocal = null;

        $respuesta = ConsultaAfipComprobanteService::consultar($this->tipo, $this->numeroComprobante);
        if (! $respuesta['ok']) {
            $this->dispatch('se-swal-error', mensaje: $respuesta['mensaje']);

            return;
        }

        $this->resultadoAfip = $respuesta['datos'] ?? null;
        $this->registroLocal = $respuesta['registro_local'] ?? null;

        if (! empty($this->resultadoAfip['simulado'])) {
            $this->dispatch('se-swal-aviso', mensaje: $respuesta['mensaje']);
        }
    }

    public function limpiar(): void
    {
        $this->reset(['resultadoAfip', 'registroLocal']);
        $this->numeroComprobante = '';
        $this->tipo = ConsultaAfipComprobanteService::TIPO_FACTURA;
    }

    public function render()
    {
        $config = tenantCuotasFacturacionAfipConfig();

        return view('livewire.cuotas.consulta-afip-comprobante-index', [
            'simulado' => (bool) ($config['simular'] ?? false),
            'ptoVtaInstitucion' => $this->ptoVtaInstitucion(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Consulta AFIP']);
    }

    private function ptoVtaInstitucion(): ?int
    {
        $ento = \App\Models\Ento::query()
            ->where('idNivel', (int) schoolCtx()->idNivel)
            ->value('ptoVta');

        $pto = (int) ($ento ?? 0);

        return $pto > 0 ? $pto : null;
    }
}
