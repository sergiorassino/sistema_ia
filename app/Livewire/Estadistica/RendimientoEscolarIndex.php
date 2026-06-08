<?php

namespace App\Livewire\Estadistica;

use App\Livewire\Estadistica\Concerns\RequiresPermisoEstadisticaRendimiento;
use Livewire\Component;

class RendimientoEscolarIndex extends Component
{
    use RequiresPermisoEstadisticaRendimiento;

    public function mount(): void
    {
        $this->autorizarEstadisticaRendimiento();
    }

    public function render()
    {
        return view('livewire.estadistica.rendimiento-index')
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Estadística de Rendimiento Escolar']);
    }
}
