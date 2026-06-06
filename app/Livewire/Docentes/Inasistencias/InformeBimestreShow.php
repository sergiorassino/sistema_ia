<?php

namespace App\Livewire\Docentes\Inasistencias;

use App\Support\InasistenciasDocentes;
use App\Support\InasistenciasDocentes\CalculoFaltasDescuento;
use Livewire\Component;

class InformeBimestreShow extends Component
{
    public int $idProfesor;

    public int $bimestre;

    public int $anio;

    public function mount(int $idProfesor, int $bimestre): void
    {
        abort_unless(tienePermiso(InasistenciasDocentes::PERMISO_ORDEN), 403);
        abort_unless(InasistenciasDocentes::moduloDisponible(), 503);
        abort_unless($bimestre >= 1 && $bimestre <= 6, 404);

        $this->idProfesor = $idProfesor;
        $this->bimestre = $bimestre;
        $this->anio = (int) request()->query('anio', InasistenciasDocentes::anoLectivo());
        InasistenciasDocentes::profesorDelContexto($idProfesor);
    }

    public function render()
    {
        $profesor = InasistenciasDocentes::profesorDelContexto($this->idProfesor);
        $rango = InasistenciasDocentes::rangoBimestre($this->bimestre, $this->anio);
        $resumen = CalculoFaltasDescuento::resumenBimestre($this->idProfesor, $this->bimestre, $this->anio);
        $inasistencias = InasistenciasDocentes::inasistenciasBimestrePorProfesor($this->idProfesor, $this->bimestre, $this->anio);

        return view('livewire.docentes.inasistencias.informe', [
            'profesor' => $profesor,
            'bimestre' => $this->bimestre,
            'bimestreInfo' => InasistenciasDocentes::BIMESTRES[$this->bimestre],
            'anio' => $this->anio,
            'rango' => $rango,
            'resumen' => $resumen,
            'inasistencias' => $inasistencias,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Informe de inasistencias']);
    }
}
