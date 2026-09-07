<?php

namespace App\Livewire\Seguimiento\Gabinete;

use App\Livewire\Seguimiento\Gabinete\Concerns\RequiresPermisoSeguimientoGabinete;
use App\Models\Gabinete;
use App\Models\Matricula;
use App\Support\Database\PersistenciaColumnas;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\Seguimiento\GabineteOrientacion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class GabineteForm extends Component
{
    use RequiresPermisoSeguimientoGabinete;

    public ?int $id = null;

    public int|string $idMatricula = '';

    public int|string $idTipoSancion = '';

    public string $fecha = '';

    public string $solipor = '';

    public string $motivo = '';

    public string $asistentes = '';

    public string $conclusion = '';

    public function mount(?int $id = null): void
    {
        abort_unless(GabineteOrientacion::tablasDisponibles(), 404, 'No hay tablas de gabinete en esta base.');

        $this->id = $id;

        if ($id) {
            $reg = GabineteOrientacion::registroEnAlcance($id);
            $actual = GabineteOrientacion::matriculaActualDelLegajo((int) $reg->matricula?->idLegajos);
            abort_if($actual === null, 404);

            $this->idMatricula = (string) $actual->id;
            $this->idTipoSancion = (string) ($reg->idTipoSancion ?? '');
            $this->fecha = $reg->fecha ? $reg->fecha->format('Y-m-d') : '';
            $this->solipor = (string) ($reg->solipor ?? '');
            $this->motivo = (string) ($reg->motivo ?? '');
            $this->asistentes = (string) ($reg->asistentes ?? '');
            $this->conclusion = (string) ($reg->conclusion ?? '');

            return;
        }

        $idMat = ContextoEstudianteSesion::matricula(ContextoEstudianteSesion::SEGUIMIENTO_GABINETE);
        abort_if($idMat === null, 404);
        $this->idMatricula = (string) $idMat;
        $this->fecha = now()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'idMatricula' => ['required', 'integer', 'min:1'],
            'idTipoSancion' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'solipor' => ['nullable', 'string', 'max:500'],
            'motivo' => ['nullable', 'string', 'max:5000'],
            'asistentes' => ['nullable', 'string', 'max:5000'],
            'conclusion' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'idTipoSancion.required' => 'Seleccione el tipo de seguimiento.',
            'fecha.required' => 'Indique la fecha.',
            'fecha.date' => 'Fecha inválida.',
        ];
    }

    private function matriculaDelContexto(int $id): Matricula
    {
        return GabineteOrientacion::matriculaEnContexto($id);
    }

    /** @return Collection<int, \App\Models\GabineteTipo> */
    private function tipos(): Collection
    {
        return GabineteOrientacion::tipos();
    }

    public function save(): mixed
    {
        $key = 'gabinete:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError('fecha', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return null;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $idTipo = (int) $this->idTipoSancion;
        if (! GabineteOrientacion::tipoExiste($idTipo)) {
            $this->addError('idTipoSancion', 'Tipo de seguimiento inválido.');

            return null;
        }

        $m = $this->matriculaDelContexto((int) $this->idMatricula);

        $payload = [
            'idMatricula' => (int) $m->id,
            'idTipoSancion' => $idTipo,
            'fecha' => $this->fecha ?: null,
            'solipor' => trim($this->solipor) !== '' ? trim($this->solipor) : null,
            'motivo' => trim($this->motivo) !== '' ? trim($this->motivo) : null,
            'asistentes' => trim($this->asistentes) !== '' ? trim($this->asistentes) : null,
            'conclusion' => trim($this->conclusion) !== '' ? trim($this->conclusion) : null,
        ];

        $preparado = PersistenciaColumnas::prepararPayload(GabineteOrientacion::TABLA, $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            $this->addError(
                'idTipoSancion',
                PersistenciaColumnas::mensajeColumnasInexistentes(
                    GabineteOrientacion::TABLA,
                    $preparado['columnas_con_valor_sin_columna']
                )
            );

            return null;
        }

        $datos = PersistenciaColumnas::reemplazarNulosExplicitos(
            GabineteOrientacion::TABLA,
            $preparado['payload']
        );
        $datos = PersistenciaColumnas::adaptarEnterosVacios(GabineteOrientacion::TABLA, $datos);

        try {
            if ($this->id) {
                $reg = GabineteOrientacion::registroEnAlcance($this->id);
                if ((int) ($reg->matricula?->idLegajos ?? 0) !== (int) $m->idLegajos) {
                    abort(404);
                }
                unset($datos['idMatricula']);
                $reg->forceFill($datos)->save();
                $idGuardado = (int) $reg->id;
                $msg = 'Registro de gabinete actualizado.';
            } else {
                $datos = PersistenciaColumnas::completarNotNullSinDefault(GabineteOrientacion::TABLA, $datos);
                $reg = new Gabinete();
                $reg->forceFill($datos)->save();
                $idGuardado = (int) $reg->id;
                $msg = 'Registro de gabinete creado.';
            }
        } catch (QueryException $e) {
            $this->addError(
                'idTipoSancion',
                PersistenciaColumnas::mensajeDesdeQueryException($e) ?? 'No se pudo guardar el registro.'
            );

            return null;
        }

        $esperados = array_filter($payload, static fn ($v) => $v !== null);
        unset($esperados['idMatricula']);
        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
            GabineteOrientacion::TABLA,
            ['id' => $idGuardado],
            $esperados
        );
        if ($noPersistidas !== []) {
            $this->addError(
                'idTipoSancion',
                PersistenciaColumnas::mensajeColumnasNoPersistidas(GabineteOrientacion::TABLA, $noPersistidas)
            );

            return null;
        }

        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SEGUIMIENTO_GABINETE, [
            'curso' => (int) $m->idCursos,
            'matricula' => (int) $m->id,
        ]);

        session()->flash('success', $msg);

        return redirect()->route('seguimiento.gabinete.alumno');
    }

    public function delete(): mixed
    {
        if (! $this->id) {
            return null;
        }

        $key = 'gabinete:delete:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return null;
        }
        RateLimiter::hit($key, 60);

        $m = $this->matriculaDelContexto((int) $this->idMatricula);
        $reg = GabineteOrientacion::registroEnAlcance((int) $this->id);
        if ((int) ($reg->matricula?->idLegajos ?? 0) !== (int) $m->idLegajos) {
            abort(404);
        }

        $reg->delete();

        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SEGUIMIENTO_GABINETE, [
            'curso' => (int) $m->idCursos,
            'matricula' => (int) $m->id,
        ]);

        session()->flash('success', 'Registro de gabinete borrado.');

        return redirect()->route('seguimiento.gabinete.alumno');
    }

    public function render()
    {
        $m = $this->matriculaDelContexto((int) $this->idMatricula);
        $tipos = $this->tipos();

        return view('livewire.seguimiento.gabinete.form', compact('m', 'tipos'))
            ->layout(layoutMenuStaff(), ['pageTitle' => $this->id ? 'Editar seguimiento de gabinete' : 'Nuevo seguimiento de gabinete']);
    }
}
