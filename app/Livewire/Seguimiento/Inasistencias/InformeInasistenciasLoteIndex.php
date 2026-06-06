<?php

namespace App\Livewire\Seguimiento\Inasistencias;

use App\Models\Curso;
use App\Models\Matricula;
use App\Support\InformeInasistenciasLoteParams;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Informe de inasistencias por curso: elige curso, marca estudiantes y genera PDF en lote.
 */
class InformeInasistenciasLoteIndex extends Component
{
    /** Curso confirmado (`cursos.Id`); null = paso de selección de curso. */
    public ?int $cursoId = null;

    /** ID de matrícula marcados (`matriculas.id` como string). */
    public array $matriculasSeleccionadas = [];

    public function elegirCurso(int $id): void
    {
        if ($id <= 0 || ! $this->cursoPerteneceAlContexto($id)) {
            return;
        }

        $this->cursoId = $id;
        $this->matriculasSeleccionadas = [];
    }

    public function volverACursos(): void
    {
        $this->cursoId = null;
        $this->matriculasSeleccionadas = [];
    }

    public function updatedMatriculasSeleccionadas(): void
    {
        $this->normalizarMatriculasSeleccionadas();
    }

    public function seleccionarTodasMatriculas(): void
    {
        $this->matriculasSeleccionadas = $this->matriculasDelCurso()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function quitarTodasMatriculas(): void
    {
        $this->matriculasSeleccionadas = [];
    }

    public function toggleSeleccionTodas(): void
    {
        if ($this->todasLasMatriculasMarcadas()) {
            $this->quitarTodasMatriculas();
        } else {
            $this->seleccionarTodasMatriculas();
        }
    }

    public function todasLasMatriculasMarcadas(): bool
    {
        $permitidos = $this->matriculasDelCurso()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->sort()
            ->values();

        if ($permitidos->isEmpty()) {
            return false;
        }

        $marcados = collect($this->matriculasSeleccionadas)
            ->map(fn ($v) => (string) $v)
            ->filter(fn ($v) => $v !== '')
            ->sort()
            ->values();

        return $marcados->all() === $permitidos->all();
    }

    public function puedeGenerarPdfLote(): bool
    {
        return $this->cursoId !== null
            && collect($this->matriculasSeleccionadas)->filter(fn ($v) => (int) $v > 0)->isNotEmpty();
    }

    protected function normalizarMatriculasSeleccionadas(): void
    {
        $allowed = $this->matriculasDelCurso()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->matriculasSeleccionadas = collect($this->matriculasSeleccionadas)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0 && in_array($id, $allowed, true))
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    private function cursoPerteneceAlContexto(int $id): bool
    {
        $ctx = schoolCtx();

        return Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', $id)
            ->exists();
    }

    /**
     * @return Collection<int, Matricula>
     */
    public function matriculasDelCurso(): Collection
    {
        if (! $this->cursoId || ! $this->cursoPerteneceAlContexto((int) $this->cursoId)) {
            return collect();
        }

        $ctx = schoolCtx();

        return Matricula::query()
            ->with('legajo')
            ->where('idCursos', (int) $this->cursoId)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->get()
            ->sortBy(function (Matricula $m) {
                $a = mb_strtolower((string) ($m->legajo?->apellido ?? ''));
                $n = mb_strtolower((string) ($m->legajo?->nombre ?? ''));

                return [$a, $n];
            })
            ->values();
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
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
    }

    public function cursoActivo(): ?Curso
    {
        if (! $this->cursoId) {
            return null;
        }

        return $this->cursos()->firstWhere('Id', (int) $this->cursoId);
    }

    public function render()
    {
        $matriculas = $this->matriculasDelCurso();
        $cantidadSeleccionados = collect($this->matriculasSeleccionadas)
            ->filter(fn ($v) => (int) $v > 0)
            ->count();

        $idsPdfLote = [];
        $puedePdfLote = false;
        if ($this->puedeGenerarPdfLote() && $this->cursoId) {
            $ids = collect($this->matriculasSeleccionadas)
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            if ($ids->count() <= InformeInasistenciasLoteParams::MAX_MATRICULAS) {
                $idsPdfLote = $ids->all();
                $puedePdfLote = $idsPdfLote !== [];
            }
        }

        return view('livewire.seguimiento.inasistencias.informe-lote-index', [
            'cursos' => $this->cursos(),
            'cursoActivo' => $this->cursoActivo(),
            'matriculas' => $matriculas,
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'idsPdfLote' => $idsPdfLote,
            'puedePdfLote' => $puedePdfLote,
            'todasMarcadas' => $this->todasLasMatriculasMarcadas(),
            'hayMatriculas' => $matriculas->isNotEmpty(),
            'maxMatriculasPdf' => InformeInasistenciasLoteParams::MAX_MATRICULAS,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Informe de Inasistencias']);
    }
}
