<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\ResumenBecasPorNivelConsulta;
use App\Support\PermisosCuotas;
use Livewire\Component;

/**
 * Resumen de becas otorgadas por tipo y nivel pedagógico — perfil Administración.
 */
class ResumenBecasPorNivelIndex extends Component
{
    public bool $modalDetalleAbierto = false;

    public int $detalleIdBeca = 0;

    /** 0 = todos los niveles (columna TOTAL) */
    public int $detalleIdNivel = 0;

    public string $detalleTitulo = '';

    /** @var list<array{curso: string, nivel: string, etiqueta: string, cantidad: int, alumnos: list<array{alumno: string, dni: string}>}> */
    public array $detalleGrupos = [];

    public int $detalleTotalAlumnos = 0;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeResumenBecasPorNivel(), 403, 'Sin permiso para resumen de becas por nivel.');
    }

    public function abrirDetalle(int $idBeca, int $idNivel = 0): void
    {
        abort_unless(PermisosCuotas::puedeResumenBecasPorNivel(), 403);

        $idNivelFiltro = $idNivel > 0 ? $idNivel : null;
        $grupos = ResumenBecasPorNivelConsulta::detallePorCurso($idBeca, $idNivelFiltro);

        if ($grupos === []) {
            return;
        }

        $nombreBeca = ResumenBecasPorNivelConsulta::etiquetaBeca($idBeca);
        $nombreNivel = ResumenBecasPorNivelConsulta::etiquetaNivel($idNivelFiltro);

        $this->detalleIdBeca = $idBeca;
        $this->detalleIdNivel = $idNivel;
        $this->detalleTitulo = "{$nombreBeca} · {$nombreNivel}";
        $this->detalleGrupos = $grupos;
        $this->detalleTotalAlumnos = array_sum(array_column($grupos, 'cantidad'));
        $this->modalDetalleAbierto = true;
    }

    public function cerrarDetalle(): void
    {
        $this->modalDetalleAbierto = false;
        $this->reset('detalleIdBeca', 'detalleIdNivel', 'detalleTitulo', 'detalleGrupos', 'detalleTotalAlumnos');
    }

    public function render()
    {
        $ano = (int) schoolCtx()->terlecAno();
        $resumen = ResumenBecasPorNivelConsulta::resumen();

        return view('livewire.cuotas.resumen-becas-por-nivel', [
            'ano' => $ano,
            'filas' => $resumen['filas'],
            'niveles' => $resumen['niveles'],
            'totalesNivel' => $resumen['totalesNivel'],
            'totalGeneral' => $resumen['totalGeneral'],
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Resumen de Becas por Nivel — {$ano}"]);
    }
}
