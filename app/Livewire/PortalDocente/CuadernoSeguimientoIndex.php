<?php

namespace App\Livewire\PortalDocente;

use App\Support\PortalDocente\CuadernoSeguimientoAulicoDocente;
use Livewire\Component;

/**
 * Listado de materias a cargo (ppc) — Cuaderno de Seguimiento Áulico.
 */
class CuadernoSeguimientoIndex extends Component
{
    public function mount(): void
    {
        CuadernoSeguimientoAulicoDocente::abortSiNoHabilitadoEnTenant();
        CuadernoSeguimientoAulicoDocente::abortSiNoEsSecundario();
    }

    public function render()
    {
        $idProfesor = (int) (schoolCtx()->idProfesor ?? 0);
        $materias = CuadernoSeguimientoAulicoDocente::materiasAsignadas($idProfesor);

        return view('livewire.portal-docente.cuaderno-seguimiento-index', [
            'materias' => $materias,
        ])->layout('layouts.docente', ['pageTitle' => 'Cuaderno de Seguimiento Áulico']);
    }
}
