<?php

namespace App\Livewire\Abm\Cursos;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Support\PermisosConfiguracion;
use App\Models\Curso;
use App\Models\Curplan;
use App\Models\Matplan;
use App\Models\Nivel;
use App\Models\Plan;
use App\Models\Terlec;
use App\Models\TurnoClase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CursosIndex extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::CURSOS_ANIO;
    }

    public bool $showConfirm = false;

    public ?int $deleteId = null;
    public string $deleteInfo = '';

    /**
     * Edición inline por fila: $editingId + $draft[<Id>][<field>]
     */
    public ?int $editingId = null;
    public array $draft = [];

    public function startEdit(int $id): void
    {
        $ctx = schoolCtx();

        // Seguridad: sólo permite editar cursos del contexto actual
        $curso = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->findOrFail($id);

        $this->editingId = $curso->Id;

        $this->draft[$curso->Id] = [
            'orden' => $curso->orden,
            'idCurPlan' => (int) $curso->idCurPlan,
            'idTerlec' => (int) $curso->idTerlec,
            'idNivel' => (int) $curso->idNivel,
            'cursec' => (string) ($curso->cursec ?? ''),
            'c' => (string) ($curso->c ?? ''),
            's' => (string) ($curso->s ?? ''),
            'idTurnoClase' => $curso->idTurnoClase !== null && (int) $curso->idTurnoClase > 0 ? (int) $curso->idTurnoClase : null,
        ];

        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->resetValidation();
    }

    protected function rowRules(int $id): array
    {
        $ctx = schoolCtx();

        // ids válidos para el nivel actual (CurPlan depende de Plan->idNivel)
        $planesIds = Plan::query()
            ->where('idNivel', $ctx->idNivel)
            ->pluck('id');

        $curplanIds = Curplan::query()
            ->whereIn('idPlan', $planesIds)
            ->pluck('id')
            ->all();

        $turnoIds = TurnoClase::query()->orderBy('orden')->orderBy('id')->pluck('id')->map(fn ($x) => (int) $x)->all();

        return [
            "draft.$id.orden" => ['nullable', 'integer', 'min:0', 'max:999'],
            "draft.$id.idCurPlan" => ['required', 'integer', Rule::in($curplanIds)],
            "draft.$id.idTerlec" => ['required', 'integer', 'exists:terlec,id'],
            "draft.$id.idNivel" => ['required', 'integer', 'exists:niveles,id'],
            "draft.$id.cursec" => ['nullable', 'string', 'max:30'],
            "draft.$id.c" => ['nullable', 'string', 'max:1'],
            "draft.$id.s" => ['nullable', 'string', 'max:1'],
            "draft.$id.idTurnoClase" => ['nullable', 'integer', Rule::in($turnoIds)],
        ];
    }

    public function saveRow(int $id): void
    {
        $key = 'cursos:inline-row:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError("draft.$id.idCurPlan", 'Demasiados intentos. Espere un momento e intente nuevamente.');
            return;
        }
        RateLimiter::hit($key, 60);

        if (isset($this->draft[$id]['idTurnoClase']) && $this->draft[$id]['idTurnoClase'] === '') {
            $this->draft[$id]['idTurnoClase'] = null;
        }

        $this->validate($this->rowRules($id));

        $ctx = schoolCtx();

        /** @var Curso $curso */
        $curso = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->findOrFail($id);

        $d = $this->draft[$id] ?? [];
        $newCurplan = (int) ($d['idCurPlan'] ?? 0);
        $newTerlec = (int) ($d['idTerlec'] ?? 0);
        $newNivel = (int) ($d['idNivel'] ?? 0);

        // Dependencias: si hay matrículas / calificaciones, NO permitir re-crear materias del año
        if ((int) $curso->idCurPlan !== $newCurplan) {
            $countMatriculas = DB::table('matricula')->where('idCursos', (int) $curso->Id)->count();
            $countCalificaciones = DB::table('calificaciones')->where('idCursos', (int) $curso->Id)->count();

            if (($countMatriculas + $countCalificaciones) > 0) {
                $detail = collect([
                    $countMatriculas ? "{$countMatriculas} matrículas" : null,
                    $countCalificaciones ? "{$countCalificaciones} calificaciones" : null,
                ])->filter()->implode(', ');

                session()->flash('error', "No se puede cambiar el curso modelo porque el curso ya tiene: {$detail}. Para evitar inconsistencias, no se re-crearon materias.");
                // Mantener la fila en modo edición
                return;
            }
        }

        try {
            DB::transaction(function () use ($curso, $d, $newCurplan, $newTerlec, $newNivel) {
                // 1) CurPlan: cambia y resincroniza materias
                if ((int) $curso->idCurPlan !== $newCurplan) {
                    $curso->update(['idCurPlan' => $newCurplan]);

                    DB::table('materias')
                        ->where('idCursos', (int) $curso->Id)
                        ->where('idTerlec', (int) $curso->idTerlec)
                        ->where('idNivel', (int) $curso->idNivel)
                        ->delete();

                    $matplan = Matplan::query()
                        ->where('idCurPlan', $newCurplan)
                        ->orderBy('ord')
                        ->orderBy('id')
                        ->get(['id', 'ord', 'matPlanMateria', 'abrev']);

                    $rows = $matplan->map(function ($m) use ($newCurplan, $curso) {
                        $row = [
                            'ord' => (int) $m->ord,
                            'idCurPlan' => $newCurplan,
                            'idMatPlan' => (int) $m->id,
                            'idNivel' => (int) $curso->idNivel,
                            'idCursos' => (int) $curso->Id,
                            'idTerlec' => (int) $curso->idTerlec,
                            'materia' => (string) $m->matPlanMateria,
                            'abrev' => $m->abrev !== null && trim((string) $m->abrev) !== '' ? (string) $m->abrev : null,
                            'cierre1e' => 0,
                            'cierre2e' => 0,
                        ];
                        if (Schema::hasColumn('materias', 'esInstitucional')) {
                            $row['esInstitucional'] = 0;
                        }

                        return $row;
                    })->all();

                    if (! empty($rows)) {
                        DB::table('materias')->insert($rows);
                    }
                }

                // 2) Terlec / Nivel: también actualiza materias
                if ((int) $curso->idTerlec !== $newTerlec) {
                    DB::table('materias')
                        ->where('idCursos', (int) $curso->Id)
                        ->update(['idTerlec' => $newTerlec]);
                    $curso->update(['idTerlec' => $newTerlec]);
                }

                if ((int) $curso->idNivel !== $newNivel) {
                    DB::table('materias')
                        ->where('idCursos', (int) $curso->Id)
                        ->update(['idNivel' => $newNivel]);
                    $curso->update(['idNivel' => $newNivel]);
                }

                $rawOrden = $d['orden'] ?? null;
                $rawCursec = $d['cursec'] ?? null;
                $rawC = $d['c'] ?? null;
                $rawS = $d['s'] ?? null;
                $rawIdTurno = $d['idTurnoClase'] ?? null;
                $idTurnoClase = ($rawIdTurno === '' || $rawIdTurno === null) ? null : (int) $rawIdTurno;
                if ($idTurnoClase !== null && $idTurnoClase <= 0) {
                    $idTurnoClase = null;
                }

                $payload = [
                    'orden' => ($rawOrden === '' || $rawOrden === null) ? null : (int) $rawOrden,
                    'cursec' => trim((string) $rawCursec) !== '' ? trim((string) $rawCursec) : null,
                    'c' => trim((string) $rawC) !== '' ? trim((string) $rawC) : null,
                    's' => trim((string) $rawS) !== '' ? trim((string) $rawS) : null,
                    'idTurnoClase' => $idTurnoClase,
                ];

                $curso->update($payload);
            });
        } catch (QueryException $e) {
            report($e);
            $sqlState = (string) ($e->errorInfo[0] ?? '');
            $errno = (int) ($e->errorInfo[1] ?? 0);
            $msg = 'No se pudo guardar el curso en la base de datos.';
            if ($errno === 1452 || str_contains(mb_strtolower($e->getMessage()), 'foreign key constraint')) {
                $msg = 'No se pudo guardar: el turno elegido no existe en la tabla turnos_clase (restricción de clave foránea). Verifique que existan los ids 1–3 en turnos_clase.';
            } elseif ($errno === 1265 || str_contains(mb_strtolower($e->getMessage()), 'data truncated')) {
                $msg = 'No se pudo guardar: un valor no coincide con el tipo o longitud esperada en la base de datos.';
            } elseif ($errno === 1366) {
                $msg = 'No se pudo guardar: valor incorrecto para una columna (codificación o tipo). Revise idTurnoClase y demás campos del curso.';
            }
            if (config('app.debug')) {
                $msg .= ' ['.$sqlState.' / '.$errno.'] '.$e->getMessage();
            }
            session()->flash('error', $msg);
            return;
        } catch (\Throwable $e) {
            report($e);
            $msg = 'No se pudo guardar el curso por dependencias u otro error. No se aplicaron cambios.';
            if (config('app.debug')) {
                $msg .= ' '.$e->getMessage();
            }
            session()->flash('error', $msg);
            return;
        }

        // Si cambió idTerlec / idNivel puede "salir" del listado actual: igual cerramos edición
        $this->editingId = null;
        $this->resetValidation();
    }

    public function createQuick(): void
    {
        $key = 'cursos:create:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            session()->flash('success', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            return;
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();

        $planesIds = Plan::query()
            ->where('idNivel', $ctx->idNivel)
            ->pluck('id');

        $curplanId = (int) (Curplan::query()
            ->whereIn('idPlan', $planesIds)
            ->orderBy('idPlan')
            ->orderBy('curPlanCurso')
            ->value('id') ?? 0);

        if ($curplanId <= 0) {
            session()->flash('success', 'No hay cursos modelo disponibles para este nivel. Cree un CurPlan primero.');
            return;
        }

        DB::transaction(function () use ($ctx, $curplanId) {
            /** @var Curso $curso */
            $curso = Curso::create([
                'orden' => null,
                'idCurPlan' => $curplanId,
                'idTerlec' => (int) $ctx->idTerlec,
                'idNivel' => (int) $ctx->idNivel,
                'cursec' => null,
                'c' => null,
                's' => null,
                'idTurnoClase' => null,
            ]);

            $matplan = Matplan::query()
                ->where('idCurPlan', $curplanId)
                ->orderBy('ord')
                ->orderBy('id')
                ->get(['id', 'ord', 'matPlanMateria', 'abrev']);

            $rows = $matplan->map(function ($m) use ($curplanId, $ctx, $curso) {
                $row = [
                    'ord' => (int) $m->ord,
                    'idCurPlan' => $curplanId,
                    'idMatPlan' => (int) $m->id,
                    'idNivel' => (int) $ctx->idNivel,
                    'idCursos' => (int) $curso->Id,
                    'idTerlec' => (int) $ctx->idTerlec,
                    'materia' => (string) $m->matPlanMateria,
                    'abrev' => $m->abrev !== null && trim((string) $m->abrev) !== '' ? (string) $m->abrev : null,
                    'cierre1e' => 0,
                    'cierre2e' => 0,
                ];
                if (Schema::hasColumn('materias', 'esInstitucional')) {
                    $row['esInstitucional'] = 0;
                }

                return $row;
            })->all();

            if (! empty($rows)) {
                DB::table('materias')->insert($rows);
            }
        });

        session()->flash('success', 'Curso creado (en blanco) y materias copiadas del curso modelo.');
    }

    public function confirmDelete(int $id): void
    {
        $ctx = schoolCtx();

        $curso = Curso::query()
            ->with('curplan')
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->findOrFail($id);

        $countMatriculas = DB::table('matricula')->where('idCursos', $curso->Id)->count();
        $countCalificaciones = DB::table('calificaciones')->where('idCursos', $curso->Id)->count();

        if (($countMatriculas + $countCalificaciones) > 0) {
            $detail = collect([
                $countMatriculas ? "{$countMatriculas} matrículas" : null,
                $countCalificaciones ? "{$countCalificaciones} calificaciones" : null,
            ])->filter()->implode(', ');

            $this->deleteInfo = "No se puede eliminar el curso porque tiene: {$detail}.";
            $this->deleteId = null;
        } else {
            $label = $curso->nombreParaListado();
            $this->deleteId = (int) $curso->Id;
            $this->deleteInfo = "¿Confirma eliminar el curso \"{$label}\"? (Se eliminarán también sus materias del año)";
        }

        $this->showConfirm = true;
    }

    public function delete(): void
    {
        $key = 'cursos:delete:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('success', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showConfirm = false;
            $this->reset('deleteId', 'deleteInfo');
            return;
        }
        RateLimiter::hit($key, 60);

        if ($this->deleteId) {
            $ctx = schoolCtx();

            $curso = Curso::query()
                ->where('idNivel', $ctx->idNivel)
                ->where('idTerlec', $ctx->idTerlec)
                ->findOrFail($this->deleteId);

            DB::transaction(function () use ($curso) {
                DB::table('materias')->where('idCursos', $curso->Id)->delete();
                $curso->delete();
            });

            session()->flash('success', "Curso \"{$curso->nombreParaListado()}\" eliminado.");
        }

        $this->showConfirm = false;
        $this->reset('deleteId', 'deleteInfo');
    }

    public function render()
    {
        $ctx = schoolCtx();

        $cursos = Curso::query()
            ->with(['curplan.plan', 'terlec', 'nivel', 'turnoClase'])
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('idCurPlan')
            ->orderBy('Id')
            ->get();

        $planesIds = Plan::query()
            ->where('idNivel', $ctx->idNivel)
            ->pluck('id');

        $curplanes = Curplan::query()
            ->with('plan')
            ->whereIn('idPlan', $planesIds)
            ->orderBy('idPlan')
            ->orderBy('curPlanCurso')
            ->get();

        $terlecs = Terlec::paraSelector();

        $niveles = Nivel::query()
            ->orderBy('id')
            ->get(['id', 'nivel', 'abrev']);

        $turnosClase = TurnoClase::query()
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'nombre', 'codigo']);

        return view('livewire.abm.cursos.index', compact('cursos', 'curplanes', 'terlecs', 'niveles', 'turnosClase'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Gestión de Cursos / Grados / Salas']);
    }
}

