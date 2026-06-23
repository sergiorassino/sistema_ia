<?php

namespace App\Livewire\Abm\MateriasAnio;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioNotasPermitidas;
use App\Support\PermisosConfiguracion;
use App\Models\Curso;
use App\Models\Curplan;
use App\Models\Matplan;
use App\Models\Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;

class MateriasAnioIndex extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::MATERIAS_ANIO;
    }

    public bool $showConfirm = false;
    public ?int $deleteId = null;
    public string $deleteInfo = '';

    /**
     * Filtro superior.
     */
    public ?int $cursoId = null;

    /**
     * Alta rápida (fila superior).
     */
    public bool $creating = false;
    public array $create = [];

    /**
     * Edición inline por fila: $editingId + $draft[<id>][<field>]
     */
    public ?int $editingId = null;
    public array $draft = [];

    public function mount(): void
    {
        $ctx = schoolCtx();

        $this->cursoId = (int) (Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->value('Id') ?? 0) ?: null;
    }

    public function updatedCursoId($value): void
    {
        // Evitar cambios de curso con una edición/alta abierta.
        $this->creating = false;
        $this->create = [];
        $this->editingId = null;
        $this->draft = [];
        $this->resetValidation();
    }

    public function startCreate(): void
    {
        $ctx = schoolCtx();

        $cursoId = (int) ($this->cursoId ?? 0);

        $curplanId = $cursoId > 0
            ? (int) (Curso::query()->where('Id', $cursoId)->value('idCurPlan') ?? 0)
            : 0;

        $matplanId = $curplanId > 0
            ? (int) (Matplan::query()
                ->where('idCurPlan', $curplanId)
                ->orderBy('ord')
                ->orderBy('id')
                ->value('id') ?? 0)
            : 0;

        $this->creating = true;
        $this->create = [
            'ord' => 0,
            'idNivel' => (int) $ctx->idNivel,
            'idTerlec' => (int) $ctx->idTerlec,
            'idCursos' => $cursoId > 0 ? $cursoId : null,
            'idCurPlan' => $curplanId > 0 ? $curplanId : null,
            'idMatPlan' => $matplanId > 0 ? $matplanId : null,
            'materia' => '',
            'abrev' => '',
            'esInstitucional' => false,
            'infoCalif' => false,
            'escala' => CalificacionesPrimarioNotasPermitidas::ESCALA_CONCEPTOS,
        ];

        $this->editingId = null;
        $this->resetValidation();
    }

    public function cancelCreate(): void
    {
        $this->creating = false;
        $this->create = [];
        $this->resetValidation();
    }

    protected function createRules(array $curplanIds, array $cursoIds, array $matplanIds): array
    {
        $ctx = schoolCtx();

        return [
            'create.ord' => ['required', 'integer', 'min:0', 'max:999'],
            'create.idNivel' => ['required', 'integer', Rule::in([(int) $ctx->idNivel])],
            'create.idTerlec' => ['required', 'integer', Rule::in([(int) $ctx->idTerlec])],
            'create.idCursos' => ['required', 'integer', Rule::in($cursoIds)],
            'create.idCurPlan' => ['required', 'integer', Rule::in($curplanIds)],
            'create.idMatPlan' => ['required', 'integer', Rule::in($matplanIds)],
            'create.materia' => ['required', 'string', 'max:70'],
            'create.abrev' => ['nullable', 'string', 'max:5'],
            'create.esInstitucional' => ['boolean'],
            'create.infoCalif' => ['boolean'],
            'create.escala' => ['required', 'integer', Rule::in([
                CalificacionesPrimarioNotasPermitidas::ESCALA_CONCEPTOS,
                CalificacionesPrimarioNotasPermitidas::ESCALA_LITERALES,
            ])],
        ];
    }

    public function saveCreate(): void
    {
        $key = 'materias-anio:create:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            session()->flash('error', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            return;
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();

        $cursos = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->get(['Id', 'idCurPlan']);

        $cursoIds = $cursos->pluck('Id')->map(fn ($v) => (int) $v)->values()->all();

        $planesIds = Plan::query()
            ->where('idNivel', $ctx->idNivel)
            ->pluck('id');

        $curplanIds = Curplan::query()
            ->whereIn('idPlan', $planesIds)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $matplanIds = Matplan::query()
            ->whereIn('idCurPlan', $curplanIds)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $this->validate($this->createRules($curplanIds, $cursoIds, $matplanIds));

        $payload = [
            'ord' => (int) $this->create['ord'],
            'idNivel' => (int) $ctx->idNivel,
            'idTerlec' => (int) $ctx->idTerlec,
            'idCursos' => (int) $this->create['idCursos'],
            'idCurPlan' => (int) $this->create['idCurPlan'],
            'idMatPlan' => (int) $this->create['idMatPlan'],
            'materia' => trim((string) $this->create['materia']),
            'abrev' => trim((string) ($this->create['abrev'] ?? '')) !== '' ? trim((string) $this->create['abrev']) : null,
            'cierre1e' => 0,
            'cierre2e' => 0,
        ];
        if (Schema::hasColumn('materias', 'esInstitucional')) {
            $payload['esInstitucional'] = ! empty($this->create['esInstitucional']) ? 1 : 0;
        }
        if (Schema::hasColumn('materias', 'infoCalif')) {
            $payload['infoCalif'] = ! empty($this->create['infoCalif']) ? 1 : 0;
        }
        if (Schema::hasColumn('materias', 'escala')) {
            $payload['escala'] = CalificacionesPrimarioNotasPermitidas::normalizarEscala($this->create['escala'] ?? 1);
        }

        try {
            $id = (int) DB::table('materias')->insertGetId($payload);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'No se pudo crear la materia. Verifique los datos (curso / curso modelo / materia modelo).');
            return;
        }

        $this->creating = false;
        $this->create = [];

        $this->cursoId = (int) $payload['idCursos'];

        session()->flash('success', "Materia creada (ID {$id}).");
    }

    public function startEdit(int $id): void
    {
        $ctx = schoolCtx();

        $m = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('id', $id)
            ->first();

        if (! $m) {
            abort(404);
        }

        $this->editingId = (int) $m->id;
        $this->draft[(int) $m->id] = [
            'ord' => (int) $m->ord,
            'idNivel' => (int) $m->idNivel,
            'idCursos' => (int) $m->idCursos,
            'idTerlec' => (int) $m->idTerlec,
            'idCurPlan' => (int) $m->idCurPlan,
            'idMatPlan' => (int) $m->idMatPlan,
            'materia' => (string) ($m->materia ?? ''),
            'abrev' => (string) ($m->abrev ?? ''),
            'esInstitucional' => (int) ($m->esInstitucional ?? 0) === 1,
            'infoCalif' => (int) ($m->infoCalif ?? 0) === 1,
            'escala' => CalificacionesPrimarioNotasPermitidas::normalizarEscala($m->escala ?? 1),
        ];

        $this->creating = false;
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->resetValidation();
    }

    protected function rowRules(int $id, array $curplanIds, array $cursoIds, array $matplanIds): array
    {
        $ctx = schoolCtx();

        return [
            "draft.$id.ord" => ['required', 'integer', 'min:0', 'max:999'],
            "draft.$id.idNivel" => ['required', 'integer', Rule::in([(int) $ctx->idNivel])],
            "draft.$id.idTerlec" => ['required', 'integer', Rule::in([(int) $ctx->idTerlec])],
            "draft.$id.idCursos" => ['required', 'integer', Rule::in($cursoIds)],
            "draft.$id.idCurPlan" => ['required', 'integer', Rule::in($curplanIds)],
            "draft.$id.idMatPlan" => ['required', 'integer', Rule::in($matplanIds)],
            "draft.$id.materia" => ['required', 'string', 'max:70'],
            "draft.$id.abrev" => ['nullable', 'string', 'max:5'],
            "draft.$id.esInstitucional" => ['boolean'],
            "draft.$id.infoCalif" => ['boolean'],
            "draft.$id.escala" => ['required', 'integer', Rule::in([
                CalificacionesPrimarioNotasPermitidas::ESCALA_CONCEPTOS,
                CalificacionesPrimarioNotasPermitidas::ESCALA_LITERALES,
            ])],
        ];
    }

    public function saveRow(int $id): void
    {
        $key = 'materias-anio:inline-row:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError("draft.$id.materia", 'Demasiados intentos. Espere un momento e intente nuevamente.');
            return;
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();

        $cursos = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->get(['Id']);
        $cursoIds = $cursos->pluck('Id')->map(fn ($v) => (int) $v)->values()->all();

        $planesIds = Plan::query()
            ->where('idNivel', $ctx->idNivel)
            ->pluck('id');

        $curplanIds = Curplan::query()
            ->whereIn('idPlan', $planesIds)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $matplanIds = Matplan::query()
            ->whereIn('idCurPlan', $curplanIds)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $this->validate($this->rowRules($id, $curplanIds, $cursoIds, $matplanIds));

        $m = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('id', $id)
            ->first();

        if (! $m) {
            abort(404);
        }

        $d = $this->draft[$id] ?? [];

        $payload = [
            'ord' => (int) $d['ord'],
            'idNivel' => (int) $ctx->idNivel,
            'idTerlec' => (int) $ctx->idTerlec,
            'idCursos' => (int) $d['idCursos'],
            'idCurPlan' => (int) $d['idCurPlan'],
            'idMatPlan' => (int) $d['idMatPlan'],
            'materia' => trim((string) $d['materia']),
            'abrev' => trim((string) ($d['abrev'] ?? '')) !== '' ? trim((string) $d['abrev']) : null,
        ];
        if (Schema::hasColumn('materias', 'esInstitucional')) {
            $payload['esInstitucional'] = ! empty($d['esInstitucional']) ? 1 : 0;
        }
        if (Schema::hasColumn('materias', 'infoCalif')) {
            $payload['infoCalif'] = ! empty($d['infoCalif']) ? 1 : 0;
        }
        if (Schema::hasColumn('materias', 'escala')) {
            $payload['escala'] = CalificacionesPrimarioNotasPermitidas::normalizarEscala($d['escala'] ?? 1);
        }

        try {
            DB::table('materias')->where('id', $id)->update($payload);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'No se pudo guardar la materia. Verifique los datos (relaciones / dependencias).');
            return;
        }

        $this->editingId = null;
        $this->resetValidation();
    }

    public function syncFromMatplan(int $id): void
    {
        $key = 'materias-anio:sync-matplan:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            session()->flash('error', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();

        $m = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('id', $id)
            ->first(['id', 'idMatPlan', 'ord', 'materia', 'abrev']);

        if (! $m) {
            abort(404);
        }

        $idMatPlan = (int) ($m->idMatPlan ?? 0);
        if ($idMatPlan <= 0) {
            session()->flash('error', 'La materia no tiene idMatPlan asignado.');

            return;
        }

        $planesIds = Plan::query()
            ->where('idNivel', $ctx->idNivel)
            ->pluck('id');

        $curplanIds = Curplan::query()
            ->whereIn('idPlan', $planesIds)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $mp = Matplan::query()
            ->whereIn('idCurPlan', $curplanIds)
            ->where('id', $idMatPlan)
            ->first(['id', 'ord', 'matPlanMateria', 'abrev']);

        if (! $mp) {
            session()->flash('error', 'No se encontró la materia modelo (matplan) vinculada.');

            return;
        }

        $nuevoNombre = trim((string) ($mp->matPlanMateria ?? ''));
        if ($nuevoNombre === '') {
            session()->flash('error', 'La materia modelo (matplan) no tiene nombre definido.');

            return;
        }

        $nuevaAbrev = $mp->abrev !== null && trim((string) $mp->abrev) !== ''
            ? trim((string) $mp->abrev)
            : null;

        $nuevoOrd = max(0, min(999, (int) ($mp->ord ?? 0)));

        $nombreActual = trim((string) ($m->materia ?? ''));
        $abrevActual = trim((string) ($m->abrev ?? '')) !== '' ? trim((string) $m->abrev) : null;
        $ordActual = (int) ($m->ord ?? 0);

        if ($nombreActual === $nuevoNombre && $abrevActual === $nuevaAbrev && $ordActual === $nuevoOrd) {
            session()->flash('success', 'La materia ya coincide con matplan.');

            return;
        }

        try {
            DB::table('materias')->where('id', $id)->update([
                'materia' => $nuevoNombre,
                'abrev' => $nuevaAbrev,
                'ord' => $nuevoOrd,
            ]);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'No se pudo actualizar la materia desde matplan.');

            return;
        }

        session()->flash(
            'success',
            "Materia actualizada desde matplan: «{$nuevoNombre}»"
            .($nuevaAbrev !== null ? " ({$nuevaAbrev})" : '')
            ." · orden {$nuevoOrd}.",
        );
    }

    public function toggleEsInstitucional(int $id): void
    {
        if (! Schema::hasColumn('materias', 'esInstitucional')) {
            session()->flash('error', 'Falta aplicar la migración del campo «Extracurricular» (esInstitucional) en materias.');

            return;
        }

        $key = 'materias-anio:toggle-instit:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            session()->flash('error', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();

        $m = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('id', $id)
            ->first(['id', 'esInstitucional']);

        if (! $m) {
            abort(404);
        }

        $nuevo = (int) ($m->esInstitucional ?? 0) === 1 ? 0 : 1;

        DB::table('materias')->where('id', $id)->update(['esInstitucional' => $nuevo]);
    }

    public function toggleInfoCalif(int $id): void
    {
        if (! Schema::hasColumn('materias', 'infoCalif')) {
            session()->flash('error', 'Falta aplicar la migración del campo «Síntesis y calificaciones» (infoCalif) en materias.');

            return;
        }

        $key = 'materias-anio:toggle-info-calif:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            session()->flash('error', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();

        $m = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('id', $id)
            ->first(['id', 'infoCalif']);

        if (! $m) {
            abort(404);
        }

        $nuevo = (int) ($m->infoCalif ?? 0) === 1 ? 0 : 1;

        DB::table('materias')->where('id', $id)->update(['infoCalif' => $nuevo]);
    }

    public function guardarEscala(int $id, mixed $valor): void
    {
        if (! Schema::hasColumn('materias', 'escala')) {
            session()->flash('error', 'Falta aplicar la migración del campo «Escala» en materias.');

            return;
        }

        $key = 'materias-anio:escala:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            session()->flash('error', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();
        $escala = CalificacionesPrimarioNotasPermitidas::normalizarEscala($valor);

        $m = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('id', $id)
            ->first(['id', 'escala']);

        if (! $m) {
            abort(404);
        }

        if (CalificacionesPrimarioNotasPermitidas::normalizarEscala($m->escala ?? 1) === $escala) {
            return;
        }

        DB::table('materias')->where('id', $id)->update(['escala' => $escala]);
    }

    public function confirmDelete(int $id): void
    {
        $ctx = schoolCtx();

        $m = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('id', $id)
            ->first();

        if (! $m) {
            abort(404);
        }

        $deps = collect([
            'calificaciones' => fn () => DB::table('calificaciones')->where('idMaterias', $id)->count(),
            'evaluac' => fn () => DB::table('evaluac')->where('idMateria', $id)->count(),
            'fechascalendario' => fn () => DB::table('fechascalendario')->where('idMateria', $id)->count(),
            'horarios' => fn () => DB::table('horarios')->where('idMaterias', $id)->count(),
            'ief' => fn () => DB::table('ief')->where('idMaterias', $id)->count(),
            'mesasexamen' => fn () => DB::table('mesasexamen')->where('idMaterias', $id)->count(),
            'plapro' => fn () => DB::table('plapro')->where('idMateria', $id)->count(),
            'ppc' => fn () => DB::table('ppc')->where('idMateria', $id)->count(),
        ])->map(function ($fn) {
            try {
                return (int) $fn();
            } catch (\Throwable $e) {
                return 0;
            }
        });

        $total = (int) $deps->sum();

        if ($total > 0) {
            $detail = $deps
                ->filter(fn ($v) => (int) $v > 0)
                ->map(fn ($v, $k) => "{$v} {$k}")
                ->implode(', ');

            $this->deleteInfo = "No se puede eliminar la materia porque está siendo utilizada: {$detail}.";
            $this->deleteId = null;
        } else {
            $label = trim((string) ($m->materia ?? ''));
            $label = $label !== '' ? $label : ('ID ' . $m->id);
            $this->deleteId = (int) $m->id;
            $this->deleteInfo = "¿Confirma eliminar la materia \"{$label}\"?";
        }

        $this->showConfirm = true;
    }

    public function delete(): void
    {
        $key = 'materias-anio:delete:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('error', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showConfirm = false;
            $this->reset('deleteId', 'deleteInfo');
            return;
        }
        RateLimiter::hit($key, 60);

        if ($this->deleteId) {
            $ctx = schoolCtx();

            $m = DB::table('materias')
                ->where('idNivel', (int) $ctx->idNivel)
                ->where('idTerlec', (int) $ctx->idTerlec)
                ->where('id', $this->deleteId)
                ->first();

            if ($m) {
                try {
                    DB::table('materias')->where('id', (int) $m->id)->delete();
                    session()->flash('success', 'Materia eliminada.');
                } catch (\Throwable $e) {
                    report($e);
                    session()->flash('error', 'No se pudo eliminar la materia (dependencias / restricciones).');
                }
            }
        }

        $this->showConfirm = false;
        $this->reset('deleteId', 'deleteInfo');
    }

    /**
     * @return array{0:Collection<int, mixed>,1:Collection<int, mixed>,2:Collection<int, mixed>}
     */
    protected function options(): array
    {
        $ctx = schoolCtx();

        $cursos = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->with('turnoClase')
            ->get(['Id', 'cursec', 'c', 's', 'idCurPlan', 'idTurnoClase']);

        $curplanes = Curplan::ordenarColeccion(
            Curplan::query()
                ->delNivel((int) $ctx->idNivel)
                ->with('plan')
                ->get(['id', 'idPlan', 'curPlanCurso'])
        );

        $matplanes = Matplan::query()
            ->whereIn('idCurPlan', $curplanes->pluck('id'))
            ->orderBy('idCurPlan')
            ->orderBy('ord')
            ->orderBy('id')
            ->get(['id', 'idCurPlan', 'ord', 'matPlanMateria', 'abrev']);

        return [$cursos, $curplanes, $matplanes];
    }

    public function render()
    {
        $ctx = schoolCtx();

        $q = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->when($this->cursoId, fn ($qq) => $qq->where('idCursos', (int) $this->cursoId))
            ->orderBy('ord')
            ->orderBy('id')
            ;

        $columnas = [
            'id',
            'ord',
            'idNivel',
            'idCursos',
            'idTerlec',
            'idCurPlan',
            'idMatPlan',
            'materia',
            'abrev',
        ];
        if (Schema::hasColumn('materias', 'esInstitucional')) {
            $columnas[] = 'esInstitucional';
        }
        if (Schema::hasColumn('materias', 'infoCalif')) {
            $columnas[] = 'infoCalif';
        }
        if (Schema::hasColumn('materias', 'escala')) {
            $columnas[] = 'escala';
        }

        $materias = $q->get($columnas);

        [$cursos, $curplanes, $matplanes] = $this->options();

        $matplanesByCurplan = $matplanes->groupBy('idCurPlan');
        $tieneEsInstitucional = Schema::hasColumn('materias', 'esInstitucional');
        $tieneInfoCalif = Schema::hasColumn('materias', 'infoCalif');
        $tieneEscala = Schema::hasColumn('materias', 'escala');

        return view('livewire.abm.materias-anio.index', compact(
            'materias',
            'cursos',
            'curplanes',
            'matplanesByCurplan',
            'tieneEsInstitucional',
            'tieneInfoCalif',
            'tieneEscala',
        ))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Gestión de Asignaturas del Año']);
    }
}

