<?php

namespace App\Livewire\Cooperadora;

use App\Support\Cooperadora\AnularMovimientoCooperadora;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Facades\RateLimiter;
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

    public function anular(int $idEgreso): void
    {
        abort_unless(PermisosCooperadora::puedeEgresos(), 403);

        $key = 'coop:anular-egreso:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente de nuevo.');

            return;
        }
        RateLimiter::hit($key, 60);

        if (! AnularMovimientoCooperadora::egreso($idEgreso)) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo anular el egreso.');

            return;
        }

        $this->dispatch('se-swal-exito', mensaje: 'Egreso anulado. El registro permanece en el listado pero no afecta los saldos.');
    }

    public function render()
    {
        $query = \App\Models\CoopEgreso::query()
            ->with(['proveedor:id,nombre'])
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
