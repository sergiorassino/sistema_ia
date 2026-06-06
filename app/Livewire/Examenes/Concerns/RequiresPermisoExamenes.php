<?php

namespace App\Livewire\Examenes\Concerns;

trait RequiresPermisoExamenes
{
    public function bootRequiresPermisoExamenes(): void
    {
        abort_unless(tienePermiso(12), 403, 'Sin permiso para el módulo de exámenes.');
    }
}
