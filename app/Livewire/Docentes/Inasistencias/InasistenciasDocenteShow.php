<?php

namespace App\Livewire\Docentes\Inasistencias;

use App\Support\InasistenciasDocentes;
use Livewire\Component;

class InasistenciasDocenteShow extends Component
{
    public int $idProfesor;

    public function mount(int $idProfesor): void
    {
        abort_unless(tienePermiso(InasistenciasDocentes::PERMISO_ORDEN), 403);
        abort_unless(InasistenciasDocentes::moduloDisponible(), 503);
        $this->idProfesor = $idProfesor;
        InasistenciasDocentes::profesorDelContexto($idProfesor);
    }

    public function render()
    {
        $profesor = InasistenciasDocentes::profesorDelContexto($this->idProfesor);
        $idNivel = (int) ($profesor->nivel ?? schoolCtx()->idNivel);
        $inasistencias = InasistenciasDocentes::inasistenciasAnoProfesor($this->idProfesor, $idNivel);

        return view('livewire.docentes.inasistencias.show', [
            'profesor' => $profesor,
            'inasistencias' => $inasistencias,
            'anoLectivo' => InasistenciasDocentes::anoLectivo(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Inasistencias del docente']);
    }
}
