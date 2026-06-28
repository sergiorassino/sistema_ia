<?php

namespace App\Livewire\Alumnos;

use App\Support\Alumnos\ArancelesEscolares;
use Livewire\Component;

/**
 * Gestión de aranceles — portal familia (variante UI legacy / Banco Roela).
 *
 * Misma lógica de datos que {@see ArancelesEscolaresIndex}; estética distinta.
 * Activar con `autogestion.aranceles_escolares.implementacion` = `gestion_aranceles`.
 */
class ArancelesEscolaresGestionIndex extends Component
{
    public function mount(): void
    {
        abort_unless(tenantAutogestionArancelesEscolaresHabilitada(), 404);
        abort_unless(tenantAutogestionArancelesEscolaresImplementacion() === 'gestion_aranceles', 404);
    }

    public function render()
    {
        $cuotas = ArancelesEscolares::cuotasPendientes();

        return view('livewire.alumnos.aranceles-escolares-gestion-index', [
            'cuotas' => $cuotas,
            'encabezado' => ArancelesEscolares::encabezadoAutogestion(),
            'botonPagosUrl' => tenantArancelesEscolaresBotonPagosUrl(),
        ])->layout('layouts.alumno', [
            'pageTitle' => tenantAutogestionArancelesEscolaresMenuEtiqueta(),
        ]);
    }
}
