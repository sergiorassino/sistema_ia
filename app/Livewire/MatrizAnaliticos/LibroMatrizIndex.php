<?php

namespace App\Livewire\MatrizAnaliticos;

use App\Support\MatrizAnaliticos\LibroMatrizAnalitico;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Libro matriz / pase / analítico — listado de legajos.
 */
class LibroMatrizIndex extends Component
{
    use WithPagination;

    public string $buscar = '';

    /** @var array<string, array{except?: mixed}> */
    protected $queryString = [
        'buscar' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(tienePermiso(16), 403, 'Sin permiso para Libro Matriz / Analítico.');
        $ctx = schoolCtx();
        if (! str_contains(mb_strtolower($ctx->nivelNombre()), 'secundari')) {
            abort(403, 'Este módulo requiere contexto de Secundario.');
        }

        LibroMatrizAnalitico::persistirBuscarListado($this->buscar);
    }

    public function updatedBuscar(): void
    {
        LibroMatrizAnalitico::persistirBuscarListado($this->buscar);
        $this->resetPage();
    }

    public function render()
    {
        $alumnos = LibroMatrizAnalitico::paginarLegajos($this->buscar, 50);

        return view('livewire.matriz-analiticos.libro-matriz-index', [
            'alumnos' => $alumnos,
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Libro Matriz / Pase / Analítico']);
    }
}
