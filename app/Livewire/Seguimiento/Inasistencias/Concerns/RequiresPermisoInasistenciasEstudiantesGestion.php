<?php

namespace App\Livewire\Seguimiento\Inasistencias\Concerns;

use App\Support\PermisosIaCatalog;

trait RequiresPermisoInasistenciasEstudiantesGestion
{
    public function bootRequiresPermisoInasistenciasEstudiantesGestion(): void
    {
        abort_unless(
            tienePermiso(PermisosIaCatalog::INASISTENCIAS_ESTUDIANTES_GESTION),
            403,
            'Sin permiso para gestión de inasistencias de estudiantes.'
        );
    }
}
