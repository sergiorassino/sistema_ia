<?php

namespace App\Livewire\Seguimiento\Tea;

use App\Livewire\Seguimiento\Tea\Concerns\RequiresPermisoTeaGestion;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\Tea\ReincoTea;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class TeaForm extends Component
{
    use RequiresPermisoTeaGestion;

    public ?int $id = null;

    public int|string $idMatricula = '';

    public int|string $idReincoTipo = '';

    public string $fecha = '';

    public string $obs = '';

    public function mount(?int $id = null): void
    {
        abort_unless(ReincoTea::tablasDisponibles(), 404, 'No hay tablas TEA (reinco2025).');

        $this->id = $id;

        if ($id) {
            $registro = ReincoTea::registroEnContexto($id);
            $this->idMatricula = (string) $registro->idMatricula;
            $this->idReincoTipo = (string) $registro->idReinco_tipo;
            $this->fecha = $registro->fecha ? $registro->fecha->format('Y-m-d') : '';
            $this->obs = trim((string) ($registro->obs ?? ''));

            return;
        }

        $idMatricula = ContextoEstudianteSesion::matricula(ContextoEstudianteSesion::SEGUIMIENTO_TEA);
        abort_if($idMatricula === null, 404);
        $this->idMatricula = (string) $idMatricula;
        $this->fecha = now()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'idMatricula' => ['required', 'integer', 'min:1'],
            'idReincoTipo' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'obs' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'idReincoTipo.required' => 'Seleccione la situación TEA.',
            'fecha.required' => 'Indique la fecha.',
            'fecha.date' => 'Fecha inválida.',
            'obs.max' => 'Las observaciones no pueden superar 2000 caracteres.',
        ];
    }

    /** @return Collection<int, \App\Models\ReincoTipo> */
    private function tipos(): Collection
    {
        return ReincoTea::tiposOrdenados();
    }

    public function save(): mixed
    {
        $key = 'tea-registros:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError('fecha', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return null;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $idTipo = (int) $this->idReincoTipo;
        if (! ReincoTea::tipoExiste($idTipo)) {
            $this->addError('idReincoTipo', 'Situación TEA inválida.');

            return null;
        }

        $matricula = ReincoTea::matriculaEnContexto((int) $this->idMatricula);

        $payload = [
            'idMatricula' => (int) $matricula->id,
            'idReinco_tipo' => $idTipo,
            'fecha' => $this->fecha ?: null,
            'obs' => trim($this->obs) !== '' ? trim($this->obs) : null,
        ];

        if ($this->id) {
            $registro = ReincoTea::registroEnContexto($this->id);
            $registro->update($payload);
            session()->flash('success', 'Registro TEA actualizado.');
        } else {
            ReincoTea::queryRegistros()->create($payload);
            session()->flash('success', 'Registro TEA registrado.');
        }

        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SEGUIMIENTO_TEA, [
            'curso' => (int) $matricula->idCursos,
            'matricula' => (int) $matricula->id,
        ]);

        return redirect()->route('seguimiento.tea');
    }

    public function render()
    {
        $matricula = null;
        $idMat = (int) $this->idMatricula;
        if ($idMat > 0) {
            $matricula = ReincoTea::matriculaEnContexto($idMat);
        }

        $tipos = $this->tipos();

        return view('livewire.seguimiento.tea.form', compact('matricula', 'tipos'))
            ->layout(layoutMenuStaff(), ['pageTitle' => $this->id ? 'Editar registro TEA' : 'Nuevo registro TEA']);
    }
}
