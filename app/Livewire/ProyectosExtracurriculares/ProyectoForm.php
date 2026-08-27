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

    /** @var list<string> */
    public array $idsCursos = [];

    /** @var list<string> */
    public array $idsAlumnos = [];

    /** @var list<string> */
    public array $idsDocentesACargo = [];

    /** @var list<string> */
    public array $idsOtrosDocentes = [];

    public string $filtroAlumno = '';

    public string $filtroDocente = '';

    public bool $modalAlumnos = false;

    public bool $soloLectura = false;

    public string $mensajeForm = '';

    public function mount(?string $ref = null): void
    {
        abort_unless(ExtActividadesService::tablasDisponibles(), 404, ExtActividadesService::mensajeTablasFaltantes());

        $this->descripcion = ExtActividadesService::DESCRIPCION_PLANTILLA;
        $this->fechas = [$this->filaFechaVacia()];
        $this->idsDocentesACargo = [(string) (int) Auth::id()];

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
        $this->idsCursos = $act->cursos->pluck('id_curso')->map(fn ($id) => (string) (int) $id)->all();
        $this->idsAlumnos = $act->alumnos->pluck('id_legajo')->map(fn ($id) => (string) (int) $id)->all();
        $this->idsDocentesACargo = $act->docentes
            ->where('rol', ExtActividad::ROL_A_CARGO)
            ->pluck('id_profesor')
            ->map(fn ($id) => (string) (int) $id)
            ->values()
            ->all();
        $this->idsOtrosDocentes = $act->docentes
            ->where('rol', ExtActividad::ROL_OTRO)
            ->pluck('id_profesor')
            ->map(fn ($id) => (string) (int) $id)
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

    public function updatedIdsCursos(): void
    {
        $this->normalizarIdsSeleccion();
    }

    public function updatedIdsDocentesACargo(): void
    {
        $this->normalizarIdsSeleccion();
        $this->idsOtrosDocentes = array_values(array_diff($this->idsOtrosDocentes, $this->idsDocentesACargo));
    }

    public function updatedIdsOtrosDocentes(): void
    {
        $this->normalizarIdsSeleccion();
        $this->idsDocentesACargo = array_values(array_diff($this->idsDocentesACargo, $this->idsOtrosDocentes));
    }

    public function seleccionarTodosCursos(): void
    {
        $this->idsCursos = ExtActividadesService::cursosDelContexto()
            ->pluck('Id')
            ->map(fn ($id) => (string) (int) $id)
            ->all();
    }

    public function limpiarCursos(): void
    {
        $this->idsCursos = [];
    }

    public function toggleCurso(int $idCurso): void
    {
        $id = (string) (int) $idCurso;
        if (in_array($id, $this->idsCursos, true)) {
            $this->idsCursos = array_values(array_diff($this->idsCursos, [$id]));

            return;
        }
        $this->idsCursos[] = $id;
    }

    public function toggleAlumno(int $idLegajo): void
    {
        $id = (string) (int) $idLegajo;
        if (in_array($id, $this->idsAlumnos, true)) {
            $this->idsAlumnos = array_values(array_diff($this->idsAlumnos, [$id]));

            return;
        }
        $this->idsAlumnos[] = $id;
    }

    public function quitarAlumno(int $idLegajo): void
    {
        $id = (string) (int) $idLegajo;
        $this->idsAlumnos = array_values(array_diff($this->idsAlumnos, [$id]));
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

        $this->mensajeForm = '';
        $this->resetErrorBag();

        if (! RateLimiter::attempt('ext-proy-guardar-'.(auth()->id() ?? 0), 40, fn () => true)) {
            $this->avisoError('Demasiados intentos. Espere un momento.');

            return;
        }

        $this->nombre = trim($this->nombre);
        $this->lugar = trim($this->lugar);
        $this->horario = trim($this->horario);
        $this->descripcion = trim($this->descripcion);
        $this->evaluacion = trim($this->evaluacion);
        $this->normalizarIdsSeleccion();
        $this->normalizarFechasHoras();

        try {
            $idsCursosValidos = array_map(
                static fn ($id) => (string) (int) $id,
                ExtActividadesService::cursosDelContexto()->pluck('Id')->all()
            );
            $idsDocentesValidos = array_map(
                static fn ($id) => (string) (int) $id,
                ExtActividadesService::filtrarIdsDocentesAula(array_merge(
                    $this->idsComoEnteros($this->idsDocentesACargo),
                    $this->idsComoEnteros($this->idsOtrosDocentes),
                ))
            );
            $idActual = (string) (int) Auth::id();
            if ($idActual !== '0' && ! in_array($idActual, $idsDocentesValidos, true)) {
                $idsDocentesValidos[] = $idActual;
            }
            $this->idsAlumnos = array_map(
                static fn ($id) => (string) (int) $id,
                ExtActividadesService::filtrarIdsAlumnosDelContexto($this->idsComoEnteros($this->idsAlumnos))
            );

            $this->idsCursos = array_values(array_intersect($this->idsComoTexto($this->idsCursos), $idsCursosValidos));
            $this->idsDocentesACargo = array_values(array_intersect($this->idsComoTexto($this->idsDocentesACargo), $idsDocentesValidos));
            $this->idsOtrosDocentes = array_values(array_intersect($this->idsComoTexto($this->idsOtrosDocentes), $idsDocentesValidos));

            $this->validate($this->reglas(), $this->mensajes());

            if ($this->tipo_grupo === ExtActividad::TIPO_GRUPO_CURSOS && $this->idsCursos === []) {
                $this->avisoError('Seleccione al menos un curso, o todos.', 'idsCursos');

                return;
            }
            if ($this->tipo_grupo === ExtActividad::TIPO_GRUPO_ALUMNOS && $this->idsAlumnos === []) {
                $this->avisoError('Seleccione al menos un alumno.', 'idsAlumnos');

                return;
            }
            if ($this->idsDocentesACargo === []) {
                $this->avisoError('Indique al menos un docente a cargo.', 'idsDocentesACargo');

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
                    'hora_inicio' => ExtActividadesService::formatearHora((string) ($fila['hora_inicio'] ?? '')),
                    'hora_fin' => ExtActividadesService::formatearHora((string) ($fila['hora_fin'] ?? '')),
                ];
            }
            if ($fechasNorm === []) {
                $this->avisoError('Indique al menos un día con fecha.', 'fechas');

                return;
            }

            ExtActividadesService::guardar($this->actividadId, [
                'nombre' => $this->nombre,
                'lugar' => $this->lugar,
                'horario' => $this->horario,
                'descripcion' => $this->descripcion,
                'evaluacion' => $this->evaluacion,
                'tipo_grupo' => $this->tipo_grupo,
                'fechas' => $fechasNorm,
                'ids_cursos' => $this->idsComoEnteros($this->idsCursos),
                'ids_alumnos' => $this->idsComoEnteros($this->idsAlumnos),
                'ids_docentes_a_cargo' => $this->idsComoEnteros($this->idsDocentesACargo),
                'ids_otros_docentes' => $this->idsComoEnteros($this->idsOtrosDocentes),
            ], (int) Auth::id());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $primero = collect($e->validator->errors()->all())->first();
            $this->avisoError(is_string($primero) && $primero !== ''
                ? $primero
                : 'Revise los datos del formulario.');
            throw $e;
        } catch (QueryException $e) {
            $this->avisoError(ExtActividadesService::mensajeDesdeQueryException($e));

            return;
        } catch (\RuntimeException $e) {
            $this->avisoError($e->getMessage());

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->avisoError('No se pudo guardar el proyecto. Intente nuevamente.');

            return;
        }

        $url = route('portalDocente.proyectosExtracurriculares.index');
        session()->flash('success', $this->actividadId ? 'Proyecto actualizado.' : 'Proyecto presentado a dirección.');
        $this->js('window.location.assign('.json_encode($url).')');
        $this->redirect($url, navigate: false);
    }

    private function avisoError(string $mensaje, ?string $campo = null): void
    {
        $this->mensajeForm = $mensaje;
        if ($campo !== null && $campo !== '') {
            $this->addError($campo, $mensaje);
        }
        $this->js('window.seSwalError('.json_encode($mensaje, JSON_UNESCAPED_UNICODE).')');
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
            'fechas.*.hora_inicio' => ['nullable', 'regex:/^(|([01]?\d|2[0-3]):[0-5]\d)$/'],
            'fechas.*.hora_fin' => ['nullable', 'regex:/^(|([01]?\d|2[0-3]):[0-5]\d)$/'],
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
            'fechas.*.hora_inicio.regex' => 'El horario de inicio no es válido.',
            'fechas.*.hora_fin.regex' => 'El horario de fin no es válido.',
            'tipo_grupo.in' => 'Seleccione cursos o alumnos.',
        ];
    }

    private function normalizarIdsSeleccion(): void
    {
        $this->idsCursos = $this->idsComoTexto($this->idsCursos);
        $this->idsAlumnos = $this->idsComoTexto($this->idsAlumnos);
        $this->idsDocentesACargo = $this->idsComoTexto($this->idsDocentesACargo);
        $this->idsOtrosDocentes = $this->idsComoTexto($this->idsOtrosDocentes);
    }

    /**
     * @param  list<mixed>  $ids
     * @return list<string>
     */
    private function idsComoTexto(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[] = (string) $n;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<mixed>  $ids
     * @return list<int>
     */
    private function idsComoEnteros(array $ids): array
    {
        return array_map('intval', $this->idsComoTexto($ids));
    }

    private function normalizarFechasHoras(): void
    {
        foreach ($this->fechas as $i => $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $this->fechas[$i]['hora_inicio'] = ExtActividadesService::formatearHora((string) ($fila['hora_inicio'] ?? ''));
            $this->fechas[$i]['hora_fin'] = ExtActividadesService::formatearHora((string) ($fila['hora_fin'] ?? ''));
        }
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
        $docentes = ExtActividadesService::docentesAulaParaSelector('', 200);
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
