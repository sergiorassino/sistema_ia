<?php

namespace App\Livewire\PlanificacionesProgramas;

use App\Support\PermisosIaCatalog;
use App\Support\PlanificacionesProgramas\PlanificacionesProgramasConsulta;
use Livewire\Component;
use Livewire\WithPagination;

class PlanificacionesProgramasIndex extends Component
{
    use WithPagination;

    public const POR_PAGINA = 50;

    public string $busqueda = '';

    public string $orden = PlanificacionesProgramasConsulta::ORDEN_CURSO;

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::PLANIFICACIONES_PROGRAMAS), 403);
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatedOrden(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $ctx = schoolCtx();
        $columnasFaltantes = PlanificacionesProgramasConsulta::columnasFaltantes();

        $filas = collect();
        if ($columnasFaltantes === []) {
            $filas = PlanificacionesProgramasConsulta::listadoPaginado(
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec,
                $this->busqueda,
                $this->orden,
                self::POR_PAGINA,
            );
        }

        return view('livewire.planificaciones-programas.index', [
            'filas' => $filas,
            'columnasFaltantes' => $columnasFaltantes,
        ]);
    }
}
