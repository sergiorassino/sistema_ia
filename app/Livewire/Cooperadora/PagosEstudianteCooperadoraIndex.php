<?php

namespace App\Livewire\Cooperadora;

use App\Support\Cooperadora\BusquedaEstudianteCooperadora;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\ProfesorMenuPortal;
use Livewire\Component;
use Livewire\WithPagination;

class PagosEstudianteCooperadoraIndex extends Component
{
    use WithPagination;

    public string $search = '';

    /** @var array<string, array{except?: mixed, as?: string}> */
    protected $queryString = [
        'search' => ['except' => '', 'as' => 'buscar'],
    ];

    public function mount(): void
    {
        abort_unless(PermisosCooperadora::puedeIngresos(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $legajos = trim($this->search) !== ''
            ? BusquedaEstudianteCooperadora::buscarLegajos($this->search)
            : null;

        return view('livewire.cooperadora.pagos-estudiante-index', [
            'legajos' => $legajos,
            'anoCiclo' => BusquedaEstudianteCooperadora::etiquetaAnioCiclo(),
        ])->layout(ProfesorMenuPortal::layoutStaff(), ['pageTitle' => 'Cooperadora — Pagos por estudiante']);
    }
}
