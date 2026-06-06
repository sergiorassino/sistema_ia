<?php

namespace App\Livewire\Docentes\Inasistencias;

use App\Support\InasistenciasDocentes;
use App\Support\InasistenciasDocentes\RankingMateriasCursos as RankingSupport;
use Livewire\Component;

class RankingInasistenciasMateriasCursos extends Component
{
    public int $anio;

    public int $periodo = 0;

    public string $sort = 'total';

    public string $dir = 'DESC';

    public function mount(): void
    {
        abort_unless(tienePermiso(InasistenciasDocentes::PERMISO_ORDEN), 403);
        abort_unless(InasistenciasDocentes::moduloDisponible(), 503);

        $anios = RankingSupport::aniosDisponibles();
        $this->anio = (int) request()->query('anio', InasistenciasDocentes::anoLectivo());
        if ($anios->isNotEmpty() && ! $anios->contains($this->anio)) {
            $this->anio = (int) $anios->first();
        }

        $this->periodo = (int) request()->query('periodo', 0);
        $this->sort = (string) request()->query('sort', 'total');
        $this->dir = strtoupper((string) request()->query('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
    }

    public function ordenar(string $col): void
    {
        if ($this->sort === $col && $this->dir === 'DESC') {
            $this->dir = 'ASC';
        } else {
            $this->sort = $col;
            $this->dir = 'DESC';
        }
    }

    public function render()
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        $idTerlec = (int) (schoolCtx()->idTerlec ?? 0);
        $datos = RankingSupport::datos($this->anio, $this->periodo, $this->sort, $this->dir, $idNivel, $idTerlec);

        return view('livewire.docentes.inasistencias.ranking', [
            'filas' => $datos['filas'],
            'chart' => $datos['chart'],
            'tieneDetalle' => $datos['tieneDetalle'],
            'anios' => RankingSupport::aniosDisponibles(),
            'bimestres' => InasistenciasDocentes::BIMESTRES,
            'periodoLabel' => $this->periodo >= 1 && $this->periodo <= 6
                ? (InasistenciasDocentes::BIMESTRES[$this->periodo]['titulo'] ?? '')
                : null,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Ranking inasistencias — Materia y curso']);
    }
}
