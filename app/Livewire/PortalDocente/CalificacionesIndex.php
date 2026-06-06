<?php

namespace App\Livewire\PortalDocente;

use App\Support\PortalDocente\CalificacionesDocenteSecundario;
use Livewire\Component;

/**
 * Listado de materias a cargo (ppc) para carga de calificaciones — solo secundario.
 */
class CalificacionesIndex extends Component
{
    public function mount(): void
    {
        CalificacionesDocenteSecundario::abortSiNoEsSecundario();
    }

    public function render()
    {
        $idProfesor = (int) (schoolCtx()->idProfesor ?? 0);
        $materias = CalificacionesDocenteSecundario::materiasAsignadas($idProfesor);

        return view('livewire.portal-docente.calificaciones-index', [
            'materias' => $materias,
        ])->layout('layouts.docente', ['pageTitle' => 'Calificaciones']);
    }
}
