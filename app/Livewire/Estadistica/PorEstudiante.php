<?php

namespace App\Livewire\Estadistica;

use App\Livewire\Estadistica\Concerns\RequiresPermisoEstadisticaRendimiento;
use App\Support\Estadistica\AprobacionEstadistica;
use App\Support\Estadistica\EstadisticaRendimientoConsulta;
use Livewire\Component;

class PorEstudiante extends Component
{
    use RequiresPermisoEstadisticaRendimiento;

    public int $cursoId = 0;

    public int $legajoId = 0;

    public function mount(): void
    {
        $this->autorizarEstadisticaRendimiento();
        $this->cursoId = (int) request()->query('cursoId', 0);
        $this->legajoId = (int) request()->query('legajoId', 0);
    }

    public function limpiarFiltros(): void
    {
        $this->cursoId = 0;
        $this->legajoId = 0;
    }

    public function render()
    {
        $idTerlec = $this->idTerlecContexto();
        $servicio = new AprobacionEstadistica;

        $filtroCurso = $this->cursoId > 0 ? $this->cursoId : null;
        $filtroLegajo = $this->legajoId > 0 ? $this->legajoId : null;

        $reporte = $idTerlec > 0
            ? $servicio->reportePorEstudiante($idTerlec, $filtroCurso, $filtroLegajo)
            : null;

        $resumen = $reporte['resumen'] ?? null;
        $porEstudiante = $reporte['por_estudiante'] ?? [];

        $idsLegajos = array_column($porEstudiante, 'idLegajos');
        $inasPorLegajo = EstadisticaRendimientoConsulta::inasistenciasPorLegajo($idTerlec, $filtroCurso, $idsLegajos);
        $previasPorLegajo = EstadisticaRendimientoConsulta::tienePreviasPorLegajo($idTerlec, $idsLegajos);
        $matriculaPorLegajo = EstadisticaRendimientoConsulta::matriculaPorLegajo($idTerlec, $idsLegajos);

        $chartBarras = EstadisticaRendimientoConsulta::porcentajesApilados(
            $porEstudiante,
            fn ($r) => trim(($r['apellido'] ?? '').', '.($r['nombre'] ?? '')),
        );

        return view('livewire.estadistica.por-estudiante', [
            'idTerlec' => $idTerlec,
            'anoLabel' => EstadisticaRendimientoConsulta::anoLabel($idTerlec),
            'cursos' => $idTerlec > 0 ? EstadisticaRendimientoConsulta::cursos($idTerlec) : collect(),
            'alumnos' => $idTerlec > 0 ? EstadisticaRendimientoConsulta::alumnos($idTerlec) : collect(),
            'resumen' => $resumen,
            'porEstudiante' => $porEstudiante,
            'inasPorLegajo' => $inasPorLegajo,
            'previasPorLegajo' => $previasPorLegajo,
            'matriculaPorLegajo' => $matriculaPorLegajo,
            'pctResumen' => $resumen ? EstadisticaRendimientoConsulta::porcentajesResumen($resumen) : [0, 0, 0, 0],
            'chartBarras' => $chartBarras,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Estadística por estudiante']);
    }
}
