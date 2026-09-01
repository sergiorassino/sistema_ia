<?php

namespace App\Livewire\CalificacionesInicial;

use App\Models\Curso;
use App\Models\Matricula;
use App\Support\BoletinSecundarioLoteParams;
use App\Support\CalificacionesInicial\CalificacionesInicialObservacionesDatos;
use App\Support\CalificacionesInicial\InformeProgresoInicialDatos;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\OrdenAlfabeticoEstudiante;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\CalificacionesInicialPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Informe de Progreso Escolar (inicial): sala/curso, selección de alumnos y PDF.
 */
class InformeProgresoInicialIndex extends Component
{
    public bool $modoPortalDocente = false;

    public ?int $cursoId = null;

    /** 1 = primera etapa, 2 = segunda etapa. */
    public int $etapa = 1;

    /** IDs de matrícula marcados. */
    public array $matriculasSeleccionadas = [];

    public function mount(): void
    {
        $this->modoPortalDocente = CalificacionesInicialPortalDocente::esPortalDocente();

        if ($this->modoPortalDocente) {
            CalificacionesInicialPortalDocente::abortSiMenuInactivo(CalificacionesInicialPortalDocente::MENU_INFORME_PROGRESO);
        } else {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para informes de calificaciones.',
            );
        }

        CalificacionesInicialPortalDocente::abortSiNoEsInicial();
        CalificacionesInicialObservacionesDatos::abortSiColumnasInexistentes();
        InformeProgresoInicialDatos::abortSiColumnaInfoCalifInexistente();

        $curso = (int) request()->query('curso', 0);
        if ($curso > 0) {
            if ($this->modoPortalDocente) {
                CalificacionesInicialPortalDocente::abortSiProfesorSinCurso($curso);
            }
            $this->cursoId = $curso;
        }
    }

    public function updatedCursoId(mixed $value): void
    {
        $id = ((int) $value) > 0 ? (int) $value : null;
        if ($id !== null && $this->modoPortalDocente) {
            CalificacionesInicialPortalDocente::abortSiProfesorSinCurso($id);
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

        $idsCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES,
        );

        return Matricula::query()
            ->with('legajo')
            ->where('idCursos', (int) $this->cursoId)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->whereIn('idCondiciones', $idsCondiciones)
            ->whereNull('fechaBaja')
            ->get()
            ->pipe(fn ($c) => OrdenAlfabeticoEstudiante::ordenarMatriculas($c));
    }

    public function cursos(): Collection
    {
        $ctx = schoolCtx();

        return Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->when($this->modoPortalDocente, fn ($q) => $q->whereIn('Id', CalificacionesInicialPortalDocente::idsCursosAsignados()))
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

        return view('livewire.calificaciones-inicial.informe-progreso-inicial-index', [
            'cursos' => $this->cursos(),
            'matriculas' => $matriculas,
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'idsPdfLote' => $idsPdfLote,
            'puedePdfLote' => $puedePdfLote,
            'todasMarcadas' => $this->todasLasMatriculasMarcadas(),
            'hayMatriculas' => $matriculas->isNotEmpty(),
            'etapaPdf' => $etapa,
        ])
            ->layout(CalificacionesInicialPortalDocente::layout(), ['pageTitle' => 'Informe de progreso escolar (inicial)']);
    }
}
