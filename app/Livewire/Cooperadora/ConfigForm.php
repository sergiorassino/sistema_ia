<?php

namespace App\Livewire\Cooperadora;

use App\Models\CoopConfig;
use App\Support\Cooperadora\CooperadoraConfig;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class ConfigForm extends Component
{
    public string $nombreInstitucion = '';

    public string $direccion = '';

    public string $localidad = '';

    public string $telefono = '';

    public string $descuentoHermanoPct = '0';

    public function mount(): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);
        $cfg = CooperadoraConfig::registro();
        $this->nombreInstitucion = (string) $cfg->nombre_institucion;
        $this->direccion = (string) $cfg->direccion;
        $this->localidad = (string) $cfg->localidad;
        $this->telefono = (string) $cfg->telefono;
        $this->descuentoHermanoPct = (string) $cfg->descuento_hermano_pct;
    }

    public function guardar(): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);

        $key = 'coop:config:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $validated = $this->validate([
            'nombreInstitucion' => ['required', 'string', 'max:200'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'localidad' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:80'],
            'descuentoHermanoPct' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $cfg = CooperadoraConfig::registro();
        CoopConfig::query()->whereKey($cfg->id)->update([
            'nombre_institucion' => trim($validated['nombreInstitucion']),
            'direccion' => trim((string) ($validated['direccion'] ?? '')),
            'localidad' => trim((string) ($validated['localidad'] ?? '')),
            'telefono' => trim((string) ($validated['telefono'] ?? '')),
            'descuento_hermano_pct' => round((float) $validated['descuentoHermanoPct'], 2),
        ]);
    }

    public function render()
    {
        return view('livewire.cooperadora.config-form')
            ->layout(ProfesorMenuPortal::layoutStaff(), ['pageTitle' => 'Cooperadora — Configuración']);
    }
}
