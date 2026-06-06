<?php

namespace App\Livewire\SolicitudEvaluacion\Concerns;

use App\Support\PermisosIaCatalog;
use App\Support\SolicitudEvaluacion\SolicitudEvaluacionConsulta;

trait RequiresPermisoGestionSolicitudEvaluacion
{
    public function bootRequiresPermisoGestionSolicitudEvaluacion(): void
    {
        SolicitudEvaluacionConsulta::abortSiNoHabilitadoEnTenant();
        SolicitudEvaluacionConsulta::abortSiNoEsSecundario();

        abort_unless(
            tienePermiso(PermisosIaCatalog::SOLICITUDES_EVALUACION_GESTION),
            403,
            'Sin permiso para gestión de solicitudes de evaluación.',
        );
    }
}
