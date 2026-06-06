<?php

namespace App\Livewire\Parametrizacion;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Support\PermisosConfiguracion;
use App\Models\SolapaLegajo;
use Livewire\Component;

class SolapaLegajoIndex extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::SOLAPAS_LEGAJO_ESTUDIANTE;
    }

    // ── Modal crear/editar ────────────────────────────────────────────────────
    public bool   $showModal    = false;
    public ?int   $editId       = null;
    public string $nombre       = '';
    public string $slug         = '';

    // ── Confirmación eliminar ─────────────────────────────────────────────────
    public bool   $showConfirm  = false;
    public ?int   $deleteId     = null;
    public string $deleteInfo   = '';

    // ── Abrir modal ───────────────────────────────────────────────────────────

    public function nuevo(): void
    {
        $this->reset(['editId', 'nombre', 'slug']);
        $this->showModal = true;
    }

    public function editar(int $id): void
    {
        $s = SolapaLegajo::findOrFail($id);
        $this->editId = $id;
        $this->nombre = $s->nombre;
        $this->slug   = $s->slug;
        $this->showModal = true;
    }

    public function cerrarModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    // ── Guardar ───────────────────────────────────────────────────────────────

    public function guardar(): void
    {
        $slugUnique = 'unique:solapas_legajo,slug' . ($this->editId ? ",{$this->editId}" : '');

        $data = $this->validate([
            'nombre' => ['required', 'string', 'max:60'],
            'slug'   => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_-]+$/', $slugUnique],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'slug.required'   => 'El slug es obligatorio.',
            'slug.regex'      => 'El slug solo puede contener letras minúsculas, números, guiones y guiones bajos.',
            'slug.unique'     => 'Ya existe una solapa con ese slug.',
        ]);

        if ($this->editId) {
            SolapaLegajo::findOrFail($this->editId)->update($data);
            session()->flash('status', "Solapa «{$data['nombre']}» actualizada.");
        } else {
            $maxOrden = (int) SolapaLegajo::query()->max('orden');
            SolapaLegajo::create(array_merge($data, ['orden' => $maxOrden + 1]));
            session()->flash('status', "Solapa «{$data['nombre']}» creada.");
        }

        $this->showModal = false;
    }

    // ── Eliminar ──────────────────────────────────────────────────────────────

    public function confirmarEliminar(int $id): void
    {
        $s = SolapaLegajo::findOrFail($id);
        $this->deleteId   = $id;
        $this->deleteInfo = $s->nombre;
        $this->showConfirm = true;
    }

    public function eliminar(): void
    {
        if ($this->deleteId) {
            // Los campos asignados quedan con solapa_legajo_id = null (nullOnDelete en FK)
            SolapaLegajo::findOrFail($this->deleteId)->delete();
            session()->flash('status', "Solapa eliminada. Los campos asignados quedaron sin solapa.");
        }

        $this->showConfirm = false;
        $this->reset('deleteId', 'deleteInfo');
    }

    // ── Reordenar ─────────────────────────────────────────────────────────────

    public function subir(int $id): void
    {
        $actual = SolapaLegajo::findOrFail($id);
        $anterior = SolapaLegajo::where('orden', '<', $actual->orden)
            ->orderByDesc('orden')
            ->first();

        if ($anterior) {
            [$actual->orden, $anterior->orden] = [$anterior->orden, $actual->orden];
            $actual->save();
            $anterior->save();
        }
    }

    public function bajar(int $id): void
    {
        $actual = SolapaLegajo::findOrFail($id);
        $siguiente = SolapaLegajo::where('orden', '>', $actual->orden)
            ->orderBy('orden')
            ->first();

        if ($siguiente) {
            [$actual->orden, $siguiente->orden] = [$siguiente->orden, $actual->orden];
            $actual->save();
            $siguiente->save();
        }
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        $solapas = SolapaLegajo::orderBy('orden')->get();

        return view('listados::parametrizacion.solapa-legajo-index', compact('solapas'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Solapas del Legajo']);
    }
}
