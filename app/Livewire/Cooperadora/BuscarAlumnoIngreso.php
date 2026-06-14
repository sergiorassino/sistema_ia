<?php

namespace App\Livewire\Cooperadora;

use App\Support\Cooperadora\BusquedaEstudianteCooperadora;
use Livewire\Component;

/**
 * Búsqueda y selección de alumno para el ingreso cooperadora (subcomponente aislado).
 */
class BuscarAlumnoIngreso extends Component
{
    public string $search = '';

    /** Si el padre ya tiene un alumno en carga, no mostrar resultados de búsqueda. */
    public ?int $idLegajoActivo = null;

    public function seleccionar(int $id): void
    {
        if (BusquedaEstudianteCooperadora::legajo($id) === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró el estudiante seleccionado.');

            return;
        }

        $this->search = '';
        $this->dispatch('coop-alumno-elegido', id: $id);
    }

    public function render()
    {
        $legajos = (! $this->idLegajoActivo && trim($this->search) !== '')
            ? BusquedaEstudianteCooperadora::buscarLegajos($this->search)
            : null;

        return view('livewire.cooperadora.buscar-alumno-ingreso', [
            'legajos' => $legajos,
        ]);
    }
}
