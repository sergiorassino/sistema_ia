<?php

namespace App\Livewire\CalificacionesInicial;

use App\Models\Curso;
use App\Support\CalificacionesInicial\CalificacionesInicialObservacionesDatos;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\CalificacionesDocenteSecundario;
use App\Support\PortalDocente\CalificacionesInicialPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * Carga de observaciones por espacio curricular (inicial): curso → materia → grilla alumnos × etapas.
 */
class CargaObservacionesInicialMateria extends Component
{
    public ?int $cursoId = null;

    public ?int $materiaId = null;

    public int $ordMateria = 0;

    public string $cursoLabel = '';

    public string $materiaLabel = '';

    /**
     * @var list<int>
     */
    public array $etapas = [];

    /**
     * @var array<int, array{
     *     idMatricula: int,
     *     idCalificacion: ?int,
     *     alumno: string,
     *     observaciones: array<int, string>
     * }>
     */
    public array $filas = [];

    public bool $modoPortalDocente = false;

    public function mount(): void
    {
        $this->modoPortalDocente = CalificacionesInicialPortalDocente::esPortalDocente();

        if ($this->modoPortalDocente) {
            CalificacionesInicialPortalDocente::abortSiMenuInactivo(
                CalificacionesInicialPortalDocente::MENU_OBSERVACIONES_MATERIA,
            );
        } else {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para calificaciones.',
            );
        }

        CalificacionesInicialPortalDocente::abortSiNoEsInicial();
        CalificacionesInicialObservacionesDatos::abortSiColumnasInexistentes();

        $this->etapas = CalificacionesInicialObservacionesDatos::etapasCarga();
    }

    public function updatedCursoId(mixed $value): void
    {
        $this->cursoId = ((int) $value) > 0 ? (int) $value : null;
        $this->materiaId = null;
        $this->filas = [];
        $this->ordMateria = 0;
        $this->cursoLabel = '';
        $this->materiaLabel = '';
    }

    public function updatedMateriaId(mixed $value): void
    {
        $this->materiaId = ((int) $value) > 0 ? (int) $value : null;
        $this->filas = [];

        if ($this->cursoId && $this->materiaId) {
            $this->loadGrid();
        }
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
            CalificacionesInicialPortalDocente::abortSiProfesorSinMateria(
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

        $data = CalificacionesInicialObservacionesDatos::cargarGrillaMateria(
            (int) $this->cursoId,
            (int) $this->materiaId,
        );

        $this->ordMateria = (int) $data['ord'];
        $this->cursoLabel = (string) $data['cursoLabel'];
        $this->materiaLabel = (string) $data['materiaLabel'];
        $this->etapas = $data['etapas'];

        $this->filas = [];
        foreach ($data['filas'] as $fila) {
            $this->filas[(int) $fila['idMatricula']] = $fila;
        }
    }

    #[Renderless]
    public function saveCell(int $idMatricula, string $campo, mixed $value): void
    {
        if (! $this->modoPortalDocente) {
            PortalDocenteContext::abortSiStaffSinPermisoIa(PermisosIaCatalog::CALIF_CARGA);
        }

        $key = 'calif-inicial-obs-mat:cell:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 240)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $this->ensureScopeOr404();

        $campo = trim($campo);
        if (! CalificacionesInicialObservacionesDatos::esCampoObservacion($campo)) {
            abort(400);
        }

        if (! $this->materiaId) {
            abort(400);
        }

        $ctx = schoolCtx();
        $materia = CalificacionesInicialObservacionesDatos::materiaEnContexto(
            (int) $this->materiaId,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );
        if ($materia === null) {
            abort(404);
        }

        $matricula = CalificacionesInicialObservacionesDatos::matriculaEnCursoDeMateria(
            $idMatricula,
            (int) $materia->idCursos,
        );
        if ($matricula === null) {
            abort(404, 'Matrícula no encontrada en el curso activo.');
        }

        $value = is_string($value) ? $value : (string) ($value ?? '');

        Validator::make(
            ['value' => $value],
            ['value' => ['nullable', 'string', 'max:'.CalificacionesInicialObservacionesDatos::MAX_CARACTERES]],
            [],
            ['value' => $campo],
        )->validate();

        CalificacionesInicialObservacionesDatos::guardarCelda($matricula, $materia, $campo, $value);

        if (isset($this->filas[$idMatricula])) {
            $etapa = $campo === 'obs02' ? 2 : 1;
            $this->filas[$idMatricula]['observaciones'][$etapa] = $value;
            if ($this->filas[$idMatricula]['idCalificacion'] === null) {
                $idCalif = DB::table('calificaciones')
                    ->where('idMatricula', $idMatricula)
                    ->where('ord', (int) $materia->ord)
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
            ->when($this->modoPortalDocente, fn ($q) => $q->whereIn('Id', CalificacionesInicialPortalDocente::idsCursosAsignados()))
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
    }

    /**
     * @return Collection<int, object{id: int, ord: int, materia: string}>
     */
    public function materiasDelCurso(): Collection
    {
        if (! $this->cursoId) {
            return collect();
        }

        $ctx = schoolCtx();

        return DB::table('materias')
            ->where('idCursos', (int) $this->cursoId)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->orderBy('ord')
            ->orderBy('id')
            ->get(['id', 'ord', 'materia'])
            ->when($this->modoPortalDocente, function (Collection $materias) {
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
        return view('livewire.calificaciones-inicial.carga-observaciones-inicial-materia', [
            'cursos' => $this->cursos(),
            'materias' => $this->materiasDelCurso(),
            'maxCaracteres' => CalificacionesInicialObservacionesDatos::MAX_CARACTERES,
        ])->layout(CalificacionesInicialPortalDocente::layout(), [
            'pageTitle' => 'Carga de observaciones por espacio curricular (inicial)',
        ]);
    }
}
