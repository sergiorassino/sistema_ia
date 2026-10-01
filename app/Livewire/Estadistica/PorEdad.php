<?php

namespace App\Livewire\Estadistica;

use App\Support\Estadistica\EstadisticaPorEdadDatos;
use App\Support\NivelSistema;
use App\Support\PermisosIaCatalog;
use Carbon\Carbon;
use Livewire\Component;

/**
 * Planilla de alumnos regulares por edad, del nivel de la sesión.
 */
class PorEdad extends Component
{
    public string $fecha = '';

    public function mount(): void
    {
        $this->autorizar();
        $this->fecha = Carbon::today()->format('Y-m-d');
    }

    public function getPdfUrlProperty(): string
    {
        $fecha = EstadisticaPorEdadDatos::parseFecha($this->fecha);
        if ($fecha === null) {
            return '#';
        }

        return route('estadistica.porEdad.pdf', [
            'fecha' => $fecha->format('Y-m-d'),
        ]);
    }

    public function render()
    {
        $this->autorizar();

        $fecha = EstadisticaPorEdadDatos::parseFecha($this->fecha);
        $datos = $fecha !== null ? EstadisticaPorEdadDatos::build($fecha) : null;
        $ano = (int) ($datos['ano'] ?? schoolCtx()->terlecAno());

        return view('livewire.estadistica.por-edad', [
            'datos' => $datos,
            'ano' => $ano,
            'pdfUrl' => $this->pdfUrl,
        ])->layout(layoutMenuStaff(), [
            'pageTitle' => 'Estadísticas por edad — '.$ano,
        ]);
    }

    private function autorizar(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ESTADISTICA_POR_EDAD), 403);
        abort_unless(NivelSistema::esNivelPedagogico((int) (schoolCtx()->idNivel ?? 0)), 403);
    }
}
