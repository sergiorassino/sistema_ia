<?php

namespace App\Livewire\Abm\ProfesoresPorMateria;

use App\Models\Curso;
use App\Models\Profesor;
use App\Models\SituacionRevista;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Asignación docente: la tabla ppc une materia y profesor (`materias.id`, `profesores.id`).
 * Cada vínculo es un registro con idMateria + idProfesor. El curso en pantalla viene de materias.idCursos;
 * al listar o mutar ppc para una materia concreta, la consulta pivota siempre en idMateria = id elegido.
 */
class ProfesoresPorMateriaIndex extends Component
{
    public ?int $cursoId = null;

    /** Materia seleccionada para panel derecho */
    public ?int $selectedMateriaId = null;

    /** Alta rápida: id de profesor a asignar */
    public ?int $nuevoProfesorId = null;

    /** Alta rápida: situación de revista (situacionrevista.id) a guardar en ppc.idSituRevis */
    public ?int $nuevaSituRevisId = null;

    /** Modal confirmar quitar asignación ppc */
    public bool $showConfirmQuitar = false;

    public ?int $quitarPpcId = null;

    public string $quitarPpcInfo = '';

    /*
     * Depuración SQL en pantalla — desactivada (convención: `docs/05-preferencias-y-convenciones.md` §10).
     * Reactivar: descomentar propiedad, resets en updatedCursoId, línea en selectMateria, pasar valor en render()
     * y panel en profesores-por-materia/index.blade.php.
     *
     * public ?string $consultaEjecutadaClic = null;
     */

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO), 403, 'Sin permiso para asignar docentes a materias.');

        $ctx = schoolCtx();

        $this->cursoId = (int) (Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->value('Id') ?? 0) ?: null;

        $this->syncSelectedMateria();
    }

    public function updatedCursoId(): void
    {
        $this->nuevoProfesorId = null;
        $this->nuevaSituRevisId = null;
        // $this->consultaEjecutadaClic = null;
        $this->resetValidation();
        $this->syncSelectedMateria();
    }

    private function syncSelectedMateria(): void
    {
        $first = $this->materiasQuery()->orderBy('ord')->orderBy('id')->value('id');
        $this->selectedMateriaId = $first ? (int) $first : null;
    }

    private function materiasQuery()
    {
        $ctx = schoolCtx();

        $q = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec);

        $cid = (int) ($this->cursoId ?? 0);
        if ($cid < 1) {
            return $q->whereRaw('1 = 0');
        }

        return $q->where('idCursos', $cid);
    }

    private function materiaEnContexto(int $idMateria): ?object
    {
        $ctx = schoolCtx();

        return DB::table('materias')
            ->where('id', $idMateria)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->first();
    }

    /** Base ppc para una materia: idMateria = PK de materias seleccionado en pantalla */
    private function queryPpcPorMateria(int $idMateria)
    {
        return DB::table('ppc')->where('idMateria', $idMateria);
    }

    /**
     * Verifica profesor existe y puede asignarse (no “Sin Rol” id tipo 1; respeta nivel de contexto como en otros módulos).
     */
    private function profesorElegibleParaAsignacion(int $idProfesor): bool
    {
        $ctx = schoolCtx();

        $q = Profesor::query()
            ->where('id', $idProfesor)
            ->where(function ($w) {
                $w->whereNull('IdTipoProf')->orWhere('IdTipoProf', '<>', 1);
            });

        if ((int) $ctx->idNivel > 0) {
            $n = (int) $ctx->idNivel;
            $q->where(function ($w) use ($n) {
                $w->where('nivel', $n)->orWhereNull('nivel')->orWhere('nivel', 0);
            });
        }

        return $q->exists();
    }

    /**
     * Docentes asignados: misma selección que al mostrar la materia en panel derecha tras `selectMateria`.
     * Parámetro: id de materia del formulario / clic (`materias.id` = `ppc.idMateria`).
     */
    public function asignadosPorMateria(int $idMateria): Collection
    {
        /*
         |   SELECT ppc.id          AS ppcId,
         |          p.id           AS idProfesor,
         |          p.apellido,
         |          p.nombre,
         |          p.IdTipoProf,
         |          ppc.idSituRevis AS idSituRevis,
         |          sr.sitRev      AS sitRev
         |     FROM ppc
         |     INNER JOIN profesores AS p ON p.id = ppc.idProfesor
         |     LEFT JOIN situacionrevista AS sr ON sr.id = ppc.idSituRevis
         |    WHERE ppc.idMateria = ?
         | ORDER BY p.apellido ASC, p.nombre ASC, ppc.id ASC
         */
        $filas = DB::select(
            'SELECT ppc.id AS ppcId, p.id AS idProfesor, p.apellido, p.nombre, p.IdTipoProf, '
            . 'ppc.idSituRevis AS idSituRevis, sr.sitRev AS sitRev '
            . 'FROM ppc '
            . 'INNER JOIN profesores AS p ON p.id = ppc.idProfesor '
            . 'LEFT JOIN situacionrevista AS sr ON sr.id = ppc.idSituRevis '
            . 'WHERE ppc.idMateria = ? '
            . 'ORDER BY p.apellido ASC, p.nombre ASC, ppc.id ASC',
            [$idMateria],
        );

        return collect($filas)->map(fn ($r) => (object) [
            'ppcId' => (int) $r->ppcId,
            'idProfesor' => (int) $r->idProfesor,
            'apellido' => $r->apellido ?? null,
            'nombre' => $r->nombre ?? null,
            'IdTipoProf' => $r->IdTipoProf ?? null,
            'idSituRevis' => isset($r->idSituRevis) ? (int) $r->idSituRevis : 0,
            'sitRev' => $r->sitRev ?? null,
        ]);
    }

    /**
     * Texto listo para mostrar en la vista; valores sólo enteros (contexto + id materia tras validación).
     * Solo debe invocarse con depuración SQL activada en pantalla (`docs/05-preferencias-y-convenciones.md` §10).
     */
    private function textoConsultasEjecutadasAlElegirMateria(int $idMateria): string
    {
        $ctx = schoolCtx();
        $idM = abs((int) $idMateria);
        $idNivel = abs((int) $ctx->idNivel);
        $idTerlec = abs((int) $ctx->idTerlec);

        $q1 = <<<SQL
SELECT id, idCursos
  FROM materias
 WHERE id = {$idM}
   AND idNivel = {$idNivel}
   AND idTerlec = {$idTerlec}
 LIMIT 1
SQL;

        $q2 = <<<SQL
SELECT ppc.id AS ppcId,
       p.id AS idProfesor,
       p.apellido,
       p.nombre,
       p.IdTipoProf,
       ppc.idSituRevis AS idSituRevis,
       sr.sitRev      AS sitRev
  FROM ppc
 INNER JOIN profesores AS p ON p.id = ppc.idProfesor
  LEFT JOIN situacionrevista AS sr ON sr.id = ppc.idSituRevis
 WHERE ppc.idMateria = {$idM}
 ORDER BY p.apellido ASC, p.nombre ASC, ppc.id ASC
SQL;

        return <<<TXT
Consultas ejecutadas al presionar esta materia (id materia = {$idM}; idNivel sesión = {$idNivel}; idTerlec sesión = {$idTerlec}):

────────────────────────────────────────────────────────────
① En la acción Livewire «selectMateria» (validar materia):

{$q1}

────────────────────────────────────────────────────────────
② En el render siguiente para armar «Docentes asignados» (panel derecho):

{$q2}

TXT;
    }

    public function selectMateria(int $id): void
    {
        /*
         | Consulta al hacer clic en el nombre de la materia (validar ciclo/nivel y curso):
         |
         |   SELECT id, idCursos
         |     FROM materias
         |    WHERE id = ?
         |      AND idNivel = ?
         |      AND idTerlec = ?
         |    LIMIT 1
         */
        $ctx = schoolCtx();

        /** @var object{id: mixed, idCursos: mixed}|null $m */
        $m = DB::selectOne(
            'SELECT id, idCursos FROM materias WHERE id = ? AND idNivel = ? AND idTerlec = ? LIMIT 1',
            [$id, (int) $ctx->idNivel, (int) $ctx->idTerlec],
        );

        if (! $m || (int) $m->idCursos !== (int) ($this->cursoId ?? 0)) {
            abort(404);
        }

        // $this->consultaEjecutadaClic = $this->textoConsultasEjecutadasAlElegirMateria((int) $id);
        $this->selectedMateriaId = $id;
        $this->nuevoProfesorId = null;
        $this->nuevaSituRevisId = null;
        $this->resetValidation();
    }

    public function agregarProfesor(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO), 403, 'Sin permiso para asignar docentes a la materia.');

        $key = 'ppc-assign:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            session()->flash('error', 'Demasiados intentos. Espere un momento.');
            return;
        }
        RateLimiter::hit($key, 60);

        if ((int) ($this->cursoId ?? 0) < 1) {
            session()->flash('error', 'Seleccione un curso.');
            return;
        }

        $idMateria = (int) ($this->selectedMateriaId ?? 0);
        $idProf = (int) ($this->nuevoProfesorId ?? 0);
        $idSituRevis = (int) ($this->nuevaSituRevisId ?? 0);

        $this->validate([
            'selectedMateriaId' => ['required', 'integer', 'min:1'],
            'nuevoProfesorId' => ['required', 'integer', 'min:1'],
            'nuevaSituRevisId' => ['required', 'integer', 'min:1'],
        ], [], [
            'selectedMateriaId' => 'materia',
            'nuevoProfesorId' => 'docente',
            'nuevaSituRevisId' => 'situación de revista',
        ]);

        $m = $this->materiaEnContexto($idMateria);
        if (! $m || ((int) ($m->idCursos ?? 0) !== (int) ($this->cursoId ?? 0))) {
            abort(404);
        }

        if (! $this->profesorElegibleParaAsignacion($idProf)) {
            session()->flash('error', 'El docente seleccionado no está disponible para asignación.');
            return;
        }

        if (! DB::table('situacionrevista')->where('id', $idSituRevis)->exists()) {
            session()->flash('error', 'La situación de revista seleccionada no es válida.');
            return;
        }

        $dup = $this->queryPpcPorMateria($idMateria)->where('idProfesor', $idProf)->exists();

        if ($dup) {
            session()->flash('error', 'Ese docente ya está asignado a la materia.');
            return;
        }

        try {
            DB::table('ppc')->insert([
                'idMateria' => $idMateria,
                'idProfesor' => $idProf,
                'idSituRevis' => $idSituRevis,
            ]);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'No se pudo guardar la asignación.');
            return;
        }

        $this->nuevoProfesorId = null;
        $this->nuevaSituRevisId = null;
        session()->flash('success', 'Docente asignado.');
    }

    /**
     * Cambia la situación de revista (`ppc.idSituRevis`) de una asignación existente,
     * validando alcance (materia → curso) y permiso. El catálogo origen es `situacionrevista`.
     */
    public function actualizarSituacionRevista(int $ppcId, $idSituRevis): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO), 403, 'Sin permiso para modificar la situación de revista.');

        $key = 'ppc-siturev:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            session()->flash('error', 'Demasiados intentos. Espere un momento.');
            return;
        }
        RateLimiter::hit($key, 60);

        if ((int) ($this->cursoId ?? 0) < 1) {
            abort(404);
        }

        $idSit = (int) ($idSituRevis ?? 0);
        if ($idSit < 1) {
            session()->flash('error', 'Seleccione una situación de revista válida.');
            return;
        }

        $ppcRow = DB::table('ppc')->where('id', $ppcId)->first(['id', 'idMateria']);
        if (! $ppcRow || (int) ($ppcRow->idMateria ?? 0) < 1) {
            abort(404);
        }

        $m = $this->materiaEnContexto((int) $ppcRow->idMateria);
        if (! $m || (int) ($m->idCursos ?? 0) !== (int) $this->cursoId) {
            abort(404);
        }

        if (! DB::table('situacionrevista')->where('id', $idSit)->exists()) {
            session()->flash('error', 'La situación de revista seleccionada no es válida.');
            return;
        }

        try {
            DB::table('ppc')->where('id', $ppcId)->update([
                'idSituRevis' => $idSit,
            ]);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'No se pudo actualizar la situación de revista.');
            return;
        }

        session()->flash('success', 'Situación de revista actualizada.');
    }

    public function confirmarQuitarProfesor(int $ppcId): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO), 403, 'Sin permiso para quitar asignaciones de docentes.');

        if ((int) ($this->cursoId ?? 0) < 1) {
            abort(404);
        }

        $row = DB::table('ppc as ppc')
            ->join('profesores as p', 'p.id', '=', 'ppc.idProfesor')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->where('ppc.id', $ppcId)
            ->first([
                'ppc.id',
                'ppc.idMateria',
                'p.apellido',
                'p.nombre',
                'm.materia',
                'm.idCursos',
            ]);

        if (! $row || (int) ($row->idMateria ?? 0) < 1) {
            abort(404);
        }

        $m = $this->materiaEnContexto((int) $row->idMateria);
        if (! $m || (int) ($row->idCursos ?? 0) !== (int) $this->cursoId) {
            abort(404);
        }

        $nombre = trim(((string) ($row->apellido ?? '')).', '.((string) ($row->nombre ?? '')));
        $this->quitarPpcId = $ppcId;
        $this->quitarPpcInfo = $nombre.' · '.trim((string) ($row->materia ?? ''));
        $this->showConfirmQuitar = true;
    }

    public function cerrarConfirmQuitar(): void
    {
        $this->showConfirmQuitar = false;
        $this->quitarPpcId = null;
        $this->quitarPpcInfo = '';
    }

    public function quitarProfesor(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO), 403, 'Sin permiso para quitar asignaciones de docentes.');

        $ppcId = (int) ($this->quitarPpcId ?? 0);
        if ($ppcId < 1) {
            $this->cerrarConfirmQuitar();

            return;
        }

        $key = 'ppc-unassign:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            session()->flash('error', 'Demasiados intentos. Espere un momento.');
            return;
        }
        RateLimiter::hit($key, 60);

        if ((int) ($this->cursoId ?? 0) < 1) {
            abort(404);
        }

        $ppcRow = DB::table('ppc')->where('id', $ppcId)->first(['id', 'idMateria']);
        if (! $ppcRow || (int) ($ppcRow->idMateria ?? 0) < 1) {
            abort(404);
        }

        $m = $this->materiaEnContexto((int) $ppcRow->idMateria);
        if (! $m || (int) ($m->idCursos ?? 0) !== (int) $this->cursoId) {
            abort(404);
        }

        try {
            DB::table('ppc')->where('id', $ppcId)->delete();
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'No se pudo quitar la asignación.');
            return;
        }

        $this->cerrarConfirmQuitar();
        session()->flash('success', 'Asignación eliminada.');
    }

    /**
     * Lista para el select: elegibles según tipo y nivel, sin los ya asignados a la materia activa.
     */
    public function profesoresDisponiblesParaAgregar(?int $idMateria): \Illuminate\Support\Collection
    {
        $ctx = schoolCtx();

        $idMateria = (int) ($idMateria ?? 0);
        $assigned = $idMateria > 0
            ? $this->queryPpcPorMateria($idMateria)->pluck('idProfesor')->map(fn ($v) => (int) $v)->all()
            : [];

        $q = Profesor::query()
            ->where(function ($w) {
                $w->whereNull('IdTipoProf')->orWhere('IdTipoProf', '<>', 1);
            });

        if ((int) $ctx->idNivel > 0) {
            $n = (int) $ctx->idNivel;
            $q->where(function ($w) use ($n) {
                $w->where('nivel', $n)->orWhereNull('nivel')->orWhere('nivel', 0);
            });
        }

        if ($assigned !== []) {
            $q->whereNotIn('id', $assigned);
        }

        return $q
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get(['id', 'apellido', 'nombre']);
    }

    public function render()
    {
        $ctx = schoolCtx();

        $cursos = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->with('turnoClase')
            ->get(['Id', 'cursec', 'c', 's', 'idTurnoClase']);

        $materias = $this->materiasQuery()
            ->orderBy('ord')
            ->orderBy('id')
            ->get(['id', 'ord', 'idCursos', 'materia', 'abrev']);

        $countsAsignaciones = [];
        if ($materias->isNotEmpty()) {
            $materiaIds = $materias->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $rows = DB::table('ppc')
                ->whereIn('idMateria', $materiaIds)
                ->selectRaw('idMateria, COUNT(*) AS c')
                ->groupBy('idMateria')
                ->get();

            foreach ($rows as $r) {
                $countsAsignaciones[(int) $r->idMateria] = (int) $r->c;
            }
        }

        $asignados = collect();
        if ($this->selectedMateriaId) {
            $asignados = $this->asignadosPorMateria((int) $this->selectedMateriaId);
        }

        $elegiblesParaSelect = $this->profesoresDisponiblesParaAgregar((int) ($this->selectedMateriaId ?? 0));

        $situacionesRevista = SituacionRevista::query()
            ->orderBy('sitRev')
            ->get(['id', 'sitRev']);

        $selectedMateria = null;
        if ($this->selectedMateriaId) {
            $selectedMateria = $materias->firstWhere('id', (int) $this->selectedMateriaId);
        }

        // Depuración SQL: con propiedad activa, pasar `'consultaEjecutadaClic' => $this->consultaEjecutadaClic` (§10 docs).
        return view('livewire.abm.profesores-por-materia.index', compact(
            'cursos',
            'materias',
            'countsAsignaciones',
            'asignados',
            'elegiblesParaSelect',
            'situacionesRevista',
            'selectedMateria',
        ) + ['consultaEjecutadaClic' => ''])
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Docentes por materia y curso']);
    }
}
