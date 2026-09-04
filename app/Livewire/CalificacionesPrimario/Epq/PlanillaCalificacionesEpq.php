<?php

namespace App\Livewire\CalificacionesPrimario\Epq;

use App\Models\Curso;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;
use App\Support\CalificacionesPrimario\Epq\CalificacionesEpqCatalogo;
use App\Support\CalificacionesPrimario\Epq\PlanillaCalificacionesEpqDatos;
use App\Support\NivelSistema;
use App\Support\PortalDocente\CalificacionesPrimarioPortalDocente;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * EPQ — selección de curso y espacios curriculares para imprimir planilla (PDF, una hoja por materia).
 */
class PlanillaCalificacionesEpq extends Component
{
    public ?int $cursoId = null;

    /** IDs de materia marcados (`materias.id` como string). */
    public array $materiasSeleccionadas = [];

    public bool $modoPortalDocente = false;

    public function mount(): void
    {
        CalificacionesPrimarioModulos::abortSiImplementacionInactiva(
            CalificacionesPrimarioModulos::PLANILLA,
            CalificacionesEpqCatalogo::IMPLEMENTACION,
        );

        $this->modoPortalDocente = CalificacionesPrimarioPortalDocente::esPortalDocente();

        if ($this->modoPortalDocente) {
            abort_unless(
                (bool) config('tenant.portal_docente.menu.primario.planilla', false),
                404,
            );
        }

        abort_unless(
            NivelSistema::esPrimario((int) schoolCtx()->idNivel),
            403,
            'Este módulo corresponde al nivel primario.',
        );

        $this->cursoId = null;
        $this->materiasSeleccionadas = [];
    }

    public function updatedCursoId(mixed $value): void
    {
        $id = ((int) $value) > 0 ? (int) $value : null;
        if ($id !== null && $this->modoPortalDocente) {
            CalificacionesPrimarioPortalDocente::abortSiProfesorSinCurso($id);
        }
        $this->cursoId = $id;
        $this->materiasSeleccionadas = [];
    }

    public function updatedMateriasSeleccionadas(): void
    {
        $this->normalizarMateriasSeleccionadas();
    }

    public function seleccionarTodasMaterias(): void
    {
        $this->materiasSeleccionadas = $this->materiasDelCurso()
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
        $allowed = $this->materiasDelCurso()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->materiasSeleccionadas = collect($this->materiasSeleccionadas)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0 && in_array($id, $allowed, true))
            ->unique()
            ->values()
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function puedeGenerarPdf(): bool
    {
        return $this->cursoId !== null
            && collect($this->materiasSeleccionadas)
                ->filter(fn ($v) => $v !== '' && $v !== null)
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($id) => $id > 0)
                ->isNotEmpty();
    }

    /**
     * @return Collection<int, Curso>
     */
    public function cursos(): Collection
    {
        $ctx = schoolCtx();

        return Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->when($this->modoPortalDocente, fn ($q) => $q->whereIn('Id', CalificacionesPrimarioPortalDocente::idsCursosAsignados()))
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
    }

    public function materiasDelCurso(): Collection
    {
        if (! $this->cursoId) {
            return collect();
        }

        return PlanillaCalificacionesEpqDatos::materiasDelCurso(
            (int) $this->cursoId,
            $this->modoPortalDocente,
        );
    }

    public function etiquetaCursoSeleccionado(): string
    {
        if (! $this->cursoId) {
            return '';
        }

        $curso = $this->cursos()->firstWhere('Id', $this->cursoId);

        return $curso?->nombreParaListado() ?? '';
    }

    public function etiquetaMateriasSeleccionadas(): string
    {
        if (! $this->puedeGenerarPdf()) {
            return '';
        }

        $ids = collect($this->materiasSeleccionadas)->map(fn ($v) => (int) $v)->flip();

        return $this->materiasDelCurso()
            ->filter(fn ($m) => $ids->has((int) $m->id))
            ->map(fn ($m) => trim((string) $m->materia))
            ->implode(' · ');
    }

    public function render()
    {
        $cursos = $this->cursos();
        $materias = $this->materiasDelCurso();
        $etiquetaCurso = $this->etiquetaCursoSeleccionado();
        $etiquetaMaterias = $this->etiquetaMateriasSeleccionadas();
        $cantidadSeleccionados = collect($this->materiasSeleccionadas)
            ->filter(fn ($v) => (int) $v > 0)
            ->count();

        $pdfUrl = null;
        if ($this->puedeGenerarPdf()) {
            $ids = collect($this->materiasSeleccionadas)
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            $pdfUrl = CalificacionesPrimarioPortalDocente::route('planilla.pdf', [
                'materias' => $ids->implode(','),
            ]);
        }

        return view('livewire.calificaciones-primario.epq.planilla-index', compact(
            'cursos',
            'materias',
            'etiquetaCurso',
            'etiquetaMaterias',
            'cantidadSeleccionados',
            'pdfUrl',
        ))->layout(CalificacionesPrimarioPortalDocente::layout(), ['pageTitle' => 'Planilla de calificaciones']);
    }
}
