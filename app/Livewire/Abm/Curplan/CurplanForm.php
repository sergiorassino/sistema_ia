<?php

namespace App\Livewire\Abm\Curplan;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Support\PermisosConfiguracion;
use App\Models\Curplan;
use App\Models\Matplan;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CurplanForm extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::CURSOS_MATERIAS_PLAN;
    }

    public ?int $id = null;

    public int|string $idPlan = '';
    public string $curPlanCurso = '';

    public ?int $matDeleteId = null;
    public bool $showMatConfirm = false;
    public string $matDeleteInfo = '';

    // Materias (inline por fila)
    public ?int $matEditingId = null; // null = no edita, 0 = crea, >0 = edita
    public array $matDraft = [
        'ord' => 0,
        'matPlanMateria' => '',
        'abrev' => '',
        'codGE' => '',
        'codGE2' => '',
        'codGE3' => '',
    ];

    public function mount(?int $id = null): void
    {
        $this->id = $id;
        if ($id) {
            $this->loadCurplan($id);
        }
        $this->resetMateriaDraft();
    }

    protected function rules(): array
    {
        $uniqueCurso = Rule::unique('curplan', 'curPlanCurso')
            ->where(fn ($q) => $q->where('idPlan', (int) $this->idPlan));

        if ($this->id) {
            $uniqueCurso = $uniqueCurso->ignore($this->id, 'id');
        }

        return [
            'idPlan' => ['required', 'integer', 'min:1', 'exists:planes,id'],
            'curPlanCurso' => ['required', 'string', 'max:30', $uniqueCurso],
        ];
    }

    protected function matRules(): array
    {
        return [
            'matDraft.matPlanMateria' => ['required', 'string', 'max:70'],
            'matDraft.ord' => ['required', 'integer', 'min:0', 'max:99'],
            'matDraft.abrev' => ['nullable', 'string', 'max:5'],
            'matDraft.codGE' => ['nullable', 'string', 'max:15'],
            'matDraft.codGE2' => ['nullable', 'string', 'max:15'],
            'matDraft.codGE3' => ['nullable', 'string', 'max:15'],
        ];
    }

    protected function messages(): array
    {
        return [
            'idPlan.required' => 'El plan es obligatorio.',
            'idPlan.exists' => 'El plan seleccionado no es válido.',
            'curPlanCurso.required' => 'El nombre del curso modelo es obligatorio.',
            'curPlanCurso.max' => 'El nombre no puede superar los 30 caracteres.',
            'curPlanCurso.unique' => 'Ya existe un curso modelo con ese nombre para ese plan.',

            'matDraft.matPlanMateria.required' => 'La materia es obligatoria.',
            'matDraft.matPlanMateria.max' => 'La materia no puede superar los 70 caracteres.',
            'matDraft.ord.required' => 'El orden es obligatorio.',
            'matDraft.ord.integer' => 'El orden debe ser un número.',
            'matDraft.ord.max' => 'El orden no puede ser mayor a 99.',
            'matDraft.abrev.max' => 'La abreviatura no puede superar los 5 caracteres.',
            'matDraft.codGE.max' => 'El código no puede superar los 15 caracteres.',
            'matDraft.codGE2.max' => 'El código no puede superar los 15 caracteres.',
            'matDraft.codGE3.max' => 'El código no puede superar los 15 caracteres.',
        ];
    }

    public function save(): mixed
    {
        $key = 'curplan:save:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('curPlanCurso', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            return null;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        if (is_string($this->idPlan)) {
            $this->idPlan = trim($this->idPlan);
        }
        $this->curPlanCurso = trim($this->curPlanCurso);

        $payload = [
            'idPlan' => (int) $this->idPlan,
            'curPlanCurso' => $this->curPlanCurso,
        ];

        if ($this->id) {
            Curplan::findOrFail($this->id)->update($payload);
            session()->flash('success', "Curso modelo \"{$this->curPlanCurso}\" actualizado.");
        } else {
            $nuevo = Curplan::create($payload);
            $this->id = (int) $nuevo->id;
            session()->flash('success', "Curso modelo \"{$this->curPlanCurso}\" creado.");
        }

        return redirect()->route('abm.curplan.edit', ['id' => $this->id]);
    }

    public function cancel(): mixed
    {
        return redirect()->route('abm.curplan');
    }

    // ── Materias ──
    public function resetMateriaDraft(): void
    {
        $this->reset('matEditingId', 'matDeleteId', 'showMatConfirm', 'matDeleteInfo');
        $this->matDraft = [
            'ord' => 0,
            'matPlanMateria' => '',
            'abrev' => '',
            'codGE' => '',
            'codGE2' => '',
            'codGE3' => '',
        ];
    }

    public function openMateriaCreate(): void
    {
        $this->resetMateriaDraft();
        $this->matEditingId = 0;
        $this->resetValidation();
    }

    public function openMateriaEdit(int $id): void
    {
        if (! $this->id) {
            return;
        }

        $m = Matplan::where('idCurPlan', (int) $this->id)->findOrFail($id);
        $this->matEditingId = (int) $m->id;
        $this->matDraft = [
            'ord' => (int) $m->ord,
            'matPlanMateria' => (string) $m->matPlanMateria,
            'abrev' => (string) ($m->abrev ?? ''),
            'codGE' => (string) ($m->codGE ?? ''),
            'codGE2' => (string) ($m->codGE2 ?? ''),
            'codGE3' => (string) ($m->codGE3 ?? ''),
        ];
        $this->resetValidation();
    }

    public function cancelMateriaEdit(): void
    {
        $this->resetMateriaDraft();
        $this->resetValidation();
    }

    public function saveMateriaRow(): void
    {
        if (! $this->id) {
            $this->addError('matDraft.matPlanMateria', 'Primero debe guardar el curso modelo para poder cargar materias.');
            return;
        }

        $key = 'matplan:save-row:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError('matDraft.matPlanMateria', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate($this->matRules());

        $d = $this->matDraft;
        $payload = [
            'idCurPlan' => (int) $this->id,
            'matPlanMateria' => trim((string) ($d['matPlanMateria'] ?? '')),
            'ord' => (int) ($d['ord'] ?? 0),
            'abrev' => trim((string) ($d['abrev'] ?? '')) !== '' ? trim((string) $d['abrev']) : null,
            'codGE' => trim((string) ($d['codGE'] ?? '')) !== '' ? trim((string) $d['codGE']) : null,
            'codGE2' => trim((string) ($d['codGE2'] ?? '')) !== '' ? trim((string) $d['codGE2']) : null,
            'codGE3' => trim((string) ($d['codGE3'] ?? '')) !== '' ? trim((string) $d['codGE3']) : null,
        ];

        if ($this->matEditingId && $this->matEditingId > 0) {
            Matplan::where('idCurPlan', (int) $this->id)->findOrFail($this->matEditingId)->update($payload);
            session()->flash('success', "Materia \"{$payload['matPlanMateria']}\" actualizada.");
        } else {
            Matplan::create($payload);
            session()->flash('success', "Materia \"{$payload['matPlanMateria']}\" agregada.");
        }

        $this->resetMateriaDraft();
    }

    public function confirmDeleteMateria(int $id): void
    {
        if (! $this->id) {
            return;
        }

        $m = Matplan::where('idCurPlan', (int) $this->id)->findOrFail($id);
        $this->matDeleteId = (int) $m->id;
        $this->matDeleteInfo = "¿Confirma eliminar la materia \"{$m->matPlanMateria}\"?";
        $this->showMatConfirm = true;
    }

    public function deleteMateria(): void
    {
        $key = 'matplan:delete:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            session()->flash('success', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showMatConfirm = false;
            $this->reset('matDeleteId', 'matDeleteInfo');
            return;
        }
        RateLimiter::hit($key, 60);

        if ($this->id && $this->matDeleteId) {
            $m = Matplan::where('idCurPlan', (int) $this->id)->findOrFail($this->matDeleteId);
            $nombre = (string) $m->matPlanMateria;
            $m->delete();
            session()->flash('success', "Materia \"{$nombre}\" eliminada.");
        }

        $this->showMatConfirm = false;
        $this->reset('matDeleteId', 'matDeleteInfo');
    }

    private function loadCurplan(int $id): void
    {
        $c = Curplan::findOrFail($id);
        $this->idPlan = (int) $c->idPlan;
        $this->curPlanCurso = (string) $c->curPlanCurso;
    }

    public function render()
    {
        $ctx = schoolCtx();

        $planes = Plan::query()
            ->where('idNivel', $ctx->idNivel)
            ->orderBy('id')
            ->get(['id', 'plan', 'abrev']);

        $materias = collect();
        if ($this->id) {
            $materias = Matplan::query()
                ->where('idCurPlan', (int) $this->id)
                ->orderBy('ord')
                ->orderBy('id')
                ->get();
        }

        return view('livewire.abm.curplan.form', compact('planes', 'materias'))
            ->layout(layoutMenuStaff(), ['pageTitle' => $this->id ? 'Editar curso modelo' : 'Nuevo curso modelo']);
    }
}

