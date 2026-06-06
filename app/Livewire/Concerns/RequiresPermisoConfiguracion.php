<?php

namespace App\Livewire\Concerns;

use App\Support\PermisosConfiguracion;

trait RequiresPermisoConfiguracion
{
    /** Orden en permisos_ia (ver {@see PermisosConfiguracion}). */
    abstract protected function permisoConfigOrden(): int;

    public function bootRequiresPermisoConfiguracion(): void
    {
        abort_unless(
            PermisosConfiguracion::tiene($this->permisoConfigOrden()),
            403,
            'Sin permiso para esta opción de configuración.'
        );
    }
}
