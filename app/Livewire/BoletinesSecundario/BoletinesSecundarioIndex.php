<?php

namespace App\Livewire\BoletinesSecundario;

use App\Models\Curso;
use App\Models\Matricula;
use App\Support\BoletinSecundarioLoteParams;
use App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\OrdenAlfabeticoEstudiante;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Boletines / informe de progreso escolar (nivel secundario):
 * elige curso, marca estudiantes y genera PDF individual o en lote.
 */
class BoletinesSecundarioIndex extends Component
{
    public function mount(): void
    {
        if (CalificacionesSecundarioModulos::moduloActivo(CalificacionesSecundarioModulos::BOLETIN)) {
            $this->redirectRoute(
                CalificacionesSecundarioModulos::rutaStaff(CalificacionesSecundarioModulos::BOLETIN),
                navigate: true,
            );
        }
    }

    /** Curso seleccionado (`cursos.Id`) dentro del contexto de sesión. */
    public ?int $cursoId = null;

    /**
     * Si el PDF muestra el valor de «Calific. Final» (1 = sí, 0 = celda vacía).
     * Entero (no bool): Livewire + `<select value="0">` pierde el false al re-renderizar.
     */
    public int $mostrarPromedios = 1;

    /** IDs de matrícula marcados (`matriculas.id` como string). */
    public array $matriculasSeleccionadas = [];

    public function updatedCursoId(mixed $value): void
    {
        $this->cursoId = ((int) $value) > 0 ? (int) $value : null;
        $this->matriculasSeleccionadas = [];
    }

    public function updatedMostrarPromedios(mixed $value): void
    {
        $this->mostrarPromedios = ((int) $value) === 1 ? 1 : 0;
    }

    public function updatedMatriculasSeleccionadas(): void
    {
        $this->normalizarMatriculasSeleccionadas();
    }

    public function debeMostrarPromedios(): bool
    {
        return $this->mostrarPromedios === 1;
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

        $idsCondicionesRegulares = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES
        );

        return OrdenAlfabeticoEstudiante::ordenarMatriculas(
            Matricula::query()
                ->with('legajo')
                ->where('idCursos', (int) $this->cursoId)
                ->where('idNivel', (int) $ctx->idNivel)
                ->where('idTerlec', (int) $ctx->idTerlec)
                ->whereIn('idCondiciones', $idsCondicionesRegulares)
                ->get()
        );
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

        return view('livewire.boletines-secundario.index', [
            'cursos' => $this->cursos(),
            'matriculas' => $matriculas,
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'idsPdfLote' => $idsPdfLote,
            'puedePdfLote' => $puedePdfLote,
            'todasMarcadas' => $this->todasLasMatriculasMarcadas(),
            'hayMatriculas' => $matriculas->isNotEmpty(),
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Boletines (secundario) v1.0']);
    }
}
