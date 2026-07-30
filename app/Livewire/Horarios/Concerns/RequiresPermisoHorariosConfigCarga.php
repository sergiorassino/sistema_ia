<?php

namespace App\Livewire\Horarios\Concerns;

use App\Support\PermisosIaCatalog;

trait RequiresPermisoHorariosConfigCarga
{
    public function bootRequiresPermisoHorariosConfigCarga(): void
    {
        abort_unless(
            tienePermiso(PermisosIaCatalog::HORARIOS),
            403,
            'Sin permiso para configurar o cargar horarios.',
        );
    }
}
