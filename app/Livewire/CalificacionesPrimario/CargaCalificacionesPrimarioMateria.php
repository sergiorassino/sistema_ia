<?php

namespace App\Livewire\CalificacionesPrimario;

use App\Models\Curso;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioCatalogo;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioDatos;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\CalificacionesDocenteSecundario;
use App\Support\PortalDocente\CalificacionesPrimarioPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * Carga manual por materia (primario): etapa → curso → materia → grilla de alumnos.
 *
 * Parciales ic05–ic10 (1ª) o ic11–ic16 (2ª); nota etapa ic01/ic02; AP.FINAL ic03 e Intensif. dic en 2ª etapa.
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

    /** @var ?array{campo: string, etiqueta: string} */
    public ?array $columnaIntensificacion = null;

    /** @var array{campo: string, etiqueta: string} */
    public array $columnaObs = ['campo' => 'obs01', 'etiqueta' => 'Obs. etapa'];

    /**
     * @var array<int, array{idMatricula: int, idCalificacion: ?int, alumno: string, notas: array<string, string>}>
     */
    public array $filas = [];

    /** @var list<string> */
    public array $notasPermitidasLista = [];

    public bool $modoPortalDocente = false;

    public function mount(): void
    {
        CalificacionesPrimarioModulos::abortSiModuloInactivo(CalificacionesPrimarioModulos::CARGA_MATERIA);

        $this->modoPortalDocente = CalificacionesPrimarioPortalDocente::esPortalDocente();

        if (! $this->modoPortalDocente) {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para cargar calificaciones.',
            );
        }

        CalificacionesPrimarioPortalDocente::abortSiNoEsPrimario();

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
        $this->columnaIntensificacion = $cols['intensificacion'];
        $this->columnaObs = $cols['obs'];
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
            ->where('id', (int) $this->materiaId)
            ->where('idCursos', (int) $this->cursoId)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->exists();

        if (! $materiaOk) {
            abort(404);
        }

        if ($this->modoPortalDocente) {
            CalificacionesPrimarioPortalDocente::abortSiProfesorSinMateria(
                (int) $this->materiaId,
                (int) $this->cursoId,
            );
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
        $this->columnaIntensificacion = $data['columnas']['intensificacion'];
        $this->columnaObs = $data['columnas']['obs'];

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

    #[Renderless]
    public function saveCell(int $idMatricula, string $campo, mixed $value): void
    {
        PortalDocenteContext::abortSiStaffSinPermisoIa(PermisosIaCatalog::CALIF_CARGA);

        $key = 'calificacionesPrimario:carga-materia:cell:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 240)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $this->ensureScopeOr404();

        $campo = trim($campo);
        $esObsCalificacion = CalificacionesPrimarioCatalogo::esCampoObservacionCalificacion($campo);
        $camposEtapa = CalificacionesPrimarioCatalogo::camposGrillaMateriaEditables($this->etapa);
        if (! in_array($campo, $camposEtapa, true)) {
            abort(400);
        }

        if (! $esObsCalificacion && CalificacionesPrimarioCatalogo::celdaInhabilitada($this->ciclo, $this->ordMateria, $campo)) {
            return;
        }

        $mat = CalificacionesPrimarioDatos::matriculaEnContexto($idMatricula);
        if ($mat === null || (int) $mat->idCursos !== (int) $this->cursoId) {
            abort(404, 'Matrícula no encontrada en el curso activo.');
        }

        $value = is_string($value) ? trim($value) : (string) ($value ?? '');

        if ($esObsCalificacion) {
            Validator::make(
                ['value' => $value],
                ['value' => ['nullable', 'string', 'max:'.CalificacionesPrimarioCatalogo::MAX_CARACTERES_OBS_CALIFICACION]],
                [],
                ['value' => $campo],
            )->validate();

            CalificacionesPrimarioDatos::guardarObservacionCalificacion(
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

            return;
        }

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
            ->when($this->modoPortalDocente, fn ($q) => $q->whereIn('Id', CalificacionesPrimarioPortalDocente::idsCursosAsignados()))
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

        return CalificacionesPrimarioCatalogo::materiasParaSelectorAnio(
            (int) $this->cursoId,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        )->when($this->modoPortalDocente, function (Collection $materias) {
            $idProfesor = (int) (schoolCtx()->idProfesor ?? 0);

            return $materias->filter(
                fn ($m) => CalificacionesDocenteSecundario::profesorTieneMateria(
                    $idProfesor,
                    (int) $m->id,
                    (int) $this->cursoId,
                ),
            )->values();
        });
    }

    public function render()
    {
        return view('livewire.calificaciones-primario.carga-calificaciones-primario-materia', [
            'cursos' => $this->cursos(),
            'materias' => $this->materiasDelCurso(),
            'notasPermitidasActiva' => $this->notasPermitidasActiva(),
            'notasPermitidasLista' => $this->notasPermitidasLista,
        ])->layout(CalificacionesPrimarioPortalDocente::layout(), ['pageTitle' => 'Carga de calificaciones por materia (primario)']);
    }
}
