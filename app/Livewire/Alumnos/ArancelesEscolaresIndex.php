<?php

namespace App\Livewire\Alumnos;

use App\Support\Alumnos\ArancelesEscolares;
use Livewire\Component;

class ArancelesEscolaresIndex extends Component
{
    public function mount(): void
    {
        abort_unless(tenantAutogestionArancelesEscolaresHabilitada(), 404);
    }

    public function render()
    {
        return view('livewire.alumnos.aranceles-escolares-index', [
            'cuotas' => ArancelesEscolares::cuotasPendientes(),
            'encabezado' => ArancelesEscolares::encabezadoAutogestion(),
        ])->layout('layouts.alumno', ['pageTitle' => 'Aranceles Escolares']);
    }
}
