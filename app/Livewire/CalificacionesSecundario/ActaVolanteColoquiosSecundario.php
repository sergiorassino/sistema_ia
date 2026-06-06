<?php

namespace App\Livewire\CalificacionesSecundario;

use App\Models\Curso;
use App\Support\ActaVolanteColoquiosSecundario as ActaVolanteColoquiosService;
use App\Support\CalificacionesColoquioSecundario;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Impresión de actas volantes de coloquio (una hoja por materia con alumnos elegibles).
 */
class ActaVolanteColoquiosSecundario extends Component
{
    public string $periodo = CalificacionesColoquioSecundario::PERIODO_DICIEMBRE;

    public ?int $cursoId = null;

    /** IDs de materia marcados (`materias.id` como string). */
    public array $materiasSeleccionadas = [];

    public function mount(): void
    {
        $this->periodo = CalificacionesColoquioSecundario::PERIODO_DICIEMBRE;
        $this->cursoId = null;
        $this->materiasSeleccionadas = [];
    }

    public function updatedPeriodo($value): void
    {
        $this->periodo = CalificacionesColoquioSecundario::normalizarPeriodo(is_string($value) ? $value : null);
        $this->sincronizarMateriasTrasCambioDeFiltros();
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

    protected function sincronizarMateriasTrasCambioDeFiltros(): void
    {
        if (! $this->cursoId) {
            return;
        }

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
     * Materias del curso con al menos un alumno elegible para coloquio en el período activo.
     *
     * @return Collection<int, object>
     */
    public function materias(): Collection
    {
        if (! $this->cursoId) {
            return collect();
        }

        return ActaVolanteColoquiosService::materiasConAlumnosElegibles(
            (int) $this->cursoId,
            CalificacionesColoquioSecundario::normalizarPeriodo($this->periodo),
        );
    }

    public function render()
    {
        $cursos = $this->cursos();
        $materias = $this->materias();
        $campoActivo = CalificacionesColoquioSecundario::normalizarPeriodo($this->periodo);
        $condicionLabel = CalificacionesColoquioSecundario::tituloCondicionColoquio($campoActivo);

        $cursoLabel = $this->cursoId
            ? optional($cursos->firstWhere('Id', (int) $this->cursoId))->nombreParaListado()
            : null;

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

            $pdfUrl = route('calificacionesSecundario.actaVolanteColoquios.pdf', [
                'periodo' => $campoActivo,
                'curso' => $this->cursoId,
                'materias' => $ids->implode(','),
            ]);
        }

        return view('livewire.calificaciones-secundario.acta-volante-coloquios-secundario', compact(
            'cursos',
            'materias',
            'campoActivo',
            'condicionLabel',
            'cursoLabel',
            'cantidadMateriasSeleccionadas',
            'pdfUrl',
        ))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Actas volantes de coloquio']);
    }
}
