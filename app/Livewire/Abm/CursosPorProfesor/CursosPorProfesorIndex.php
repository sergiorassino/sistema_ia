<?php

namespace App\Livewire\Abm\CursosPorProfesor;

use App\Models\Curso;
use App\Models\SituacionRevista;
use App\Support\PermisosIaCatalog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Cursos por profesor (consulta).
 *
 * Lista los docentes que tienen al menos una asignación en `ppc` (filtrado por nivel/ciclo lectivo
 * de la sesión), y para cada uno muestra:
 *   - materias y cursos asignados (ppc → materias → cursos)
 *   - situación de revista (situacionrevista vinculada por ppc.idSituRevis)
 *   - cantidad de horas cátedra cargadas en horarios26 (un módulo = una hora cátedra)
 *
 * Solo lectura. Para alta/baja de asignaciones se usa el módulo «Docentes por materia y curso»
 * (App\Livewire\Abm\ProfesoresPorMateria\ProfesoresPorMateriaIndex).
 */
class CursosPorProfesorIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $cursoId = '';

    public string $situacionRevistaId = '';

    /** Cantidad de docentes por página. */
    private const POR_PAGINA = 15;

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO), 403, 'Sin permiso para consultar cursos por profesor.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCursoId(): void
    {
        $this->resetPage();
    }

    public function updatedSituacionRevistaId(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['search', 'cursoId', 'situacionRevistaId']);
        $this->resetPage();
    }

    public function render()
    {
        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        $profesoresPagina = $this->profesoresConAsignacionesPaginados($idNivel, $idTerlec);
        $idsProfesores = collect($profesoresPagina->items())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $asignacionesPorProfesor = $idsProfesores !== []
            ? $this->asignacionesPorProfesor($idsProfesores, $idNivel, $idTerlec)
            : collect();

        $horasPorPpc = $idsProfesores !== []
            ? $this->horasPorAsignacionDelProfesor($asignacionesPorProfesor)
            : collect();

        $totalesPorProfesor = $asignacionesPorProfesor
            ->map(function (Collection $filas) use ($horasPorPpc) {
                return (int) $filas->sum(fn ($f) => (int) ($horasPorPpc[$this->clavePpcUnica($f)] ?? 0));
            });

        $cursos = Curso::query()
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->with('turnoClase')
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'c', 's', 'idTurnoClase', 'idCurPlan', 'orden']);

        $situaciones = SituacionRevista::query()
            ->orderBy('sitRev')
            ->get(['id', 'sitRev']);

        return view('livewire.abm.cursos-por-profesor.index', [
            'profesores' => $profesoresPagina,
            'asignacionesPorProfesor' => $asignacionesPorProfesor,
            'horasPorPpc' => $horasPorPpc,
            'totalesPorProfesor' => $totalesPorProfesor,
            'cursos' => $cursos,
            'situaciones' => $situaciones,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Cursos por profesor']);
    }

    /**
     * Profesores con al menos una asignación en `ppc` cuyo curso/materia pertenece al nivel y ciclo de la sesión,
     * aplicando filtros de búsqueda y curso/situación. Paginado por docente.
     */
    private function profesoresConAsignacionesPaginados(int $idNivel, int $idTerlec): LengthAwarePaginator
    {
        $q = DB::table('profesores as p')
            ->join('ppc as ppc', 'ppc.idProfesor', '=', 'p.id')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->join('cursos as c', 'c.Id', '=', 'm.idCursos')
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->where('c.idNivel', $idNivel)
            ->where('c.idTerlec', $idTerlec);

        $cursoId = (int) ($this->cursoId !== '' ? $this->cursoId : 0);
        if ($cursoId > 0) {
            $q->where('m.idCursos', $cursoId);
        }

        $sitId = (int) ($this->situacionRevistaId !== '' ? $this->situacionRevistaId : 0);
        if ($sitId > 0) {
            $q->where('ppc.idSituRevis', $sitId);
        }

        $search = trim($this->search);
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($w) use ($like, $search) {
                $w->where('p.apellido', 'like', $like)
                    ->orWhere('p.nombre', 'like', $like);
                if (ctype_digit($search)) {
                    $w->orWhere('p.dni', (int) $search);
                }
            });
        }

        return $q
            ->select('p.id', 'p.apellido', 'p.nombre', 'p.dni')
            ->groupBy('p.id', 'p.apellido', 'p.nombre', 'p.dni')
            ->orderBy('p.apellido')
            ->orderBy('p.nombre')
            ->paginate(self::POR_PAGINA);
    }

    /**
     * Asignaciones agrupadas por idProfesor.
     *
     * @param  list<int>  $idsProfesores
     * @return Collection<int, Collection<int, object>>
     */
    private function asignacionesPorProfesor(array $idsProfesores, int $idNivel, int $idTerlec): Collection
    {
        $q = DB::table('ppc as ppc')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->join('cursos as c', 'c.Id', '=', 'm.idCursos')
            ->leftJoin('situacionrevista as sr', 'sr.id', '=', 'ppc.idSituRevis')
            ->whereIn('ppc.idProfesor', $idsProfesores)
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->where('c.idNivel', $idNivel)
            ->where('c.idTerlec', $idTerlec);

        $cursoId = (int) ($this->cursoId !== '' ? $this->cursoId : 0);
        if ($cursoId > 0) {
            $q->where('m.idCursos', $cursoId);
        }

        $sitId = (int) ($this->situacionRevistaId !== '' ? $this->situacionRevistaId : 0);
        if ($sitId > 0) {
            $q->where('ppc.idSituRevis', $sitId);
        }

        $filas = $q
            ->orderBy('c.orden')
            ->orderBy('c.cursec')
            ->orderBy('m.ord')
            ->orderBy('m.materia')
            ->get([
                'ppc.id as ppcId',
                'ppc.idProfesor as idProfesor',
                'ppc.idMateria as idMateria',
                'ppc.idSituRevis as idSituRevis',
                'm.idCursos as idCursos',
                'm.materia as materia',
                'm.abrev as materiaAbrev',
                'm.ord as materiaOrden',
                'c.Id as cursoId',
                'c.cursec as cursoCursec',
                'c.orden as cursoOrden',
                'c.c as cursoC',
                'c.s as cursoS',
                'c.idTurnoClase as cursoIdTurnoClase',
                'c.idCurPlan as cursoIdCurPlan',
                'sr.sitRev as sitRev',
            ]);

        $turnoIds = $filas->pluck('cursoIdTurnoClase')
            ->filter(fn ($v) => (int) $v > 0)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $turnosPorId = $turnoIds !== []
            ? DB::table('turnos_clase')->whereIn('id', $turnoIds)->pluck('nombre', 'id')
            : collect();

        $filas = $filas->map(function ($r) use ($turnosPorId) {
            $idTc = (int) ($r->cursoIdTurnoClase ?? 0);
            $turnoNombre = $idTc > 0 ? trim((string) ($turnosPorId[$idTc] ?? '')) : '';

            $sec = trim((string) ($r->cursoCursec ?? ''));
            if ($sec !== '') {
                $cursoLabel = $sec.($turnoNombre !== '' ? ' · '.$turnoNombre : '');
            } else {
                $extras = array_filter([
                    $turnoNombre,
                    trim((string) ($r->cursoC ?? '')),
                    trim((string) ($r->cursoS ?? '')),
                ], fn ($v) => $v !== '');
                $cursoLabel = $extras !== [] ? implode(' · ', $extras) : ('Curso #'.(int) $r->cursoId);
            }

            $r->cursoLabel = $cursoLabel;
            $r->turnoNombre = $turnoNombre;

            return $r;
        });

        return $filas->groupBy(fn ($r) => (int) $r->idProfesor);
    }

    /**
     * Horas cátedra cargadas por cada asignación (idProfesor + idMateria) en `horarios26`.
     * Cada fila de horarios26 = 1 módulo (1 hora cátedra). Se cuenta por par (idProfesores, idMaterias)
     * porque la materia ya identifica al curso en `materias.idCursos`.
     *
     * @param  Collection<int, Collection<int, object>>  $asignacionesPorProfesor
     * @return Collection<string, int>  clave: "{idProfesor}-{idMateria}" → horas
     */
    private function horasPorAsignacionDelProfesor(Collection $asignacionesPorProfesor): Collection
    {
        $pares = [];
        foreach ($asignacionesPorProfesor as $idProf => $filas) {
            foreach ($filas as $f) {
                $pares[] = [(int) $idProf, (int) $f->idMateria];
            }
        }
        if ($pares === []) {
            return collect();
        }

        $idsProf = array_values(array_unique(array_map(fn ($p) => $p[0], $pares)));
        $idsMat = array_values(array_unique(array_map(fn ($p) => $p[1], $pares)));

        $rows = DB::table('horarios26')
            ->whereIn('idProfesores', $idsProf)
            ->whereIn('idMaterias', $idsMat)
            ->selectRaw('idProfesores, idMaterias, COUNT(*) AS horas')
            ->groupBy('idProfesores', 'idMaterias')
            ->get();

        $out = collect();
        foreach ($rows as $r) {
            $out[((int) $r->idProfesores).'-'.((int) $r->idMaterias)] = (int) $r->horas;
        }

        return $out;
    }

    /**
     * Clave única (idProfesor + idMateria) de una asignación, usada para indexar el conteo de horas.
     */
    public function clavePpcUnica(object $fila): string
    {
        return ((int) $fila->idProfesor).'-'.((int) $fila->idMateria);
    }
}
