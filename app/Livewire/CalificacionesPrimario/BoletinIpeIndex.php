<?php

namespace App\Livewire\CalificacionesPrimario;

use App\Models\Curso;
use App\Models\Matricula;
use App\Support\BoletinSecundarioLoteParams;
use App\Support\NivelSistema;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Boletín de calificaciones IPE (primario): curso, selección de alumnos y PDF.
 */
class BoletinIpeIndex extends Component
{
    public ?int $cursoId = null;

    /** 1 = primera etapa, 2 = segunda etapa (layout del informe). */
    public int $etapa = 1;

    /** IDs de matrícula marcados (`matriculas.id` como string). */
    public array $matriculasSeleccionadas = [];

    public function mount(): void
    {
        abort_unless(tienePermiso(9), 403, 'Sin permiso para boletines de calificaciones.');
        abort_unless(
            NivelSistema::esPrimario((int) schoolCtx()->idNivel),
            403,
            'Este módulo corresponde al nivel primario. Cambie el contexto de nivel en el menú lateral.'
        );
    }

    public function updatedCursoId(mixed $value): void
    {
        $this->cursoId = ((int) $value) > 0 ? (int) $value : null;
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
        return collect($this->matriculasSeleccionadas)
            ->filter(fn ($v) => (int) $v > 0)
            ->isNotEmpty();
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

    /**
     * @return Collection<int, Matricula>
     */
    public function matriculasDelCurso(): Collection
    {
        if (! $this->cursoId) {
            return collect();
        }

        $ctx = schoolCtx();

        $cursoOk = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', (int) $this->cursoId)
            ->exists();

        if (! $cursoOk) {
            return collect();
        }

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

            if ($ids->count() <= BoletinSecundarioLoteParams::MAX_MATRICULAS) {
                $idsPdfLote = $ids->all();
                $puedePdfLote = $idsPdfLote !== [];
            }
        }

        $etapa = $this->etapa === 2 ? 2 : 1;

        return view('livewire.calificaciones-primario.boletin-ipe-index', [
            'cursos' => $this->cursos(),
            'matriculas' => $matriculas,
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'idsPdfLote' => $idsPdfLote,
            'puedePdfLote' => $puedePdfLote,
            'todasMarcadas' => $this->todasLasMatriculasMarcadas(),
            'hayMatriculas' => $matriculas->isNotEmpty(),
            'etapaPdf' => $etapa,
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Boletín IPE (primario)']);
    }
}
