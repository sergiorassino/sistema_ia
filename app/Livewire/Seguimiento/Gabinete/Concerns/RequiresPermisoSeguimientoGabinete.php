<?php

namespace App\Livewire\Seguimiento\Gabinete\Concerns;

use App\Support\PermisosIaCatalog;

trait RequiresPermisoSeguimientoGabinete
{
    public function bootRequiresPermisoSeguimientoGabinete(): void
    {
        abort_unless(
            tienePermiso(PermisosIaCatalog::SEGUIMIENTO_GABINETE),
            403,
            'Sin permiso para seguimiento de gabinete de orientación.'
        );
    }
}
