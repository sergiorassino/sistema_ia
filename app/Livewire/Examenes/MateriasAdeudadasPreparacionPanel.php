<?php

namespace App\Livewire\Examenes;

use App\Livewire\Examenes\Concerns\PreparaMateriasAdeudadasExamenes;
use App\Livewire\Examenes\Concerns\RequiresPermisoExamenes;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use Livewire\Component;

/**
 * Panel de preparación (turno, año lectivo, recálculo). Componente hijo con su propio ámbito Livewire
 * para que wire:submit / wire:click funcionen de forma fiable dentro del layout.
 */
class MateriasAdeudadasPreparacionPanel extends Component
{
    use PreparaMateriasAdeudadasExamenes;
    use RequiresPermisoExamenes;

    /** @var self::MODULO_* */
    public string $modulo = MateriasAdeudadasPreparacion::MODULO_LISTADO;

    public function mount(string $modulo): void
    {
        $this->modulo = $modulo;
        $this->configurarEntradaPreparacionMateriasAdeudadas();
    }

    protected function moduloMateriasAdeudadas(): string
    {
        return $this->modulo;
    }

    protected function siempreMostrarSelectsPreparacion(): bool
    {
        return $this->modulo === MateriasAdeudadasPreparacion::MODULO_GESTION;
    }

    protected function usarValoresMinimosPreparacionPorDefecto(): bool
    {
        return $this->modulo !== MateriasAdeudadasPreparacion::MODULO_GESTION;
    }

    public function confirmarPreparacionMateriasAdeudadas(): void
    {
        $this->asegurarCatalogosPreparacionCargados();
        $this->ejecutarConfirmarPreparacion($this->modulo);
        $this->dispatch('materias-adeudadas-preparacion-confirmada', modulo: $this->modulo);
    }

    public function render()
    {
        return view(
            'livewire.examenes.materias-adeudadas-preparacion-panel',
            array_merge($this->datosVistaPreparacion(), ['modulo' => $this->modulo]),
        );
    }
}
