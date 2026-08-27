<?php

namespace App\Livewire\Mora;

use App\Support\Mora\EstadoDeudaEstudianteDatos;
use App\Support\Mora\EstadoDeudaEstudianteListado;
use App\Support\Mora\PermisosMora;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de estudiantes — Gestión de mora (Administración).
 */
class EstadoDeudaEstudianteIndex extends Component
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
            PermisosMora::puedeEstadoDeudaEstudiante(),
            403,
            'Sin permiso para estado de deuda por estudiante.',
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
        $estudiantes = EstadoDeudaEstudianteListado::listarEstudiantes($this->search, $idNivel, $this->soloConDeuda);
        $idsLegajos = $estudiantes->getCollection()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('livewire.mora.estado-deuda-estudiante-index', [
            'estudiantes' => $estudiantes,
            'niveles' => EstadoDeudaEstudianteListado::nivelesParaSelector(),
            'totalesDeuda' => EstadoDeudaEstudianteDatos::totalesAPagarPorLegajos($idsLegajos),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Estado de Deuda por Estudiante']);
    }

    private function idNivelNormalizadoParaVista(): string
    {
        $id = EstadoDeudaEstudianteListado::normalizarIdNivel((int) $this->idNivel);

        return $id > 0 ? (string) $id : '';
    }
}
