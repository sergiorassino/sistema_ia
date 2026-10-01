<?php

namespace App\Livewire\Estadistica;

use App\Support\Estadistica\EstadisticaSexoCursoDatos;
use Livewire\Component;

class PorSexoYCurso extends Component
{
    public function mount(): void
    {
        EstadisticaSexoCursoDatos::asegurarAcceso();
    }

    public function render()
    {
        return view('livewire.estadistica.por-sexo-y-curso', [
            'datos' => EstadisticaSexoCursoDatos::armar(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Estadística por Sexo y Curso']);
    }
}
