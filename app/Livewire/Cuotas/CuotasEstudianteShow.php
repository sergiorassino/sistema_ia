<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\GestionAranceles;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\PermisosCuotas;
use Livewire\Component;

/**
 * Listado de cuotas del estudiante: ciclo activo o historial de abonadas (todos los años).
 */
class CuotasEstudianteShow extends Component
{
    public int $idLegajo;

    public bool $mostrarHistorial = false;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $idLegajo = ContextoEstudianteSesion::legajo(ContextoEstudianteSesion::CUOTAS_GESTION);
        abort_if($idLegajo === null || GestionAranceles::legajoParaGestion($idLegajo) === null, 404);

        $this->idLegajo = $idLegajo;
    }

    public function alternarVistaCuotas(): void
    {
        $this->mostrarHistorial = ! $this->mostrarHistorial;
    }

    public function render()
    {
        return view('livewire.cuotas.estudiante-show', [
            'encabezado' => GestionAranceles::encabezadoEstudiante($this->idLegajo),
            'cuotas' => $this->mostrarHistorial
                ? GestionAranceles::cuotasAbonadasHistorial($this->idLegajo)
                : GestionAranceles::cuotasDelEstudiante($this->idLegajo),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Gestión de aranceles']);
    }
}
