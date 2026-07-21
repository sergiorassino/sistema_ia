<?php

namespace App\Livewire\Seguimiento\Tea\Concerns;

use App\Support\PermisosIaCatalog;

trait RequiresPermisoTeaGestion
{
    public function bootRequiresPermisoTeaGestion(): void
    {
        abort_unless(
            tienePermiso(PermisosIaCatalog::TEA_ESTUDIANTES_GESTION),
            403,
            'Sin permiso para gestión de TEA de estudiantes.'
        );
    }
}
