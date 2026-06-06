<?php

namespace App\Livewire\Mora;

use App\Support\Mora\EstadoDeudaFamiliarDatos;
use App\Support\Mora\EstadoDeudaFamiliarListado;
use App\Support\Mora\PermisosMora;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de familias — Gestión de mora (Administración).
 */
class EstadoDeudaFamiliarIndex extends Component
{
    use WithPagination;

    public string $search = '';

    /** @var array<string, array{except?: mixed, as?: string}> */
    protected $queryString = [
        'search' => ['except' => '', 'as' => 'buscar'],
    ];

    public function mount(): void
    {
        abort_unless(
            PermisosMora::puedeEstadoDeudaFamiliar(),
            403,
            'Sin permiso para estado de deuda familiar.',
        );
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $familias = EstadoDeudaFamiliarListado::listarFamilias($this->search);
        $idsFamilias = $familias->getCollection()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('livewire.mora.estado-deuda-familiar-index', [
            'familias' => $familias,
            'totalesDeuda' => EstadoDeudaFamiliarDatos::totalesAPagarPorFamilias($idsFamilias),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Estado de Deuda Familiar']);
    }
}
