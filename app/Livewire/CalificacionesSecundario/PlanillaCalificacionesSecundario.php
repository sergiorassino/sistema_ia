<?php

namespace App\Livewire\CalificacionesSecundario;

use App\Models\Curso;
use App\Support\PlanillaCalificacionesSecundario as PlanillaCalificacionesPdf;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Selección de curso y una o más materias para imprimir la planilla de calificaciones (PDF).
 */
class PlanillaCalificacionesSecundario extends Component
{
    public ?int $cursoId = null;

    /** IDs de materia marcados (`materias.id` como string). */
    public array $materiasSeleccionadas = [];

    public function mount(): void
    {
        $this->cursoId = null;
        $this->materiasSeleccionadas = [];
    }

    public function updatedCursoId($value): void
    {
        $this->cursoId = ((int) $value) > 0 ? (int) $value : null;
        $this->materiasSeleccionadas = [];
    }

    public function updatedMateriasSeleccionadas(): void
    {
        $this->normalizarMateriasSeleccionadas();
    }

    public function seleccionarTodasMaterias(): void
    {
        $this->materiasSeleccionadas = $this->materias()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function quitarTodasMaterias(): void
    {
        $this->materiasSeleccionadas = [];
    }

    protected function normalizarMateriasSeleccionadas(): void
    {
        $allowed = $this->materias()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->materiasSeleccionadas = collect($this->materiasSeleccionadas)
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
        return ($this->cursoId ?? 0) > 0 && collect($this->materiasSeleccionadas)
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

    /**
     * @return Collection<int, object>
     */
    public function materias(): Collection
    {
        if (! $this->cursoId) {
            return collect();
        }

        return PlanillaCalificacionesPdf::materiasDelCurso((int) $this->cursoId);
    }

    public function etiquetaMateriasSeleccionadas(): string
    {
        if (! $this->puedeGenerarPdf()) {
            return '';
        }

        $ids = collect($this->materiasSeleccionadas)->map(fn ($v) => (int) $v)->flip();

        return $this->materias()
            ->filter(fn ($m) => $ids->has((int) $m->id))
            ->map(fn ($m) => trim((string) ($m->materia ?? '')) !== '' ? $m->materia : ('ID '.$m->id))
            ->implode(' · ');
    }

    public function render()
    {
        $cursos = $this->cursos();
        $materias = $this->materias();

        $cursoLabel = $this->cursoId
            ? optional($cursos->firstWhere('Id', (int) $this->cursoId))->nombreParaListado()
            : null;

        $etiquetaMaterias = $this->etiquetaMateriasSeleccionadas();
        $cantidadMateriasSeleccionadas = collect($this->materiasSeleccionadas)
            ->filter(fn ($v) => (int) $v > 0)
            ->count();

        $pdfUrl = null;
        if ($this->puedeGenerarPdf()) {
            $ids = collect($this->materiasSeleccionadas)
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            $pdfUrl = route('calificacionesSecundario.planilla.pdf', [
                'curso' => $this->cursoId,
                'materias' => $ids->implode(','),
            ]);
        }

        return view('livewire.calificaciones-secundario.planilla-calificaciones-secundario', compact(
            'cursos',
            'materias',
            'cursoLabel',
            'etiquetaMaterias',
            'cantidadMateriasSeleccionadas',
            'pdfUrl',
        ))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Planilla de calificaciones']);
    }
}
