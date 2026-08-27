<?php

namespace App\Livewire\ProyectosExtracurriculares;

use App\Models\ExtActividad;
use App\Support\ProyectosExtracurriculares\ExtActividadesService;
use App\Support\Security\OpaqueRouteToken;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProyectoForm extends Component
{
    public ?int $actividadId = null;

    public string $nombre = '';

    public string $lugar = '';

    public string $horario = '';

    public string $descripcion = '';

    public string $evaluacion = '';

    public string $tipo_grupo = ExtActividad::TIPO_GRUPO_CURSOS;

    /** @var list<array{fecha: string, hora_inicio: string, hora_fin: string}> */
    public array $fechas = [];

    /** @var list<int> */
    public array $idsCursos = [];

    /** @var list<int> */
    public array $idsAlumnos = [];

    /** @var list<int> */
    public array $idsDocentesACargo = [];

    /** @var list<int> */
    public array $idsOtrosDocentes = [];

    public string $filtroAlumno = '';

    public string $filtroDocente = '';

    public bool $modalAlumnos = false;

    public bool $soloLectura = false;

    public function mount(?string $ref = null): void
    {
        abort_unless(ExtActividadesService::tablasDisponibles(), 404, ExtActividadesService::mensajeTablasFaltantes());

        $this->descripcion = ExtActividadesService::DESCRIPCION_PLANTILLA;
        $this->fechas = [$this->filaFechaVacia()];
        $this->idsDocentesACargo = [(int) Auth::id()];

        if ($ref === null || trim($ref) === '') {
            return;
        }

        $id = OpaqueRouteToken::decodeExtActividad($ref);
        abort_if($id === null, 404);

        $act = ExtActividadesService::cargarCompleta($id, (int) Auth::id());
        $this->actividadId = (int) $act->id;
        $this->soloLectura = $act->estaAprobada();
        $this->nombre = (string) $act->nombre;
        $this->lugar = (string) ($act->lugar ?? '');
        $this->horario = (string) ($act->horario ?? '');
        $this->descripcion = (string) ($act->descripcion ?? ExtActividadesService::DESCRIPCION_PLANTILLA);
        $this->evaluacion = (string) ($act->evaluacion ?? '');
        $this->tipo_grupo = (string) $act->tipo_grupo;
        $this->fechas = $act->fechas->map(fn ($f) => [
            'fecha' => $f->fecha instanceof Carbon ? $f->fecha->format('Y-m-d') : (string) $f->fecha,
            'hora_inicio' => ExtActividadesService::formatearHora((string) ($f->hora_inicio ?? '')),
            'hora_fin' => ExtActividadesService::formatearHora((string) ($f->hora_fin ?? '')),
        ])->values()->all();
        if ($this->fechas === []) {
            $this->fechas = [$this->filaFechaVacia()];
        }
        $this->idsCursos = $act->cursos->pluck('id_curso')->map(fn ($id) => (int) $id)->all();
        $this->idsAlumnos = $act->alumnos->pluck('id_legajo')->map(fn ($id) => (int) $id)->all();
        $this->idsDocentesACargo = $act->docentes
            ->where('rol', ExtActividad::ROL_A_CARGO)
            ->pluck('id_profesor')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $this->idsOtrosDocentes = $act->docentes
            ->where('rol', ExtActividad::ROL_OTRO)
            ->pluck('id_profesor')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function agregarFecha(): void
    {
        $this->fechas[] = $this->filaFechaVacia();
    }

    public function quitarFecha(int $indice): void
    {
        if (count($this->fechas) <= 1) {
            return;
        }
        unset($this->fechas[$indice]);
        $this->fechas = array_values($this->fechas);
    }

    public function updatedTipoGrupo(): void
    {
        if ($this->tipo_grupo !== ExtActividad::TIPO_GRUPO_CURSOS) {
            $this->tipo_grupo = ExtActividad::TIPO_GRUPO_ALUMNOS;
        }
    }

    public function seleccionarTodosCursos(): void
    {
        $this->idsCursos = ExtActividadesService::cursosDelContexto()
            ->pluck('Id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function limpiarCursos(): void
    {
        $this->idsCursos = [];
    }

    public function toggleCurso(int $idCurso): void
    {
        $idCurso = (int) $idCurso;
        if (in_array($idCurso, $this->idsCursos, true)) {
            $this->idsCursos = array_values(array_diff($this->idsCursos, [$idCurso]));

            return;
        }
        $this->idsCursos[] = $idCurso;
    }

    public function toggleAlumno(int $idLegajo): void
    {
        $idLegajo = (int) $idLegajo;
        if (in_array($idLegajo, $this->idsAlumnos, true)) {
            $this->idsAlumnos = array_values(array_diff($this->idsAlumnos, [$idLegajo]));

            return;
        }
        $this->idsAlumnos[] = $idLegajo;
    }

    public function quitarAlumno(int $idLegajo): void
    {
        $this->idsAlumnos = array_values(array_diff($this->idsAlumnos, [(int) $idLegajo]));
    }

    public function toggleDocenteACargo(int $idProfesor): void
    {
        $idProfesor = (int) $idProfesor;
        if (in_array($idProfesor, $this->idsDocentesACargo, true)) {
            $this->idsDocentesACargo = array_values(array_diff($this->idsDocentesACargo, [$idProfesor]));

            return;
        }
        $this->idsDocentesACargo[] = $idProfesor;
        $this->idsOtrosDocentes = array_values(array_diff($this->idsOtrosDocentes, [$idProfesor]));
    }

    public function toggleOtroDocente(int $idProfesor): void
    {
        $idProfesor = (int) $idProfesor;
        if (in_array($idProfesor, $this->idsOtrosDocentes, true)) {
            $this->idsOtrosDocentes = array_values(array_diff($this->idsOtrosDocentes, [$idProfesor]));

            return;
        }
        $this->idsOtrosDocentes[] = $idProfesor;
        $this->idsDocentesACargo = array_values(array_diff($this->idsDocentesACargo, [$idProfesor]));
    }

    public function abrirModalAlumnos(): void
    {
        $this->modalAlumnos = true;
    }

    public function cerrarModalAlumnos(): void
    {
        $this->modalAlumnos = false;
    }

    public function guardar(): void
    {
        if ($this->soloLectura) {
            return;
        }

        if (! RateLimiter::attempt('ext-proy-guardar-'.(auth()->id() ?? 0), 40, fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        $this->nombre = trim($this->nombre);
        $this->lugar = trim($this->lugar);
        $this->horario = trim($this->horario);
        $this->descripcion = trim($this->descripcion);
        $this->evaluacion = trim($this->evaluacion);

        $idsCursosValidos = ExtActividadesService::cursosDelContexto()
            ->pluck('Id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $idsDocentesValidos = collect(ExtActividadesService::docentesAulaParaSelector('', 2000))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $idActual = (int) Auth::id();
        if ($idActual > 0 && ! in_array($idActual, $idsDocentesValidos, true)) {
            $idsDocentesValidos[] = $idActual;
        }
        $this->idsAlumnos = ExtActividadesService::filtrarIdsAlumnosDelContexto($this->idsAlumnos);

        $this->idsCursos = array_values(array_intersect($this->idsCursos, $idsCursosValidos));
        $this->idsDocentesACargo = array_values(array_intersect($this->idsDocentesACargo, $idsDocentesValidos));
        $this->idsOtrosDocentes = array_values(array_intersect($this->idsOtrosDocentes, $idsDocentesValidos));

        $this->validate($this->reglas(), $this->mensajes());

        if ($this->tipo_grupo === ExtActividad::TIPO_GRUPO_CURSOS && $this->idsCursos === []) {
            $this->addError('idsCursos', 'Seleccione al menos un curso, o todos.');

            return;
        }
        if ($this->tipo_grupo === ExtActividad::TIPO_GRUPO_ALUMNOS && $this->idsAlumnos === []) {
            $this->addError('idsAlumnos', 'Seleccione al menos un alumno.');

            return;
        }
        if ($this->idsDocentesACargo === []) {
            $this->addError('idsDocentesACargo', 'Indique al menos un docente a cargo.');

            return;
        }

        $fechasNorm = [];
        foreach ($this->fechas as $fila) {
            $fecha = trim((string) ($fila['fecha'] ?? ''));
            if ($fecha === '') {
                continue;
            }
            $fechasNorm[] = [
                'fecha' => $fecha,
                'hora_inicio' => trim((string) ($fila['hora_inicio'] ?? '')),
                'hora_fin' => trim((string) ($fila['hora_fin'] ?? '')),
            ];
        }
        if ($fechasNorm === []) {
            $this->addError('fechas', 'Indique al menos un día con fecha.');

            return;
        }

        try {
            ExtActividadesService::guardar($this->actividadId, [
                'nombre' => $this->nombre,
                'lugar' => $this->lugar,
                'horario' => $this->horario,
                'descripcion' => $this->descripcion,
                'evaluacion' => $this->evaluacion,
                'tipo_grupo' => $this->tipo_grupo,
                'fechas' => $fechasNorm,
                'ids_cursos' => $this->idsCursos,
                'ids_alumnos' => $this->idsAlumnos,
                'ids_docentes_a_cargo' => $this->idsDocentesACargo,
                'ids_otros_docentes' => $this->idsOtrosDocentes,
            ], (int) Auth::id());
        } catch (QueryException $e) {
            $this->dispatch('se-swal-error', mensaje: ExtActividadesService::mensajeDesdeQueryException($e));

            return;
        }

        session()->flash('success', $this->actividadId ? 'Proyecto actualizado.' : 'Proyecto presentado a dirección.');
        $this->redirectRoute('portalDocente.proyectosExtracurriculares.index', navigate: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'lugar' => ['nullable', 'string', 'max:255'],
            'horario' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:8000'],
            'evaluacion' => ['nullable', 'string', 'max:4000'],
            'tipo_grupo' => ['required', Rule::in([ExtActividad::TIPO_GRUPO_CURSOS, ExtActividad::TIPO_GRUPO_ALUMNOS])],
            'fechas' => ['required', 'array', 'min:1'],
            'fechas.*.fecha' => ['required', 'date'],
            'fechas.*.hora_inicio' => ['nullable', 'date_format:H:i'],
            'fechas.*.hora_fin' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mensajes(): array
    {
        return [
            'nombre.required' => 'Indique el nombre de la actividad.',
            'descripcion.required' => 'Complete la breve descripción.',
            'fechas.required' => 'Indique al menos un día.',
            'fechas.*.fecha.required' => 'Cada día debe tener fecha.',
            'tipo_grupo.in' => 'Seleccione cursos o alumnos.',
        ];
    }

    /**
     * @return array{fecha: string, hora_inicio: string, hora_fin: string}
     */
    private function filaFechaVacia(): array
    {
        return [
            'fecha' => Carbon::today()->format('Y-m-d'),
            'hora_inicio' => '08:00',
            'hora_fin' => '12:00',
        ];
    }

    public function render()
    {
        $tipo = ExtActividadesService::tipoRegistroDefault();
        $cursos = ExtActividadesService::cursosDelContexto();
        $docentes = ExtActividadesService::docentesAulaParaSelector($this->filtroDocente);
        $idActual = (int) Auth::id();
        if ($idActual > 0 && ! collect($docentes)->contains(fn ($d) => (int) $d['id'] === $idActual)) {
            $yo = Auth::user();
            array_unshift($docentes, [
                'id' => $idActual,
                'label' => $yo ? trim((string) $yo->apellido.', '.(string) $yo->nombre) : 'Yo',
                'dni' => $yo && $yo->dni !== null ? (string) $yo->dni : '',
            ]);
        }
        $alumnosBusqueda = $this->modalAlumnos
            ? ExtActividadesService::alumnosParaSelector($this->filtroAlumno)
            : [];
        $alumnosElegidos = ExtActividadesService::alumnosPorIds($this->idsAlumnos);

        return view('livewire.proyectos-extracurriculares.form', [
            'tipoRegistroNombre' => $tipo?->nombre ?? 'Actividad Extraprogramática',
            'cursos' => $cursos,
            'docentes' => $docentes,
            'alumnosBusqueda' => $alumnosBusqueda,
            'alumnosElegidos' => $alumnosElegidos,
        ])->layout('layouts.docente', [
            'pageTitle' => $this->actividadId ? 'Editar proyecto extracurricular' : 'Nuevo proyecto extracurricular',
        ]);
    }
}
