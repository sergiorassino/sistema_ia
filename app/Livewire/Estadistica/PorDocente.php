<?php

namespace App\Livewire\Estadistica;

use App\Livewire\Estadistica\Concerns\RequiresPermisoEstadisticaRendimiento;
use App\Support\Estadistica\AprobacionEstadistica;
use App\Support\Estadistica\EstadisticaRendimientoConsulta;
use Livewire\Component;

class PorDocente extends Component
{
    use RequiresPermisoEstadisticaRendimiento;

    public int $profesorId = 0;

    public function mount(): void
    {
        $this->autorizarEstadisticaRendimiento();
        $this->profesorId = (int) request()->query('profesorId', 0);
    }

    public function limpiarFiltros(): void
    {
        $this->profesorId = 0;
    }

    public function render()
    {
        $idTerlec = $this->idTerlecContexto();
        $servicio = new AprobacionEstadistica;

        $filas = $idTerlec > 0
            ? $servicio->estadisticasPorDocente($idTerlec, $this->profesorId > 0 ? $this->profesorId : null)
            : [];

        $porProfesor = [];
        foreach ($filas as $r) {
            $idP = $r['idProfesor'];
            if (! isset($porProfesor[$idP])) {
                $porProfesor[$idP] = [
                    'apellido' => $r['apellido'],
                    'nombre' => $r['nombre'],
                    'materias' => [],
                    'total' => 0,
                    'durante_anio' => 0,
                    'diciembre' => 0,
                    'febrero' => 0,
                    'pendientes' => 0,
                ];
            }
            $porProfesor[$idP]['materias'][] = $r;
            $porProfesor[$idP]['total'] += $r['total'];
            $porProfesor[$idP]['durante_anio'] += $r['durante_anio'];
            $porProfesor[$idP]['diciembre'] += $r['diciembre'];
            $porProfesor[$idP]['febrero'] += $r['febrero'];
            $porProfesor[$idP]['pendientes'] += $r['pendientes'];
        }

        $chartComparativa = EstadisticaRendimientoConsulta::porcentajesApilados(
            array_values($porProfesor),
            fn ($b) => trim(($b['apellido'] ?? '').', '.($b['nombre'] ?? '')),
        );

        return view('livewire.estadistica.por-docente', [
            'idTerlec' => $idTerlec,
            'anoLabel' => EstadisticaRendimientoConsulta::anoLabel($idTerlec),
            'profesores' => $idTerlec > 0 ? EstadisticaRendimientoConsulta::profesores($idTerlec) : collect(),
            'porProfesor' => $porProfesor,
            'chartComparativa' => $chartComparativa,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Estadística por docente']);
    }
}
