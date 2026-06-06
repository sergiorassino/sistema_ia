<?php

namespace App\Livewire\SolicitudEvaluacion\Concerns;

use App\Support\SolicitudEvaluacion\SolicitudEvaluacionConsulta;

trait RequiresSolicitudEvaluacion
{
    /**
     * Fijado en mount() desde la ruta HTTP inicial.
     * En peticiones Livewire (wire:click) request()->routeIs() no es fiable.
     */
    public bool $solicitudEvaluacionPortalDocente = false;

    public function bootRequiresSolicitudEvaluacion(): void
    {
        SolicitudEvaluacionConsulta::abortSiNoHabilitadoEnTenant();
        SolicitudEvaluacionConsulta::abortSiNoEsSecundario();
    }

    protected function fijarOrigenPortalSolicitudEvaluacion(): void
    {
        $this->solicitudEvaluacionPortalDocente = request()->routeIs('portalDocente.solicitudEvaluacion*');
    }

    protected function esPortalDocente(): bool
    {
        return $this->solicitudEvaluacionPortalDocente;
    }
}
