<?php

namespace App\Livewire\CalificacionesPrimario;

use App\Models\Curso;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioCatalogo;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioDatos;
use App\Support\NivelSistema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

/**
 * Carga manual por materia (primario): etapa → curso → materia → grilla de alumnos.
 *
 * Parciales ic05–ic10 (1ª etapa) o ic11–ic16 (2ª); nota de etapa ic01/ic02; anual ic03 en 2ª etapa.
 */
class CargaCalificacionesPrimarioMateria extends Component
{
    /** 1 = 1ª etapa · 2 = 2ª etapa */
    public int $etapa = 1;

    public ?int $cursoId = null;

    public ?int $materiaId = null;

    public int $ciclo = 1;

    public int $ordMateria = 0;

    public string $cursoLabel = '';

    public string $materiaLabel = '';

    /**
     * @var list<array{campo: string, etiqueta: string}>
     */
    public array $columnasParciales = [];

    /** @var array{campo: string, etiqueta: string} */
    public array $columnaFinalEtapa = ['campo' => 'ic01', 'etiqueta' => 'Nota etapa'];

    /** @var ?array{campo: string, etiqueta: string} */
    public ?array $columnaAnual = null;

    /**
     * @var array<int, array{idMatricula: int, idCalificacion: ?int, alumno: string, notas: array<string, string>}>
     */
    public array $filas = [];

    /** @var list<string> */
    public array $notasPermitidasLista = [];

    public function mount(): void
    {
        abort_unless(tienePermiso(9), 403, 'Sin permiso para cargar calificaciones.');
        abort_unless(
            NivelSistema::esPrimario((int) schoolCtx()->idNivel),
            403,
            'Este módulo corresponde al nivel primario.'
        );

        $this->cargarNotasPermitidas();
        $this->aplicarColumnasEtapa();
    }

    public function updatedEtapa(mixed $value): void
    {
        $this->etapa = CalificacionesPrimarioCatalogo::normalizarEtapaCargaMateria((int) $value);
        $this->aplicarColumnasEtapa();
        $this->filas = [];
        if ($this->cursoId && $this->materiaId) {
            $this->loadGrid();
        }
    }

    public function updatedCursoId(mixed $value): void
    {
        $this->cursoId = ((int) $value) > 0 ? (int) $value : null;
        $this->materiaId = null;
        $this->filas = [];
    }

    public function updatedMateriaId(mixed $value): void
    {
        $this->materiaId = ((int) $value) > 0 ? (int) $value : null;
        $this->filas = [];

        if ($this->cursoId && $this->materiaId) {
            $this->loadGrid();
        }
    }

    protected function aplicarColumnasEtapa(): void
    {
        $cols = CalificacionesPrimarioCatalogo::columnasGrillaMateria($this->etapa);
        $this->columnasParciales = $cols['parciales'];
        $this->columnaFinalEtapa = $cols['finalEtapa'];
        $this->columnaAnual = $cols['anual'];
    }

    protected function ensureScopeOr404(): void
    {
        $ctx = schoolCtx();

        if (! $this->cursoId || ! $this->materiaId) {
            return;
        }

        $cursoOk = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', (int) $this->cursoId)
            ->exists();

        if (! $cursoOk) {
            abort(404);
        }

        $materiaOk = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('idCursos', (int) $this->cursoId)
            ->where('id', (int) $this->materiaId)
            ->exists();

        if (! $materiaOk) {
            abort(404);
        }
    }

    public function loadGrid(): void
    {
        $this->ensureScopeOr404();

        if (! $this->cursoId || ! $this->materiaId) {
            $this->filas = [];

            return;
        }

        $data = CalificacionesPrimarioDatos::cargarGrillaMateria(
            (int) $this->cursoId,
            (int) $this->materiaId,
            $this->etapa,
        );

        $this->ciclo = (int) $data['ciclo'];
        $this->ordMateria = (int) $data['ord'];
        $this->cursoLabel = (string) $data['cursoLabel'];
        $this->materiaLabel = (string) $data['materiaLabel'];
        $this->columnasParciales = $data['columnas']['parciales'];
        $this->columnaFinalEtapa = $data['columnas']['finalEtapa'];
        $this->columnaAnual = $data['columnas']['anual'];

        $this->filas = [];
        foreach ($data['filas'] as $fila) {
            $this->filas[(int) $fila['idMatricula']] = $fila;
        }
    }

    protected function cargarNotasPermitidas(): void
    {
        $this->notasPermitidasLista = [];
        $ctx = schoolCtx();
        $notas = DB::table('notaspermitidas')
            ->where('idNivel', (int) $ctx->idNivel)
            ->pluck('nota');

        foreach ($notas as $n) {
            $clave = trim((string) $n);
            if ($clave === '') {
                continue;
            }
            if (! in_array($clave, $this->notasPermitidasLista, true)) {
                $this->notasPermitidasLista[] = $clave;
            }
        }
    }

    protected function notasPermitidasActiva(): bool
    {
        return $this->notasPermitidasLista !== [];
    }

    protected function notaPermitida(string $nota): bool
    {
        if ($nota === '') {
            return true;
        }
        if (! $this->notasPermitidasActiva()) {
            return true;
        }

        return in_array($nota, $this->notasPermitidasLista, true);
    }

    public function saveCell(int $idMatricula, string $campo, mixed $value): void
    {
        abort_unless(tienePermiso(9), 403);

        $key = 'calificacionesPrimario:carga-materia:cell:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 240)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $this->ensureScopeOr404();

        $campo = trim($campo);
        $camposEtapa = CalificacionesPrimarioCatalogo::camposNotaGrillaMateria($this->etapa);
        if (! in_array($campo, $camposEtapa, true)) {
            abort(400);
        }

        if (CalificacionesPrimarioCatalogo::celdaInhabilitada($this->ciclo, $this->ordMateria, $campo)) {
            return;
        }

        $mat = CalificacionesPrimarioDatos::matriculaEnContexto($idMatricula);
        if ($mat === null || (int) $mat->idCursos !== (int) $this->cursoId) {
            abort(404, 'Matrícula no encontrada en el curso activo.');
        }

        $value = is_string($value) ? trim($value) : (string) ($value ?? '');

        Validator::make(
            ['value' => $value],
            ['value' => ['nullable', 'string', 'max:15']],
            [],
            ['value' => $campo],
        )->validate();

        if (! $this->notaPermitida($value)) {
            $guardado = (string) (DB::table('calificaciones')
                ->where('idMatricula', $idMatricula)
                ->where('ord', $this->ordMateria)
                ->value($campo) ?? '');
            if (isset($this->filas[$idMatricula])) {
                $this->filas[$idMatricula]['notas'][$campo] = $guardado;
            }

            return;
        }

        CalificacionesPrimarioDatos::guardarNota(
            $mat,
            $this->ordMateria,
            $campo,
            $value,
            (int) $this->materiaId,
        );

        if (isset($this->filas[$idMatricula])) {
            $this->filas[$idMatricula]['notas'][$campo] = $value;
            if ($this->filas[$idMatricula]['idCalificacion'] === null) {
                $idCalif = DB::table('calificaciones')
                    ->where('idMatricula', $idMatricula)
                    ->where('ord', $this->ordMateria)
                    ->value('id');
                $this->filas[$idMatricula]['idCalificacion'] = $idCalif !== null ? (int) $idCalif : null;
            }
        }
    }

    /**
     * @return Collection<int, Curso>
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
     * @return Collection<int, object{id: int, ord: int, materia: string, abrev: string}>
     */
    public function materiasDelCurso(): Collection
    {
        if (! $this->cursoId) {
            return collect();
        }

        $ctx = schoolCtx();
        $curso = Curso::query()
            ->with('curplan')
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', (int) $this->cursoId)
            ->first();

        if ($curso === null) {
            return collect();
        }

        $ciclo = CalificacionesPrimarioCatalogo::cicloDesdeCurso($curso);

        return CalificacionesPrimarioCatalogo::materiasParaCurso(
            (int) $curso->Id,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            $ciclo,
        );
    }

    public function render()
    {
        return view('livewire.calificaciones-primario.carga-calificaciones-primario-materia', [
            'cursos' => $this->cursos(),
            'materias' => $this->materiasDelCurso(),
            'notasPermitidasActiva' => $this->notasPermitidasActiva(),
            'notasPermitidasLista' => $this->notasPermitidasLista,
        ])->layout('layouts.app', ['pageTitle' => 'Carga de calificaciones por materia (primario)']);
    }
}
