<?php

namespace App\Livewire\Concerns;

use App\Support\PermisosMatriculaWeb;

trait RequiresPermisoMatriculaWeb
{
    /** Orden en permisos_ia (ver {@see PermisosMatriculaWeb}). */
    abstract protected function permisoMatriculaWebOrden(): int;

    public function bootRequiresPermisoMatriculaWeb(): void
    {
        abort_unless(
            PermisosMatriculaWeb::tiene($this->permisoMatriculaWebOrden()),
            403,
            'Sin permiso para esta opción de matrícula web.'
        );
    }
}
