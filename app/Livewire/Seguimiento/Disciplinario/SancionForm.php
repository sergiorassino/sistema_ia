<?php

namespace App\Livewire\Seguimiento\Disciplinario;

use App\Livewire\Seguimiento\Disciplinario\Concerns\RequiresPermisoSeguimientoDisciplinario;
use App\Models\Matricula;
use App\Models\Sancion;
use App\Models\SancionTipo;
use App\Support\Database\PersistenciaColumnas;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\Navegacion\ContextoEstudianteSesion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class SancionForm extends Component
{
    use RequiresPermisoSeguimientoDisciplinario;

    public ?int $id = null; // id sancion (edit)
    public int|string $idMatricula = '';

    public int|string $idTipoSancion = '';
    public string $fecha = '';
    /** Solo lectura en UI: alta = ahora; edición = valor persistido. */
    public string $fechaRegistroMostrar = '';
    public int|string $cantidad = '';
    public string $motivo = '';
    public string $solipor = '';

    public function mount(?int $id = null): void
    {
        $this->id = $id;

        if ($id) {
            $s = Sancion::query()
                ->with(['matricula.legajo', 'matricula.curso'])
                ->findOrFail($id);

            // Seguridad: la sanción debe pertenecer al contexto (año/nivel via matrícula)
            if ((int) ($s->matricula?->idNivel ?? 0) !== (int) schoolCtx()->idNivel
                || (int) ($s->matricula?->idTerlec ?? 0) !== (int) schoolCtx()->idTerlec) {
                abort(404);
            }

            $this->idMatricula = (string) $s->idMatricula;
            $this->idTipoSancion = (string) ($s->idTipoSancion ?? '');
            $this->fecha = $s->fecha ? $s->fecha->format('Y-m-d') : '';
            $this->fechaRegistroMostrar = $s->fechaRegistro
                ? $s->fechaRegistro->format('d/m/Y H:i')
                : '—';
            $this->cantidad = $s->cantidad ?? '';
            $this->motivo = (string) ($s->motivo ?? '');
            $this->solipor = (string) ($s->solipor ?? '');

            return;
        }

        $id = ContextoEstudianteSesion::matricula(ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO);
        abort_if($id === null, 404);
        $this->idMatricula = (string) $id;
        $this->fecha = now()->format('Y-m-d');
        $this->fechaRegistroMostrar = now()->format('d/m/Y H:i');
    }

    protected function rules(): array
    {
        return [
            'idMatricula' => ['required', 'integer', 'min:1'],
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
            'idTipoSancion.required' => 'Seleccione el tipo de registro.',
            'fecha.required' => 'Indique la fecha.',
            'fecha.date' => 'Fecha inválida.',
            'cantidad.integer' => 'Cantidad inválida.',
        ];
    }

    private function matriculaDelContexto(int $id): Matricula
    {
        /** @var Matricula $m */
        $m = Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->whereIn('idCondiciones', ListadoCursoCondicionFiltro::idCondicionesParaQuery(
                ListadoCursoCondicionFiltro::TODOS
            ))
            ->findOrFail($id);

        return $m;
    }

    /** @return Collection<int, SancionTipo> */
    private function tipos(): Collection
    {
        return SancionTipo::query()
            ->orderBy('tipo')
            ->get(['id', 'tipo']);
    }

    public function save(): mixed
    {
        $key = 'sanciones:save:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError('fecha', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            return null;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $m = $this->matriculaDelContexto((int) $this->idMatricula);

        $payload = [
            'idMatricula' => (int) $m->id,
            'idTipoSancion' => (int) $this->idTipoSancion,
            'idProfesores' => (int) (schoolCtx()->idProfesor ?? 0),
            'fecha' => $this->fecha ?: null,
            'cantidad' => ($this->cantidad === '' || $this->cantidad === null) ? null : (int) $this->cantidad,
            'motivo' => trim($this->motivo) !== '' ? trim($this->motivo) : null,
            'solipor' => trim($this->solipor) !== '' ? trim($this->solipor) : null,
        ];

        if (! $this->id) {
            $payload['fechaRegistro'] = now()->format('Y-m-d H:i:s');
        }

        $payload = PersistenciaColumnas::adaptarEnterosVacios('sanciones', $payload);
        $payload = PersistenciaColumnas::reemplazarNulosExplicitos('sanciones', $payload);
        $preparado = PersistenciaColumnas::prepararPayload('sanciones', $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            $mensaje = PersistenciaColumnas::mensajeColumnasInexistentes(
                'sanciones',
                $preparado['columnas_con_valor_sin_columna']
            );
            $this->addError('fechaRegistroMostrar', $mensaje);
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return null;
        }

        try {
            if ($this->id) {
                $s = Sancion::findOrFail($this->id);
                // Revalidar alcance de la sanción
                if ((int) ($s->matricula?->idNivel ?? 0) !== (int) schoolCtx()->idNivel
                    || (int) ($s->matricula?->idTerlec ?? 0) !== (int) schoolCtx()->idTerlec) {
                    abort(404);
                }
                $s->update($preparado['payload']);
                $idGuardado = (int) $s->id;
                $mensajeExito = 'Sanción actualizada.';
            } else {
                $s = Sancion::create($preparado['payload']);
                $idGuardado = (int) $s->id;
                $mensajeExito = 'Sanción creada.';
            }
        } catch (QueryException $e) {
            Log::warning('sancion-form: error al guardar', [
                'id' => $this->id,
                'code' => $e->getCode(),
            ]);
            $mensaje = PersistenciaColumnas::mensajeDesdeQueryException($e)
                ?? 'No se pudo guardar la sanción. Intente nuevamente.';
            $this->addError('fecha', $mensaje);
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return null;
        }

        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
            'sanciones',
            ['id' => $idGuardado],
            array_diff_key($preparado['payload'], array_flip(['fecha']))
        );
        if ($noPersistidas !== []) {
            $mensaje = PersistenciaColumnas::mensajeColumnasNoPersistidas('sanciones', $noPersistidas);
            $this->addError('fecha', $mensaje);
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return null;
        }

        session()->flash('success', $mensajeExito);

        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO, [
            'curso' => (int) $m->idCursos,
            'matricula' => (int) $m->id,
        ]);

        return redirect()->route('seguimiento.disciplinario');
    }

    public function delete(): mixed
    {
        if (! $this->id) {
            return null;
        }

        $key = 'sanciones:delete:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return null;
        }
        RateLimiter::hit($key, 60);

        $m = $this->matriculaDelContexto((int) $this->idMatricula);

        $s = Sancion::query()
            ->with('matricula')
            ->where('idMatricula', (int) $m->id)
            ->findOrFail((int) $this->id);

        if ((int) ($s->matricula?->idNivel ?? 0) !== (int) schoolCtx()->idNivel
            || (int) ($s->matricula?->idTerlec ?? 0) !== (int) schoolCtx()->idTerlec) {
            abort(404);
        }

        $s->delete();

        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO, [
            'curso' => (int) $m->idCursos,
            'matricula' => (int) $m->id,
        ]);

        session()->flash('success', 'Evento borrado.');

        return redirect()->route('seguimiento.disciplinario');
    }

    public function render()
    {
        $m = null;
        $idMat = (int) $this->idMatricula;
        if ($idMat > 0) {
            // si no existe / fuera de contexto, 404 para evitar leaks
            $m = $this->matriculaDelContexto($idMat);
        }

        $tipos = $this->tipos();

        return view('livewire.seguimiento.disciplinario.form', compact('m', 'tipos'))
            ->layout(layoutMenuStaff(), ['pageTitle' => $this->id ? 'Editar sanción' : 'Nueva sanción']);
    }
}
