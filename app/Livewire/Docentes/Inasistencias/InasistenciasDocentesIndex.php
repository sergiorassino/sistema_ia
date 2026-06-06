<?php

namespace App\Livewire\Docentes\Inasistencias;

use App\Support\InasistenciasDocentes;
use App\Support\InasistenciasDocentes\CalculoFaltasDescuento;
use Livewire\Component;

class InasistenciasDocentesIndex extends Component
{
    public string $search = '';

    public function mount(): void
    {
        abort_unless(tienePermiso(InasistenciasDocentes::PERMISO_ORDEN), 403);
        abort_unless(InasistenciasDocentes::moduloDisponible(), 503, 'El módulo de inasistencias docentes no está disponible en esta base de datos.');
    }

    public function render()
    {
        $anoLectivo = InasistenciasDocentes::anoLectivo();
        $profesores = InasistenciasDocentes::queryDocentesIndex($this->search)->get();

        $cargosPorProfesor = [];
        $resumenPorProfesor = [];
        foreach ($profesores as $p) {
            $id = (int) $p->id;
            $cargosPorProfesor[$id] = InasistenciasDocentes::cargosConHorasPorProfesor($id);
            $resumenPorProfesor[$id] = [];
            for ($b = 1; $b <= 6; $b++) {
                $resumenPorProfesor[$id][$b] = CalculoFaltasDescuento::resumenBimestre($id, $b, $anoLectivo);
            }
        }

        return view('livewire.docentes.inasistencias.index', [
            'profesores' => $profesores,
            'cargosPorProfesor' => $cargosPorProfesor,
            'resumenPorProfesor' => $resumenPorProfesor,
            'bimestres' => InasistenciasDocentes::BIMESTRES,
            'anoLectivo' => $anoLectivo,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Inasistencias docentes']);
    }
}
