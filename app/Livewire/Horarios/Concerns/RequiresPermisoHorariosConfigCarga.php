<?php

namespace App\Livewire\Horarios\Concerns;

trait RequiresPermisoHorariosConfigCarga
{
    public function bootRequiresPermisoHorariosConfigCarga(): void
    {
        abort_unless(tienePermiso(13), 403, 'Sin permiso para configurar o cargar horarios.');
    }
}
