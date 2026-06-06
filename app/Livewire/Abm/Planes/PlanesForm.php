<?php

namespace App\Livewire\Abm\Planes;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Support\PermisosConfiguracion;
use App\Models\Plan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PlanesForm extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::PLANES_ESTUDIO;
    }

    public ?int $id = null;

    public string $plan = '';
    public string $abrev = '';

    public function mount(?int $id = null): void
    {
        $this->id = $id;
        if ($id) {
            $this->loadPlan($id);
        }
    }

    protected function rules(): array
    {
        $ctx = schoolCtx();

        $uniquePlan = Rule::unique('planes', 'plan')
            ->where(fn ($q) => $q->where('idNivel', $ctx->idNivel));

        if ($this->id) {
            $uniquePlan = $uniquePlan->ignore($this->id, 'id');
        }

        return [
            'plan' => ['required', 'string', 'max:80', $uniquePlan],
            'abrev' => ['nullable', 'string', 'max:15'],
        ];
    }

    protected function messages(): array
    {
        return [
            'plan.required' => 'El nombre del plan es obligatorio.',
            'plan.max' => 'El nombre no puede superar los 80 caracteres.',
            'plan.unique' => 'Ya existe un plan con ese nombre en este nivel.',
            'abrev.max' => 'La abreviatura no puede superar los 15 caracteres.',
        ];
    }

    public function save(): mixed
    {
        $key = 'planes:save:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('plan', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            return null;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $ctx = schoolCtx();
        $this->plan = trim($this->plan);
        $this->abrev = trim($this->abrev);

        $payload = [
            'idNivel' => (int) $ctx->idNivel,
            'plan' => $this->plan,
            'abrev' => $this->abrev !== '' ? $this->abrev : null,
        ];

        if ($this->id) {
            Plan::query()
                ->where('idNivel', $ctx->idNivel)
                ->findOrFail($this->id)
                ->update($payload);

            session()->flash('success', "Plan \"{$this->plan}\" actualizado.");
        } else {
            $nuevo = Plan::create($payload);
            $this->id = (int) $nuevo->id;
            session()->flash('success', "Plan \"{$this->plan}\" creado.");
        }

        return redirect()->route('abm.planes.edit', ['id' => $this->id]);
    }

    private function loadPlan(int $id): void
    {
        $ctx = schoolCtx();

        $p = Plan::query()
            ->where('idNivel', $ctx->idNivel)
            ->findOrFail($id);

        $this->plan = (string) $p->plan;
        $this->abrev = (string) ($p->abrev ?? '');
    }

    public function render()
    {
        return view('livewire.abm.planes.form')
            ->layout(layoutMenuStaff(), ['pageTitle' => $this->id ? 'Editar plan de estudio' : 'Nuevo plan de estudio']);
    }
}

