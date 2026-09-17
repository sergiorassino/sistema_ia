<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\CuotasPlantillaCatalog;
use App\Support\Cuotas\EstadisticaPagoCuotasDatos;
use App\Support\PermisosCuotas;
use Livewire\Component;

/**
 * Gráficos de porcentaje de pago por plantilla de cuota del ciclo activo.
 */
class EstadisticaPagoCuotasIndex extends Component
{
    /** @var list<string> */
    public array $cuotasSeleccionadas = [];

    /** 0 = todos los niveles del alcance */
    public int $idNivel = 0;

    public string $filtroCuotas = '';

    public bool $mostrarResultados = false;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeEstadisticaPagoCuotas(), 403);
    }

    public function updatedCuotasSeleccionadas(): void
    {
        $this->mostrarResultados = false;
    }

    public function updatedIdNivel(): void
    {
        $this->mostrarResultados = false;
    }

    public function seleccionarTodasCuotas(): void
    {
        abort_unless(PermisosCuotas::puedeEstadisticaPagoCuotas(), 403);

        $this->cuotasSeleccionadas = EstadisticaPagoCuotasDatos::cuotasDelCicloParaSelector()
            ->map(fn ($cuota) => (string) (int) $cuota->id)
            ->values()
            ->all();
        $this->mostrarResultados = false;
        $this->resetErrorBag('cuotasSeleccionadas');
    }

    public function quitarTodasCuotas(): void
    {
        $this->cuotasSeleccionadas = [];
        $this->mostrarResultados = false;
    }

    public function limpiar(): void
    {
        $this->cuotasSeleccionadas = [];
        $this->idNivel = 0;
        $this->filtroCuotas = '';
        $this->mostrarResultados = false;
        $this->resetErrorBag();
    }

    public function graficar(): void
    {
        abort_unless(PermisosCuotas::puedeEstadisticaPagoCuotas(), 403);

        $ids = $this->idsCuotasValidados();
        if ($ids === []) {
            $this->mostrarResultados = false;
            $this->addError('cuotasSeleccionadas', 'Elegí al menos una cuota para graficar.');
            $this->dispatch('se-swal-error', mensaje: 'Elegí al menos una cuota para graficar.');

            return;
        }

        $this->resetErrorBag('cuotasSeleccionadas');
        $this->mostrarResultados = true;
    }

    public function render()
    {
        $idTerlec = CuotasPlantillaCatalog::idTerlecActivo();
        $ano = (int) schoolCtx()->terlecAno();
        $plantillas = $idTerlec > 0
            ? EstadisticaPagoCuotasDatos::cuotasDelCicloParaSelector()
            : collect();

        $filas = [];
        if ($this->mostrarResultados && $idTerlec > 0) {
            $filas = EstadisticaPagoCuotasDatos::build($this->idsCuotasValidados(), $this->idNivel);
        }

        $chartBarras = $filas !== []
            ? EstadisticaPagoCuotasDatos::chartBarrasComparativo($filas)
            : ['labels' => [], 'datasets' => []];

        return view('livewire.cuotas.estadistica-pago-cuotas', [
            'ano' => $ano,
            'idTerlec' => $idTerlec,
            'plantillas' => $plantillas,
            'niveles' => EstadisticaPagoCuotasDatos::nivelesParaSelector(),
            'cantidadSeleccionadas' => count($this->idsCuotasValidados()),
            'filas' => $filas,
            'chartBarras' => $chartBarras,
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Estadística de pago de cuotas — {$ano}"]);
    }

    /**
     * @return list<int>
     */
    private function idsCuotasValidados(): array
    {
        $permitidos = EstadisticaPagoCuotasDatos::cuotasDelCicloParaSelector()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $permitidosFlip = array_flip($permitidos);

        $ids = [];
        foreach ($this->cuotasSeleccionadas as $id) {
            $id = (int) $id;
            if ($id > 0 && isset($permitidosFlip[$id])) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
