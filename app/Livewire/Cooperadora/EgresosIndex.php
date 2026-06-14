<?php

namespace App\Livewire\Cooperadora;

use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\ProfesorMenuPortal;
use Livewire\Component;
use Livewire\WithPagination;

class EgresosIndex extends Component
{
    use WithPagination;

    public string $fechaDesde = '';

    public string $fechaHasta = '';

    public function mount(): void
    {
        abort_unless(PermisosCooperadora::puedeEgresos(), 403);
        $this->fechaDesde = now()->startOfMonth()->format('Y-m-d');
        $this->fechaHasta = now()->format('Y-m-d');
    }

    public function updatedFechaDesde(): void
    {
        $this->resetPage();
    }

    public function updatedFechaHasta(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = \App\Models\CoopEgreso::query()
            ->with(['proveedor:id,nombre'])
            ->where('anulado', false)
            ->orderByDesc('fecha')
            ->orderByDesc('orden_numero');

        if ($this->fechaDesde !== '') {
            $query->where('fecha', '>=', $this->fechaDesde);
        }
        if ($this->fechaHasta !== '') {
            $query->where('fecha', '<=', $this->fechaHasta);
        }

        return view('livewire.cooperadora.egresos-index', [
            'egresos' => $query->paginate(25),
        ])->layout(ProfesorMenuPortal::layoutStaff(), ['pageTitle' => 'Cooperadora — Egresos']);
    }
}
