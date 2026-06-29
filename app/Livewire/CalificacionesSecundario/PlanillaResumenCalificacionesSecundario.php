<?php

namespace App\Livewire\CalificacionesSecundario;

use App\Models\Curso;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Selección de uno o más cursos para imprimir la planilla resumen de calificaciones (PDF).
 */
class PlanillaResumenCalificacionesSecundario extends Component
{
    /** IDs de curso marcados (`cursos.Id` como string). */
    public array $cursosSeleccionados = [];

    public function mount(): void
    {
        abort_unless(
            tienePermiso(PermisosIaCatalog::CALIF_PLANILLA_RESUMEN),
            403,
            'Sin permiso para planilla resumen de calificaciones.',
        );

        $this->cursosSeleccionados = [];
    }

    public function updatedCursosSeleccionados(): void
    {
        $this->normalizarCursosSeleccionados();
    }

    public function seleccionarTodosCursos(): void
    {
        $this->cursosSeleccionados = $this->cursos()
            ->pluck('Id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function quitarTodosCursos(): void
    {
        $this->cursosSeleccionados = [];
    }

    protected function normalizarCursosSeleccionados(): void
    {
        $allowed = $this->cursos()->pluck('Id')->map(fn ($id) => (int) $id)->all();

        $this->cursosSeleccionados = collect($this->cursosSeleccionados)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0 && in_array($id, $allowed, true))
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function puedeGenerarPdf(): bool
    {
        return collect($this->cursosSeleccionados)
            ->filter(fn ($v) => $v !== '' && $v !== null)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->isNotEmpty();
    }

    /**
     * @return Collection<int, mixed>
     */
    public function cursos(): Collection
    {
        $ctx = schoolCtx();

        return Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
    }

    public function etiquetaCursosSeleccionados(): string
    {
        if (! $this->puedeGenerarPdf()) {
            return '';
        }

        $ids = collect($this->cursosSeleccionados)->map(fn ($v) => (int) $v)->flip();

        return $this->cursos()
            ->filter(fn ($c) => $ids->has((int) $c->Id))
            ->map(fn ($c) => $c->nombreParaListado())
            ->implode(' · ');
    }

    public function render()
    {
        $cursos = $this->cursos();
        $etiquetaCursos = $this->etiquetaCursosSeleccionados();
        $cantidadSeleccionados = collect($this->cursosSeleccionados)
            ->filter(fn ($v) => (int) $v > 0)
            ->count();

        $pdfUrl = null;
        if ($this->puedeGenerarPdf()) {
            $ids = collect($this->cursosSeleccionados)
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            $pdfUrl = route('calificacionesSecundario.planillaResumen.pdf', [
                'cursos' => $ids->implode(','),
            ]);
        }

        return view('livewire.calificaciones-secundario.planilla-resumen-calificaciones-secundario', compact(
            'cursos',
            'etiquetaCursos',
            'cantidadSeleccionados',
            'pdfUrl',
        ))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Planilla resumen de calificaciones']);
    }
}
