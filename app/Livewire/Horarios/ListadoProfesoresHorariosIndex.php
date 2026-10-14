<?php

namespace App\Livewire\Horarios;

use App\Support\Horarios\ListadoProfesoresHorariosConsulta;
use App\Support\PermisosIaCatalog;
use Livewire\Component;

class ListadoProfesoresHorariosIndex extends Component
{
    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::LISTADO_PROFESORES_HORARIOS), 403);
    }

    public function render()
    {
        $resultado = ListadoProfesoresHorariosConsulta::consultar();

        return view('livewire.horarios.listado-profesores-horarios-index', [
            'resultado' => $resultado,
            'dias' => ListadoProfesoresHorariosConsulta::etiquetasDias(),
            'pdfUrl' => ($resultado['ok'] ?? false) ? route('horarios.listado-profesores.pdf') : null,
        ])->layout(layoutMenuStaff(), [
            'pageTitle' => 'Listado de Profesores con Horarios',
        ]);
    }
}
