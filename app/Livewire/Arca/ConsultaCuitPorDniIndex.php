<?php

namespace App\Livewire\Arca;

use App\Support\Arca\ConsultaCuitPorDniService;
use App\Support\PermisosArca;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Consulta CUIT/CUIL en ARCA a partir de un DNI.
 */
class ConsultaCuitPorDniIndex extends Component
{
    public string $dni = '';

    /** @var list<string> */
    public array $cuits = [];

    public bool $simulado = false;

    public function mount(): void
    {
        abort_unless(PermisosArca::puedeConsultaCuitPorDni(), 404);
    }

    public function consultar(): void
    {
        abort_unless(PermisosArca::puedeConsultaCuitPorDni(), 403);

        $key = 'arca:consulta-cuit-dni:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 15)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiadas consultas. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate([
            'dni' => 'required|string|max:12',
        ], [
            'dni.required' => 'Ingrese el DNI a consultar.',
        ]);

        $this->cuits = [];
        $this->simulado = false;

        $respuesta = ConsultaCuitPorDniService::consultar($this->dni);
        if (! $respuesta['ok']) {
            $this->dispatch('se-swal-error', mensaje: $respuesta['mensaje']);

            return;
        }

        $this->cuits = $respuesta['cuits'] ?? [];
        $this->simulado = (bool) ($respuesta['simulado'] ?? false);

        if ($this->simulado) {
            $this->dispatch('se-swal-aviso', mensaje: $respuesta['mensaje']);
        }
    }

    public function limpiar(): void
    {
        $this->reset(['cuits', 'simulado']);
        $this->dni = '';
    }

    public function render()
    {
        return view('livewire.arca.consulta-cuit-por-dni-index', [
            'modoSimulacion' => (bool) (tenantArcaPadronConfig()['simular'] ?? false),
            'configurado' => tenantArcaPadronConfig() !== null,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Consulta CUIT por DNI']);
    }
}
