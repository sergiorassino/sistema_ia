<?php

namespace App\Livewire\Cooperadora;

use App\Models\CoopRubroIngreso;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

class RubrosIndex extends Component
{
    public bool $showModal = false;

    public ?int $editId = null;

    public string $nombre = '';

    public string $tipo = 'origen_estudiantes';

    public bool $esAnual = false;

    public string $orden = '0';

    public bool $activo = true;

    public function mount(): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);
    }

    public function abrirNuevo(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function abrirEditar(int $id): void
    {
        $rubro = CoopRubroIngreso::query()->findOrFail($id);
        $this->editId = $id;
        $this->nombre = (string) $rubro->nombre;
        $this->tipo = (string) $rubro->tipo;
        $this->esAnual = (bool) $rubro->es_anual;
        $this->orden = (string) $rubro->orden;
        $this->activo = (bool) $rubro->activo;
        $this->showModal = true;
    }

    public function cerrarModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function guardar(): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);

        $key = 'coop:rubros:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $validated = $this->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(CoopRubroIngreso::tiposValidos())],
            'esAnual' => ['boolean'],
            'orden' => ['required', 'integer', 'min:0', 'max:999'],
            'activo' => ['boolean'],
        ]);

        $esAnual = $validated['esAnual'] && $validated['tipo'] === 'origen_estudiantes';

        $data = [
            'nombre' => trim($validated['nombre']),
            'tipo' => $validated['tipo'],
            'es_anual' => $esAnual,
            'orden' => (int) $validated['orden'],
            'activo' => (bool) $validated['activo'],
        ];

        if ($this->editId) {
            CoopRubroIngreso::query()->whereKey($this->editId)->update($data);
        } else {
            CoopRubroIngreso::query()->create($data);
        }

        $this->cerrarModal();
    }

    private function resetForm(): void
    {
        $this->editId = null;
        $this->nombre = '';
        $this->tipo = 'origen_estudiantes';
        $this->esAnual = false;
        $this->orden = '0';
        $this->activo = true;
    }

    public function render()
    {
        $rubros = CoopRubroIngreso::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('livewire.cooperadora.rubros-index', compact('rubros'))
            ->layout(ProfesorMenuPortal::layoutStaff(), ['pageTitle' => 'Cooperadora — Rubros de ingreso']);
    }
}
