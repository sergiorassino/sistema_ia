<?php

namespace App\Livewire\Seguimiento\Disciplinario;

use App\Livewire\Seguimiento\Disciplinario\Concerns\RequiresPermisoSeguimientoDisciplinario;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Sancion;
use App\Models\SancionTipo;
use App\Support\Database\PersistenciaColumnas;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\OrdenAlfabeticoEstudiante;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class SancionRegistroMultiple extends Component
{
    use RequiresPermisoSeguimientoDisciplinario;

    public int|string $idCurso = '';

    /** @var list<string> */
    public array $matriculasSeleccionadas = [];

    public int|string $idTipoSancion = '';

    public string $fecha = '';

    public string $fechaRegistroMostrar = '';

    public int|string $cantidad = '';

    public string $motivo = '';

    public string $solipor = '';

    public function mount(): void
    {
        $curso = ContextoEstudianteSesion::curso(ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO);
        if ($curso !== null) {
            $this->idCurso = (string) $curso;
        }

        $this->fecha = now()->format('Y-m-d');
        $this->fechaRegistroMostrar = now()->format('d/m/Y H:i');
    }

    public function updatedIdCurso(mixed $value): void
    {
        $this->idCurso = is_scalar($value) ? (string) $value : '';
        $this->matriculasSeleccionadas = [];
    }

    public function updatedMatriculasSeleccionadas(): void
    {
        $this->normalizarMatriculasSeleccionadas();
    }

    public function seleccionarTodasMatriculas(): void
    {
        $this->matriculasSeleccionadas = $this->alumnosDelCursoSeleccionado()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function quitarTodasMatriculas(): void
    {
        $this->matriculasSeleccionadas = [];
    }

    public function toggleSeleccionTodas(): void
    {
        if ($this->todasLasMatriculasMarcadas()) {
            $this->quitarTodasMatriculas();
        } else {
            $this->seleccionarTodasMatriculas();
        }
    }

    public function todasLasMatriculasMarcadas(): bool
    {
        $permitidos = $this->alumnosDelCursoSeleccionado()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->sort()
            ->values();

        if ($permitidos->isEmpty()) {
            return false;
        }

        $marcados = collect($this->matriculasSeleccionadas)
            ->map(fn ($v) => (string) $v)
            ->filter(fn ($v) => $v !== '')
            ->sort()
            ->values();

        return $marcados->all() === $permitidos->all();
    }

    protected function rules(): array
    {
        return [
            'idCurso' => ['required', 'integer', 'min:1'],
            'matriculasSeleccionadas' => ['required', 'array', 'min:1'],
            'matriculasSeleccionadas.*' => ['integer', 'min:1'],
            'idTipoSancion' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'cantidad' => ['nullable', 'integer', 'min:0', 'max:99'],
            'motivo' => ['nullable', 'string', 'max:2000'],
            'solipor' => ['nullable', 'string', 'max:150'],
        ];
    }

    protected function messages(): array
    {
        return [
            'idCurso.required' => 'Seleccione el curso.',
            'matriculasSeleccionadas.required' => 'Seleccione al menos un estudiante.',
            'matriculasSeleccionadas.min' => 'Seleccione al menos un estudiante.',
            'idTipoSancion.required' => 'Seleccione el tipo de registro.',
            'fecha.required' => 'Indique la fecha.',
            'fecha.date' => 'Fecha inválida.',
            'cantidad.integer' => 'Cantidad inválida.',
        ];
    }

    /** @return list<int> */
    private function idsCondicionesSelector(): array
    {
        return ListadoCursoCondicionFiltro::idCondicionesParaQuery(ListadoCursoCondicionFiltro::TODOS);
    }

    /** @return Collection<int, Curso> */
    private function cursosDelContexto(): Collection
    {
        return Curso::query()
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden', 'idTurnoClase', 'c', 's']);
    }

    /** @return Collection<int, object> */
    private function alumnosDelCursoSeleccionado(): Collection
    {
        $idCurso = (int) $this->idCurso;
        if ($idCurso <= 0) {
            return collect();
        }

        return Matricula::query()
            ->where('matricula.idNivel', schoolCtx()->idNivel)
            ->where('matricula.idTerlec', schoolCtx()->idTerlec)
            ->where('matricula.idCursos', $idCurso)
            ->whereIn('matricula.idCondiciones', $this->idsCondicionesSelector())
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->orderByRaw(OrdenAlfabeticoEstudiante::sql('legajos.apellido'))
            ->orderByRaw(OrdenAlfabeticoEstudiante::sql('legajos.nombre'))
            ->select([
                'matricula.id',
                'matricula.idLegajos',
                'legajos.apellido',
                'legajos.nombre',
                'legajos.dni',
            ])
            ->get();
    }

    protected function normalizarMatriculasSeleccionadas(): void
    {
        $allowed = $this->alumnosDelCursoSeleccionado()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->matriculasSeleccionadas = collect($this->matriculasSeleccionadas)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0 && in_array($id, $allowed, true))
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /** @return Collection<int, Matricula> */
    private function matriculasValidasSeleccionadas(): Collection
    {
        $this->normalizarMatriculasSeleccionadas();
        $ids = collect($this->matriculasSeleccionadas)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        $encontradas = Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->whereIn('idCondiciones', $this->idsCondicionesSelector())
            ->whereIn('id', $ids)
            ->get();

        return OrdenAlfabeticoEstudiante::ordenarMatriculas($encontradas);
    }

    public function save(): mixed
    {
        $key = 'sanciones:save-multiple:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('fecha', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return null;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $matriculas = $this->matriculasValidasSeleccionadas();
        if ($matriculas->isEmpty()) {
            $this->addError('matriculasSeleccionadas', 'Seleccione al menos un estudiante.');

            return null;
        }

        /** @var Matricula $primera */
        $primera = $matriculas->first();

        $payloadBase = [
            'idMatricula' => (int) $primera->id,
            'idTipoSancion' => (int) $this->idTipoSancion,
            'idProfesores' => (int) (schoolCtx()->idProfesor ?? 0),
            'fecha' => $this->fecha ?: null,
            'fechaRegistro' => now()->format('Y-m-d H:i:s'),
            'cantidad' => ($this->cantidad === '' || $this->cantidad === null) ? null : (int) $this->cantidad,
            'motivo' => trim($this->motivo) !== '' ? trim($this->motivo) : null,
            'solipor' => trim($this->solipor) !== '' ? trim($this->solipor) : null,
        ];

        $payloadBase = PersistenciaColumnas::adaptarEnterosVacios('sanciones', $payloadBase);
        $payloadBase = PersistenciaColumnas::reemplazarNulosExplicitos('sanciones', $payloadBase);
        $preparado = PersistenciaColumnas::prepararPayload('sanciones', $payloadBase);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            $mensaje = PersistenciaColumnas::mensajeColumnasInexistentes(
                'sanciones',
                $preparado['columnas_con_valor_sin_columna']
            );
            $this->addError('fechaRegistroMostrar', $mensaje);
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return null;
        }

        $payload = $preparado['payload'];
        $fechaRegistro = $payload['fechaRegistro'] ?? now()->format('Y-m-d H:i:s');
        $idsCreados = [];

        try {
            DB::transaction(function () use ($matriculas, $payload, $fechaRegistro, &$idsCreados): void {
                foreach ($matriculas as $mat) {
                    $fila = $payload;
                    $fila['idMatricula'] = (int) $mat->id;
                    $fila['fechaRegistro'] = $fechaRegistro;
                    $creada = Sancion::create($fila);
                    $idGuardado = (int) $creada->id;

                    $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
                        'sanciones',
                        ['id' => $idGuardado],
                        array_diff_key($fila, array_flip(['fecha']))
                    );
                    if ($noPersistidas !== []) {
                        throw new \RuntimeException(
                            PersistenciaColumnas::mensajeColumnasNoPersistidas('sanciones', $noPersistidas)
                        );
                    }

                    $idsCreados[] = $idGuardado;
                }
            });
        } catch (QueryException $e) {
            Log::warning('sancion-registro-multiple: error al guardar', [
                'code' => $e->getCode(),
            ]);
            $mensaje = PersistenciaColumnas::mensajeDesdeQueryException($e)
                ?? 'No se pudo guardar la sanción. Intente nuevamente.';
            $this->addError('fecha', $mensaje);
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return null;
        } catch (\RuntimeException $e) {
            $mensaje = $e->getMessage();
            $this->addError('fecha', $mensaje);
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return null;
        }

        $n = count($idsCreados);
        session()->flash(
            'success',
            $n === 1 ? 'Sanción creada.' : 'Sanción creada para '.$n.' estudiantes.'
        );

        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO, [
            'curso' => (int) $primera->idCursos,
            'matricula' => (int) $primera->id,
        ]);

        return redirect()->route('seguimiento.disciplinario');
    }

    public function render()
    {
        $alumnos = $this->alumnosDelCursoSeleccionado();
        $cantidadSeleccionados = collect($this->matriculasSeleccionadas)
            ->filter(fn ($v) => (int) $v > 0)
            ->count();

        return view('livewire.seguimiento.disciplinario.form-multiple', [
            'cursos' => $this->cursosDelContexto(),
            'alumnos' => $alumnos,
            'hayAlumnos' => $alumnos->isNotEmpty(),
            'todasMarcadas' => $this->todasLasMatriculasMarcadas(),
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'tipos' => SancionTipo::query()->orderBy('tipo')->get(['id', 'tipo']),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Registro múltiple']);
    }
}
