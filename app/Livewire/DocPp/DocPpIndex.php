<?php

namespace App\Livewire\DocPp;

use App\Support\DocPp\DocPpConsulta;
use App\Support\PermisosIaCatalog;
use Livewire\Component;
use Livewire\WithPagination;

class DocPpIndex extends Component
{
    use WithPagination;

    public const POR_PAGINA = 50;

    public string $busqueda = '';

    public string $orden = DocPpConsulta::ORDEN_CURSO;

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::PLANIFICACIONES_PROGRAMAS), 403);
        abort_unless(tenantDocPpHabilitado(), 404);
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
        $tablaOk = DocPpConsulta::tablaDisponible();

        $filas = collect();
        if ($tablaOk) {
            $filas = DocPpConsulta::listadoPaginado(
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec,
                $this->busqueda,
                $this->orden,
                self::POR_PAGINA,
            );
        }

        return view('livewire.doc-pp.index', [
            'filas' => $filas,
            'tablaDisponible' => $tablaOk,
        ]);
    }
}
