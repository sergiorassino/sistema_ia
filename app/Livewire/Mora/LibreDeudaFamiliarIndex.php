<?php

namespace App\Livewire\Mora;

use App\Support\Mora\EstadoDeudaEstudianteDatos;
use App\Support\Mora\EstadoDeudaEstudianteListado;
use App\Support\Mora\LibreDeudaFamiliarDatos;
use App\Support\Mora\LibreDeudaFamiliarListado;
use App\Support\Mora\PermisosMora;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado para emitir la constancia de libre deuda — Gestión de mora.
 */
class LibreDeudaFamiliarIndex extends Component
{
    use WithPagination;

    public string $search = '';

    /** Vacío = todos los niveles pedagógicos del alcance. */
    public string $idNivel = '';

    public bool $soloSinDeuda = false;

    /** @var array<string, array{except?: mixed, as?: string}> */
    protected $queryString = [
        'search' => ['except' => '', 'as' => 'buscar'],
        'idNivel' => ['except' => '', 'as' => 'filtro_nivel'],
        'soloSinDeuda' => ['except' => false, 'as' => 'sin_deuda'],
    ];

    public function mount(): void
    {
        abort_unless(
            PermisosMora::puedeLibreDeudaFamiliar(),
            403,
            'Sin permiso para libre deuda familiar.',
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

    public function updatedSoloSinDeuda(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->idNivel = $this->idNivelNormalizadoParaVista();
        $idNivel = $this->idNivel === '' ? 0 : (int) $this->idNivel;
        $estudiantes = LibreDeudaFamiliarListado::listarEstudiantes($this->search, $idNivel, $this->soloSinDeuda);
        $idsLegajos = $estudiantes->getCollection()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('livewire.mora.libre-deuda-familiar-index', [
            'estudiantes' => $estudiantes,
            'niveles' => EstadoDeudaEstudianteListado::nivelesParaSelector(),
            'totalesDeuda' => EstadoDeudaEstudianteDatos::totalesAPagarPorLegajos($idsLegajos),
            'idsConDeuda' => LibreDeudaFamiliarDatos::idsConDeuda($idsLegajos),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Libre Deuda Familiar']);
    }

    private function idNivelNormalizadoParaVista(): string
    {
        $id = EstadoDeudaEstudianteListado::normalizarIdNivel((int) $this->idNivel);

        return $id > 0 ? (string) $id : '';
    }
}
