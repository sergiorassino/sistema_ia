<?php

namespace App\Livewire\Estadistica;

use App\Livewire\Estadistica\Concerns\RequiresPermisoEstadisticaRendimiento;
use App\Support\Estadistica\AprobacionEstadistica;
use App\Support\Estadistica\EstadisticaRendimientoConsulta;
use App\Support\NivelSistema;
use Livewire\Component;

class PorMateria extends Component
{
    use RequiresPermisoEstadisticaRendimiento;

    public string $materiaCurso = '0';

    public int $cursoId = 0;

    public function mount(): void
    {
        $this->autorizarEstadisticaRendimiento();
        $this->materiaCurso = (string) request()->query('materiaCurso', '0');
        $this->cursoId = (int) request()->query('cursoId', 0);
    }

    public function limpiarFiltros(): void
    {
        $this->materiaCurso = '0';
        $this->cursoId = 0;
    }

    public function render()
    {
        $idTerlec = $this->idTerlecContexto();
        $servicio = new AprobacionEstadistica;

        [$filtroMateria, $filtroCursoMc] = $this->parseMateriaCurso();
        $filtroCurso = $this->cursoId > 0 ? $this->cursoId : ($filtroCursoMc > 0 ? $filtroCursoMc : null);
        $filtroMateria = $filtroMateria > 0 ? $filtroMateria : null;

        $reporte = $idTerlec > 0
            ? $servicio->reportePorMateria($idTerlec, $filtroMateria, $filtroCurso, NivelSistema::SECUNDARIO)
            : null;

        $resumen = $reporte['resumen'] ?? null;
        $porMateriaCurso = ($filtroMateria === null) ? ($reporte['por_materia_curso'] ?? []) : [];

        $chartBarras = EstadisticaRendimientoConsulta::porcentajesApilados(
            $porMateriaCurso,
            fn ($r) => trim(($r['curso'] ?? '').' — '.($r['materia'] ?? '')),
        );

        return view('livewire.estadistica.por-materia', [
            'idTerlec' => $idTerlec,
            'anoLabel' => EstadisticaRendimientoConsulta::anoLabel($idTerlec),
            'cursos' => $idTerlec > 0 ? EstadisticaRendimientoConsulta::cursos($idTerlec) : collect(),
            'materiasCursos' => $idTerlec > 0 ? EstadisticaRendimientoConsulta::materiasCursos($idTerlec) : collect(),
            'resumen' => $resumen,
            'porMateriaCurso' => $porMateriaCurso,
            'pctResumen' => $resumen ? EstadisticaRendimientoConsulta::porcentajesResumen($resumen) : [0, 0, 0, 0],
            'chartBarras' => $chartBarras,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Estadística por materias']);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseMateriaCurso(): array
    {
        if ($this->materiaCurso === '0' || $this->materiaCurso === '') {
            return [0, 0];
        }
        if (str_contains($this->materiaCurso, '-')) {
            [$m, $c] = array_map('intval', explode('-', $this->materiaCurso, 2));

            return [$m, $c];
        }

        return [(int) $this->materiaCurso, 0];
    }
}
