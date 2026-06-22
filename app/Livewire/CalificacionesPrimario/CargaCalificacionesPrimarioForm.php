<?php

namespace App\Livewire\CalificacionesPrimario;

use App\Support\CalificacionesPrimario\CalificacionesPrimarioCatalogo;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioDatos;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;
use App\Support\PortalDocente\CalificacionesPrimarioPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * Formulario por alumno: grilla materias × etapas (ic01, ic02, ic03) y observaciones de matrícula.
 */
class CargaCalificacionesPrimarioForm extends Component
{
    public int $idMatricula;

    public int $cursoId;

    public int $ciclo = 1;

    public string $alumnoLinea = '';

    public string $cursoLabel = '';

    /** @var array<int, array{id: ?int, ic01: string, ic02: string, ic03: string}> */
    public array $notas = [];

    public string $obs1 = '';

    public string $obs2 = '';

    public string $obsAnual = '';

    /**
     * Lista secuencial para validación en navegador (mismo criterio que secundario).
     *
     * @var list<string>
     */
    public array $notasPermitidasLista = [];

    /**
     * Materias visibles (array serializable para Livewire; no usar Collection pública).
     *
     * @var list<array{id: int, ord: int, abrev: string, materia: string}>
     */
    public array $materiasLista = [];

    public bool $modoPortalDocente = false;

    public function mount(int $matricula): void
    {
        CalificacionesPrimarioModulos::abortSiModuloInactivo(CalificacionesPrimarioModulos::CARGA_ESTUDIANTE);

        $this->modoPortalDocente = CalificacionesPrimarioPortalDocente::esPortalDocente();

        if (! $this->modoPortalDocente) {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                \App\Support\PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para cargar calificaciones.',
            );
        }

        CalificacionesPrimarioPortalDocente::abortSiNoEsPrimario();

        $mat = CalificacionesPrimarioDatos::matriculaEnContexto($matricula);
        abort_if($mat === null, 404);

        if ($this->modoPortalDocente) {
            CalificacionesPrimarioPortalDocente::abortSiProfesorSinCurso((int) $mat->idCursos);
        }

        $this->idMatricula = (int) $mat->id;
        $this->cursoId = (int) $mat->idCursos;
        $this->cargarDesdeBd($mat);
        $this->cargarNotasPermitidas();
    }

    protected function cargarDesdeBd(\App\Models\Matricula $mat): void
    {
        $data = CalificacionesPrimarioDatos::cargarFormulario($mat);
        $this->ciclo = (int) $data['ciclo'];
        $this->materiasLista = CalificacionesPrimarioCatalogo::ordenarMateriasPorOrd($data['materias'])
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'ord' => (int) $m->ord,
                'abrev' => (string) $m->abrev,
                'materia' => (string) $m->materia,
            ])
            ->values()
            ->all();
        $this->notas = $data['notas'];
        $this->obs1 = $data['obs1'];
        $this->obs2 = $data['obs2'];
        $this->obsAnual = $data['obsAnual'];
        $this->alumnoLinea = $data['alumnoLinea'];
        $this->cursoLabel = $data['cursoLabel'];
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
    public function saveCell(int $ord, string $campo, mixed $value): void
    {
        PortalDocenteContext::abortSiStaffSinPermisoIa(\App\Support\PermisosIaCatalog::CALIF_CARGA);

        $key = 'calificacionesPrimario:carga:cell:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 240)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $campo = trim($campo);
        if (! in_array($campo, CalificacionesPrimarioCatalogo::camposNotaEditables(), true)) {
            abort(400);
        }

        if (CalificacionesPrimarioCatalogo::celdaInhabilitada($this->ciclo, $ord, $campo)) {
            return;
        }

        $mat = CalificacionesPrimarioDatos::matriculaEnContexto($this->idMatricula);
        if ($mat === null) {
            abort(404, 'Matrícula no encontrada en el contexto activo.');
        }

        $ctx = schoolCtx();
        $materia = CalificacionesPrimarioDatos::materiaDelCursoPorOrd(
            (int) $mat->idCursos,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            $ord,
        );
        if ($materia === null) {
            abort(404, 'Materia no encontrada para este curso y orden.');
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
                ->where('idMatricula', $this->idMatricula)
                ->where('ord', $ord)
                ->value($campo) ?? '');
            if (isset($this->notas[$ord])) {
                $this->notas[$ord][$campo] = $guardado;
            }

            return;
        }

        CalificacionesPrimarioDatos::guardarNota(
            $mat,
            $ord,
            $campo,
            $value,
            $materia->id,
        );

        if (isset($this->notas[$ord])) {
            $this->notas[$ord][$campo] = $value;
        }
    }

    #[Renderless]
    public function saveObservacion(string $campo, mixed $value): void
    {
        PortalDocenteContext::abortSiStaffSinPermisoIa(\App\Support\PermisosIaCatalog::CALIF_CARGA);

        $key = 'calificacionesPrimario:carga:obs:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 120)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $campo = trim($campo);
        if (! in_array($campo, CalificacionesPrimarioCatalogo::camposObservacionMatricula(), true)) {
            abort(400);
        }

        if (CalificacionesPrimarioDatos::matriculaEnContexto($this->idMatricula) === null) {
            abort(404);
        }

        $value = is_string($value) ? trim($value) : (string) ($value ?? '');
        $max = $campo === 'obsAnual' ? 500 : 1200;

        Validator::make(
            ['value' => $value],
            ['value' => ['nullable', 'string', 'max:'.$max]],
            [],
            ['value' => $campo],
        )->validate();

        CalificacionesPrimarioDatos::guardarObservacionMatricula($this->idMatricula, $campo, $value);

        if ($campo === 'obs1') {
            $this->obs1 = $value;
        } elseif ($campo === 'obs2') {
            $this->obs2 = $value;
        } else {
            $this->obsAnual = $value;
        }
    }

    public function render()
    {
        return view('livewire.calificaciones-primario.carga-calificaciones-primario-form', [
            'notasPermitidasLista' => $this->notasPermitidasLista,
            'notasPermitidasActiva' => $this->notasPermitidasActiva(),
        ])->layout(CalificacionesPrimarioPortalDocente::layout(), ['pageTitle' => 'Carga de calificaciones por estudiante']);
    }
}
