<?php

namespace App\Livewire\CalificacionesPrimario;

use App\Models\Curso;
use App\Models\Matricula;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\NivelSistema;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Carga manual de calificaciones (primario): selección de curso y listado de alumnos.
 */
class CargaCalificacionesPrimarioIndex extends Component
{
    public ?int $cursoId = null;

    public function mount(): void
    {
        abort_unless(tienePermiso(9), 403, 'Sin permiso para cargar calificaciones.');
        abort_unless(
            NivelSistema::esPrimario((int) schoolCtx()->idNivel),
            403,
            'Este módulo corresponde al nivel primario. Cambie el contexto de nivel en el menú lateral.'
        );

        $curso = (int) request()->query('curso', 0);
        if ($curso > 0) {
            $this->cursoId = $curso;
        }
    }

    public function updatedCursoId(mixed $value): void
    {
        $this->cursoId = ((int) $value) > 0 ? (int) $value : null;
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
        return view('livewire.calificaciones-primario.carga-calificaciones-primario-index', [
            'cursos' => $this->cursos(),
            'matriculas' => $this->matriculasDelCurso(),
        ])->layout('layouts.app', ['pageTitle' => 'Carga de calificaciones (primario)']);
    }
}
