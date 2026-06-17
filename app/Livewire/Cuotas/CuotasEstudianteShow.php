<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\GestionAranceles;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\PermisosCuotas;
use Livewire\Component;

/**
 * Listado de cuotas del estudiante: vista normal (año actual + impagas anteriores) o historial completo.
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
        $cuotas = $this->mostrarHistorial
                ? GestionAranceles::cuotasHistorial($this->idLegajo)
                : GestionAranceles::cuotasDelEstudiante($this->idLegajo);

        return view('livewire.cuotas.estudiante-show', [
            'encabezado' => GestionAranceles::encabezadoEstudiante($this->idLegajo),
            'cuotas' => $cuotas,
            'totalesAdeudados' => GestionAranceles::totalizarSaldosAdeudados($cuotas),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Gestión de aranceles']);
    }
}
