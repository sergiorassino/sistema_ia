<?php

namespace App\Livewire\Mora;

use App\Support\Mora\EstadoDeudaFamiliarDatos;
use App\Support\Mora\EstadoDeudaFamiliarListado;
use App\Support\Mora\EstadoDeudaListadoFiltros;
use App\Support\Mora\PermisosMora;
use App\Support\Security\OpaqueRouteToken;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de familias — Gestión de mora (Administración).
 */
class EstadoDeudaFamiliarIndex extends Component
{
    use WithPagination;

    public string $search = '';

    /** Vacío = todos los niveles pedagógicos del alcance. */
    public string $idNivel = '';

    public bool $soloConDeuda = false;

    /** @var array<string, array{except?: mixed, as?: string}> */
    protected $queryString = [
        'search' => ['except' => '', 'as' => 'buscar'],
        'idNivel' => ['except' => '', 'as' => 'filtro_nivel'],
        'soloConDeuda' => ['except' => false, 'as' => 'solo_deuda'],
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

    public function updatedIdNivel(): void
    {
        $this->idNivel = $this->idNivelNormalizadoParaVista();
        $this->resetPage();
    }

    public function updatedSoloConDeuda(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->idNivel = $this->idNivelNormalizadoParaVista();
        $idNivel = $this->idNivel === '' ? 0 : (int) $this->idNivel;
        $familias = EstadoDeudaFamiliarListado::listarFamilias($this->search, $idNivel, $this->soloConDeuda);
        $idsFamilias = $familias->getCollection()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('livewire.mora.estado-deuda-familiar-index', [
            'familias' => $familias,
            'niveles' => EstadoDeudaFamiliarListado::nivelesParaSelector(),
            'totalesDeuda' => EstadoDeudaFamiliarDatos::totalesAPagarPorFamilias($idsFamilias),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Estado de Deuda Familiar']);
    }

    private function idNivelNormalizadoParaVista(): string
    {
        $id = EstadoDeudaFamiliarListado::normalizarIdNivel((int) $this->idNivel);

        return $id > 0 ? (string) $id : '';
    }

    public function urlListadoPdf(): string
    {
        return route('mora.estado-deuda-familiar.listado-pdf', [
            'ref' => OpaqueRouteToken::forEstadoDeudaFamiliarListadoPdf($this->filtrosExportacion()->aPayload()),
        ]);
    }

    public function urlListadoExcel(): string
    {
        return route('mora.estado-deuda-familiar.listado-excel', [
            'ref' => OpaqueRouteToken::forEstadoDeudaFamiliarListadoExcel($this->filtrosExportacion()->aPayload()),
        ]);
    }

    private function filtrosExportacion(): EstadoDeudaListadoFiltros
    {
        return EstadoDeudaListadoFiltros::desdeLivewire($this->search, $this->idNivel, $this->soloConDeuda);
    }
}
