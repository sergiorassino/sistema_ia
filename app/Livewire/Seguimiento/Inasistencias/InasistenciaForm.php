<?php

namespace App\Livewire\Seguimiento\Inasistencias;

use App\Livewire\Seguimiento\Inasistencias\Concerns\RequiresPermisoInasistenciasEstudiantesGestion;
use App\Models\Inasistencia;
use App\Models\InasistenciaValor;
use App\Models\Matricula;
use App\Support\Navegacion\ContextoEstudianteSesion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class InasistenciaForm extends Component
{
    use RequiresPermisoInasistenciasEstudiantesGestion;

    public ?int $id = null;

    public int|string $idMatricula = '';

    public int|string $tipo = '';

    public string $fecha = '';

    public string $cantidad = '';

    public string $just = '';

    public string $obs = '';

    public function mount(?int $id = null): void
    {
        $this->id = $id;

        if ($id) {
            $i = Inasistencia::query()
                ->with(['matricula.legajo', 'matricula.curso', 'valorTipo'])
                ->findOrFail($id);

            if ((int) ($i->matricula?->idNivel ?? 0) !== (int) schoolCtx()->idNivel
                || (int) ($i->matricula?->idTerlec ?? 0) !== (int) schoolCtx()->idTerlec) {
                abort(404);
            }

            $this->idMatricula = (string) $i->idMatricula;
            $tipoRaw = trim((string) ($i->tipo ?? ''));
            $this->tipo = $tipoRaw !== '' ? (string) (int) $tipoRaw : '';
            $this->fecha = $i->fecha ? $i->fecha->format('Y-m-d') : '';
            $this->cantidad = $i->cantidad !== null ? (string) $i->cantidad : '';
            $justRaw = strtoupper(trim((string) ($i->just ?? '')));
            $this->just = $justRaw === 'J' ? 'J' : 'I';
            $this->obs = trim((string) ($i->obs ?? ''));

            return;
        }

        $id = ContextoEstudianteSesion::matricula(ContextoEstudianteSesion::SEGUIMIENTO_INASISTENCIAS);
        abort_if($id === null, 404);
        $this->idMatricula = (string) $id;
        $this->fecha = now()->format('Y-m-d');
        $this->just = 'I';
    }

    public function updatedTipo(mixed $value): void
    {
        $idTipo = is_scalar($value) ? (int) $value : 0;
        if ($idTipo <= 0 || $this->cantidad !== '') {
            return;
        }

        $valor = InasistenciaValor::query()->find($idTipo);
        if ($valor && $valor->cantidad !== null) {
            $this->cantidad = (string) $valor->cantidad;
        }
    }

    protected function rules(): array
    {
        return [
            'idMatricula' => ['required', 'integer', 'min:1'],
            'tipo' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'cantidad' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'just' => ['required', 'string', 'in:J,I'],
            'obs' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function messages(): array
    {
        return [
            'tipo.required' => 'Seleccione el tipo de inasistencia.',
            'fecha.required' => 'Indique la fecha.',
            'fecha.date' => 'Fecha inválida.',
            'cantidad.numeric' => 'Cantidad inválida.',
            'obs.max' => 'Las observaciones no pueden superar 100 caracteres.',
        ];
    }

    private function matriculaDelContexto(int $id): Matricula
    {
        /** @var Matricula $m */
        $m = Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->findOrFail($id);

        return $m;
    }

    /** @return Collection<int, InasistenciaValor> */
    private function tipos(): Collection
    {
        return InasistenciaValor::query()
            ->orderBy('concepto')
            ->get(['id', 'concepto', 'cantidad']);
    }

    private function tipoExiste(int $idTipo): bool
    {
        return InasistenciaValor::query()->whereKey($idTipo)->exists();
    }

    public function save(): mixed
    {
        $key = 'inasistencias:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError('fecha', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return null;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $idTipo = (int) $this->tipo;
        if (! $this->tipoExiste($idTipo)) {
            $this->addError('tipo', 'Tipo de inasistencia inválido.');

            return null;
        }

        $m = $this->matriculaDelContexto((int) $this->idMatricula);

        $payload = [
            'idMatricula' => (int) $m->id,
            'tipo' => (string) $idTipo,
            'fecha' => $this->fecha ?: null,
            'cantidad' => trim($this->cantidad) !== '' ? round((float) $this->cantidad, 2) : null,
            'just' => strtoupper(trim($this->just)),
            'obs' => trim($this->obs) !== '' ? trim($this->obs) : null,
        ];

        if ($this->id) {
            $i = Inasistencia::query()->with('matricula')->findOrFail($this->id);
            if ((int) ($i->matricula?->idNivel ?? 0) !== (int) schoolCtx()->idNivel
                || (int) ($i->matricula?->idTerlec ?? 0) !== (int) schoolCtx()->idTerlec) {
                abort(404);
            }
            $i->update($payload);
            session()->flash('success', 'Inasistencia actualizada.');
        } else {
            $payload['just'] = 'I';
            Inasistencia::create($payload);
            session()->flash('success', 'Inasistencia registrada.');
        }

        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SEGUIMIENTO_INASISTENCIAS, [
            'curso' => (int) $m->idCursos,
            'matricula' => (int) $m->id,
        ]);

        return redirect()->route('seguimiento.inasistencias');
    }

    public function render()
    {
        $m = null;
        $idMat = (int) $this->idMatricula;
        if ($idMat > 0) {
            $m = $this->matriculaDelContexto($idMat);
        }

        $tipos = $this->tipos();

        return view('livewire.seguimiento.inasistencias.form', compact('m', 'tipos'))
            ->layout(layoutMenuStaff(), ['pageTitle' => $this->id ? 'Editar inasistencia' : 'Nueva inasistencia']);
    }
}
