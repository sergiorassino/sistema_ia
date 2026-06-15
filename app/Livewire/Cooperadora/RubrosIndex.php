<?php

namespace App\Livewire\Cooperadora;

use App\Models\CoopIngreso;
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

    public bool $aplicaDescuentoHermano = false;

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
        $this->aplicaDescuentoHermano = (bool) ($rubro->aplica_descuento_hermano ?? false);
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
            'aplicaDescuentoHermano' => ['boolean'],
            'esAnual' => ['boolean'],
            'orden' => ['required', 'integer', 'min:0', 'max:999'],
            'activo' => ['boolean'],
        ]);

        $esAnual = $validated['esAnual'] && $validated['tipo'] === 'origen_estudiantes';
        $aplicaDescuentoHermano = $validated['tipo'] === 'origen_estudiantes'
            && (bool) $validated['aplicaDescuentoHermano'];

        $data = [
            'nombre' => trim($validated['nombre']),
            'tipo' => $validated['tipo'],
            'aplica_descuento_hermano' => $aplicaDescuentoHermano,
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

    public function eliminar(int $id): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);

        $key = 'coop:rubros-del:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente de nuevo.');

            return;
        }
        RateLimiter::hit($key, 60);

        $rubro = CoopRubroIngreso::query()->findOrFail($id);

        $tieneItems = $rubro->items()->exists();
        $tieneIngresos = CoopIngreso::query()->where('id_rubro', $rubro->id)->exists();

        if ($tieneItems || $tieneIngresos) {
            $partes = [];
            if ($tieneItems) {
                $partes[] = 'ítems asociados';
            }
            if ($tieneIngresos) {
                $partes[] = 'ingresos registrados';
            }
            $this->dispatch(
                'se-swal-aviso',
                mensaje: 'No se puede eliminar el rubro porque tiene '.implode(' e ', $partes).'.',
                titulo: 'Rubro en uso'
            );

            return;
        }

        $rubro->delete();

        if ($this->editId === $id) {
            $this->cerrarModal();
        }

        $this->dispatch('se-swal-exito', mensaje: 'Rubro eliminado.');
    }

    private function resetForm(): void
    {
        $this->editId = null;
        $this->nombre = '';
        $this->tipo = 'origen_estudiantes';
        $this->aplicaDescuentoHermano = false;
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
