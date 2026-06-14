<?php

namespace App\Livewire\Cooperadora;

use App\Models\CoopMedioPago;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class MediosPagoIndex extends Component
{
    public bool $showModal = false;

    public ?int $editId = null;

    public string $nombre = '';

    public string $orden = '0';

    public bool $activo = true;

    public function mount(): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);
    }

    public function abrirNuevo(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function abrirEditar(int $id): void
    {
        $medio = CoopMedioPago::query()->findOrFail($id);
        $this->editId = $id;
        $this->nombre = (string) $medio->nombre;
        $this->orden = (string) $medio->orden;
        $this->activo = (bool) $medio->activo;
        $this->showModal = true;
    }

    public function cerrarModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function guardar(): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);

        $key = 'coop:medios-pago:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $validated = $this->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'orden' => ['required', 'integer', 'min:0', 'max:999'],
            'activo' => ['boolean'],
        ]);

        $data = [
            'nombre' => trim($validated['nombre']),
            'orden' => (int) $validated['orden'],
            'activo' => (bool) $validated['activo'],
        ];

        if ($this->editId) {
            CoopMedioPago::query()->whereKey($this->editId)->update($data);
        } else {
            CoopMedioPago::query()->create($data);
        }

        $this->cerrarModal();
    }

    public function eliminar(int $id): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);

        $key = 'coop:medios-pago-del:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $medio = CoopMedioPago::query()->findOrFail($id);
        $medio->delete();

        if ($this->editId === $id) {
            $this->cerrarModal();
        }
    }

    private function resetForm(): void
    {
        $this->editId = null;
        $this->nombre = '';
        $this->orden = '0';
        $this->activo = true;
    }

    public function render()
    {
        $medios = CoopMedioPago::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('livewire.cooperadora.medios-pago-index', compact('medios'))
            ->layout(ProfesorMenuPortal::layoutStaff(), ['pageTitle' => 'Cooperadora — Medios de pago']);
    }
}
