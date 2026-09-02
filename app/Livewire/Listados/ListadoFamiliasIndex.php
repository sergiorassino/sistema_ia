<?php

namespace App\Livewire\Listados;

use App\Support\Listados\ListadoFamiliasConsulta;
use App\Support\Listados\ListadoFamiliasFiltros;
use App\Support\Security\OpaqueRouteToken;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de familias con estudiantes del ciclo lectivo activo.
 */
class ListadoFamiliasIndex extends Component
{
    use WithPagination;

    public string $search = '';

    /** Vacío = todos los niveles del alcance (en Administración). */
    public string $idNivel = '';

    /** @var array<string, array{except?: mixed, as?: string}> */
    protected $queryString = [
        'search' => ['except' => '', 'as' => 'buscar'],
        'idNivel' => ['except' => '', 'as' => 'filtro_nivel'],
    ];

    public function mount(): void
    {
        abort_unless(puedeConsultarLegajosEstudiantes(), 403);
        abort_unless((int) schoolCtx()->idTerlec > 0, 403);
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

    public function render()
    {
        $this->idNivel = $this->idNivelNormalizadoParaVista();
        $idNivel = $this->idNivel === '' ? 0 : (int) $this->idNivel;
        $familias = ListadoFamiliasConsulta::listar($this->search, $idNivel);

        return view('listados::livewire.listados.familias-index', [
            'familias' => $familias,
            'niveles' => ListadoFamiliasConsulta::nivelesParaSelector(),
            'mostrarFiltroNivel' => schoolEsAdministracion(),
            'tieneDniResp' => ListadoFamiliasConsulta::tieneDniResp(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Listado de familias']);
    }

    public function urlListadoPdf(): string
    {
        return route('listados.familias.pdf', [
            'ref' => OpaqueRouteToken::forListadoFamiliasPdf($this->filtrosExportacion()->aPayload()),
        ]);
    }

    public function urlListadoExcel(): string
    {
        return route('listados.familias.excel', [
            'ref' => OpaqueRouteToken::forListadoFamiliasExcel($this->filtrosExportacion()->aPayload()),
        ]);
    }

    private function filtrosExportacion(): ListadoFamiliasFiltros
    {
        return ListadoFamiliasFiltros::desdeLivewire($this->search, $this->idNivel);
    }

    private function idNivelNormalizadoParaVista(): string
    {
        if (! schoolEsAdministracion()) {
            return '';
        }

        $id = ListadoFamiliasConsulta::normalizarIdNivel((int) $this->idNivel);

        return $id > 0 ? (string) $id : '';
    }
}
