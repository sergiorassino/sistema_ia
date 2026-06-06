<?php

namespace App\Livewire\Seguimiento\Disciplinario\Concerns;

use App\Support\PermisosIaCatalog;

trait RequiresPermisoSeguimientoDisciplinario
{
    public function bootRequiresPermisoSeguimientoDisciplinario(): void
    {
        abort_unless(
            tienePermiso(PermisosIaCatalog::SEGUIMIENTO_DISCIPLINARIO),
            403,
            'Sin permiso para seguimiento disciplinario.'
        );
    }
}
