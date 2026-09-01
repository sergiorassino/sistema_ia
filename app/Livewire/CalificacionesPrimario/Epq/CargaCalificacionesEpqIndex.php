<?php

namespace App\Livewire\CalificacionesPrimario\Epq;

use App\Livewire\Concerns\AvisoCargaNotasOffEnto;
use App\Models\Curso;
use App\Models\Matricula;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;
use App\Support\CalificacionesPrimario\Epq\CalificacionesEpqCatalogo;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\OrdenAlfabeticoEstudiante;
use App\Support\PortalDocente\CalificacionesPrimarioPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * EPQ — selección de curso y listado de alumnos (Calificaciones / Info. adicional).
 */
class CargaCalificacionesEpqIndex extends Component
{
    use AvisoCargaNotasOffEnto;

    public ?int $cursoId = null;

    public bool $modoPortalDocente = false;

    public function mount(): void
    {
        CalificacionesPrimarioModulos::abortSiImplementacionInactiva(
            CalificacionesPrimarioModulos::CARGA_ESTUDIANTE,
            CalificacionesEpqCatalogo::IMPLEMENTACION,
        );

        $this->modoPortalDocente = CalificacionesPrimarioPortalDocente::esPortalDocente();

        if (! $this->modoPortalDocente) {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                \App\Support\PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para cargar calificaciones.',
            );
        }

        CalificacionesPrimarioPortalDocente::abortSiNoEsPrimario();

        $curso = (int) request()->query('curso', 0);
        if ($curso > 0) {
            if ($this->modoPortalDocente) {
                CalificacionesPrimarioPortalDocente::abortSiProfesorSinCurso($curso);
            }
            $this->cursoId = $curso;
        }

        $this->inicializarAvisoCargaNotasOff($this->modoPortalDocente);
    }

    public function updatedCursoId(mixed $value): void
    {
        $id = ((int) $value) > 0 ? (int) $value : null;
        if ($id !== null && $this->modoPortalDocente) {
            CalificacionesPrimarioPortalDocente::abortSiProfesorSinCurso($id);
        }
        $this->cursoId = $id;
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
            ->when($this->modoPortalDocente, fn ($q) => $q->whereIn('Id', CalificacionesPrimarioPortalDocente::idsCursosAsignados()))
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
    }

    public function render()
    {
        return view('livewire.calificaciones-primario.epq.carga-index', array_merge([
            'cursos' => $this->cursos(),
            'matriculas' => $this->matriculasDelCurso(),
        ], $this->datosVistaAvisoCargaNotasOff($this->modoPortalDocente)))->layout(CalificacionesPrimarioPortalDocente::layout(), ['pageTitle' => 'Carga de calificaciones (EPQ)']);
    }
}
