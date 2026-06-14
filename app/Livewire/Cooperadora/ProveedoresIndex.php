<?php

namespace App\Livewire\Cooperadora;

use App\Models\CoopProveedor;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\ProfesorMenuPortal;
use Livewire\Component;
use Livewire\WithPagination;

class ProveedoresIndex extends Component
{
    use WithPagination;

    public string $buscar = '';

    public bool $soloActivos = true;

    public function mount(): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedSoloActivos(): void
    {
        $this->resetPage();
    }

    public function toggleActivo(int $id): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);

        $proveedor = CoopProveedor::query()->findOrFail($id);
        $proveedor->update(['activo' => ! $proveedor->activo]);
    }

    public function render()
    {
        $query = CoopProveedor::query()->orderBy('nombre');
        if ($this->soloActivos) {
            $query->where('activo', true);
        }
        $filtro = trim($this->buscar);
        if ($filtro !== '') {
            $query->where(function ($q) use ($filtro) {
                $q->where('nombre', 'like', "%{$filtro}%")
                    ->orWhere('cuit', 'like', "%{$filtro}%");
            });
        }

        return view('livewire.cooperadora.proveedores-index', [
            'proveedores' => $query->paginate(20),
        ])->layout(ProfesorMenuPortal::layoutStaff(), ['pageTitle' => 'Cooperadora — Proveedores']);
    }
}
