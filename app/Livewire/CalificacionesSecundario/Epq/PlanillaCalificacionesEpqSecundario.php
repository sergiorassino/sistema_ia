<?php

namespace App\Livewire\CalificacionesSecundario\Epq;

use App\Models\Curso;
use App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos;
use App\Support\CalificacionesSecundario\Epq\CalificacionesEpqSecundarioCatalogo;
use App\Support\CalificacionesSecundario\Epq\PlanillaCalificacionesEpqSecundarioDatos;
use App\Support\NivelSistema;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\CalificacionesDocenteSecundario;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * EPQ — selección de curso y espacios curriculares para imprimir planilla secundario (PDF, una hoja por materia).
 */
class PlanillaCalificacionesEpqSecundario extends Component
{
    public ?int $cursoId = null;

    /** IDs de materia marcados (`materias.id` como string). */
    public array $materiasSeleccionadas = [];

    public bool $modoPortalDocente = false;

    public function mount(): void
    {
        CalificacionesSecundarioModulos::abortSiImplementacionInactiva(
            CalificacionesSecundarioModulos::PLANILLA,
            CalificacionesEpqSecundarioCatalogo::IMPLEMENTACION,
        );

        $this->modoPortalDocente = PortalDocenteContext::esActivo();

        if ($this->modoPortalDocente) {
            CalificacionesDocenteSecundario::abortSiNoEsSecundario();
        } else {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para generar planillas.',
            );
        }

        abort_unless(
            NivelSistema::esSecundario((int) schoolCtx()->idNivel),
            403,
            'Este módulo corresponde al nivel secundario.',
        );

        $this->cursoId = null;
        $this->materiasSeleccionadas = [];
    }

    public function updatedCursoId(mixed $value): void
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

        $query = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id');

        if ($this->modoPortalDocente) {
            $idsCursos = collect(CalificacionesDocenteSecundario::materiasAsignadas((int) ($ctx->idProfesor ?? 0)))
                ->pluck('idCurso')
                ->unique()
                ->filter(fn ($id) => (int) $id > 0)
                ->values()
                ->all();
            if ($idsCursos === []) {
                return collect();
            }
            $query->whereIn('Id', $idsCursos);
        }

        return $query->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
    }

    public function materiasDelCurso(): Collection
    {
        if (! $this->cursoId) {
            return collect();
        }

        return PlanillaCalificacionesEpqSecundarioDatos::materiasDelCurso(
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

            $pdfUrl = route(
                $this->modoPortalDocente
                    ? CalificacionesSecundarioModulos::rutaPortal(CalificacionesSecundarioModulos::PLANILLA, 'pdf')
                    : CalificacionesSecundarioModulos::rutaStaff(CalificacionesSecundarioModulos::PLANILLA, 'pdf'),
                ['materias' => $ids->implode(',')],
            );
        }

        $layout = $this->modoPortalDocente ? 'layouts.docente' : layoutMenuStaff();

        return view('livewire.calificaciones-secundario.epq.planilla-index', compact(
            'cursos',
            'materias',
            'etiquetaCurso',
            'etiquetaMaterias',
            'cantidadSeleccionados',
            'pdfUrl',
        ))->layout($layout, ['pageTitle' => 'Planilla de calificaciones']);
    }
}
