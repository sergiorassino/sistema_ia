<?php

namespace App\Livewire\Cooperadora;

use App\Models\CoopProveedor;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class ProveedoresForm extends Component
{
    public ?int $id = null;

    public string $nombre = '';

    public string $cuit = '';

    public string $telefono = '';

    public string $email = '';

    public string $direccion = '';

    public string $observaciones = '';

    public bool $activo = true;

    public function mount(?int $id = null): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);
        $this->id = $id;
        if ($id) {
            $p = CoopProveedor::query()->findOrFail($id);
            $this->nombre = (string) $p->nombre;
            $this->cuit = (string) ($p->cuit ?? '');
            $this->telefono = (string) ($p->telefono ?? '');
            $this->email = (string) ($p->email ?? '');
            $this->direccion = (string) ($p->direccion ?? '');
            $this->observaciones = (string) ($p->observaciones ?? '');
            $this->activo = (bool) $p->activo;
        }
    }

    public function guardar(): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);

        $key = 'coop:proveedor:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 15)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $validated = $this->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'cuit' => ['nullable', 'string', 'max:20'],
            'telefono' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'activo' => ['boolean'],
        ]);

        $data = [
            'nombre' => trim($validated['nombre']),
            'cuit' => trim((string) ($validated['cuit'] ?? '')) ?: null,
            'telefono' => trim((string) ($validated['telefono'] ?? '')) ?: null,
            'email' => trim((string) ($validated['email'] ?? '')) ?: null,
            'direccion' => trim((string) ($validated['direccion'] ?? '')) ?: null,
            'observaciones' => trim((string) ($validated['observaciones'] ?? '')) ?: null,
            'activo' => (bool) $validated['activo'],
        ];

        if ($this->id) {
            CoopProveedor::query()->whereKey($this->id)->update($data);
        } else {
            CoopProveedor::query()->create($data);
        }

        session()->flash('success', 'Proveedor guardado.');
        $this->redirect(route('cooperadora.proveedores'), navigate: true);
    }

    public function render()
    {
        return view('livewire.cooperadora.proveedores-form')
            ->layout(ProfesorMenuPortal::layoutStaff(), [
                'pageTitle' => $this->id ? 'Editar proveedor' : 'Nuevo proveedor',
            ]);
    }
}
