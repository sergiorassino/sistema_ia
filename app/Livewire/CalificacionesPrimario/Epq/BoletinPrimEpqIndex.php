<?php

namespace App\Livewire\CalificacionesPrimario\Epq;

use App\Models\Curso;
use App\Models\Matricula;
use App\Support\BoletinSecundarioLoteParams;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;
use App\Support\CalificacionesPrimario\Epq\CalificacionesEpqCatalogo;
use App\Support\NivelSistema;
use App\Support\OrdenAlfabeticoEstudiante;
use App\Support\PortalDocente\CalificacionesPrimarioPortalDocente;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * EPQ — Boletín (Prim): selección de alumnos y generación PDF anverso/reverso/completo.
 */
class BoletinPrimEpqIndex extends Component
{
    public ?int $cursoId = null;

    /** anverso | reverso | completo */
    public string $cara = 'completo';

    /** @var list<string> */
    public array $matriculasSeleccionadas = [];

    public bool $modoPortalDocente = false;

    public function mount(): void
    {
        CalificacionesPrimarioModulos::abortSiImplementacionInactiva(
            CalificacionesPrimarioModulos::BOLETIN_PRIM,
            CalificacionesEpqCatalogo::IMPLEMENTACION,
        );

        $this->modoPortalDocente = CalificacionesPrimarioPortalDocente::esPortalDocente();

        if ($this->modoPortalDocente) {
            abort_unless(
                (bool) config('tenant.portal_docente.menu.primario.boletin_ipe', false),
                404,
            );
        }

        abort_unless(
            NivelSistema::esPrimario((int) schoolCtx()->idNivel),
            403,
            'Este módulo corresponde al nivel primario.',
        );
    }

    public function updatedCursoId(mixed $value): void
    {
        $id = ((int) $value) > 0 ? (int) $value : null;
        if ($id !== null && $this->modoPortalDocente) {
            CalificacionesPrimarioPortalDocente::abortSiProfesorSinCurso($id);
        }
        $this->cursoId = $id;
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

        return Matricula::query()
            ->with('legajo')
            ->where('idCursos', (int) $this->cursoId)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->when($this->modoPortalDocente, function ($q) {
                $q->whereIn('idCursos', CalificacionesPrimarioPortalDocente::idsCursosAsignados());
            })
            ->get()
            ->pipe(fn ($c) => OrdenAlfabeticoEstudiante::ordenarMatriculas($c));
    }

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

    public function render()
    {
        $matriculas = $this->matriculasDelCurso();
        $idsPdfLote = [];
        $puedePdfLote = false;

        if ($this->puedeGenerarPdfLote()) {
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

        $etiquetaMenu = tenantBoletinPrimarioMenuEtiquetaBoletinIpe();

        return view('livewire.calificaciones-primario.epq.boletin-index', [
            'cursos' => $this->cursos(),
            'matriculas' => $matriculas,
            'idsPdfLote' => $idsPdfLote,
            'puedePdfLote' => $puedePdfLote,
            'etiquetaMenu' => $etiquetaMenu,
        ])->layout(CalificacionesPrimarioPortalDocente::layout(), ['pageTitle' => $etiquetaMenu]);
    }
}
