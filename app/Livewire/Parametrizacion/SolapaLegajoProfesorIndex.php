<?php

namespace App\Livewire\Parametrizacion;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Support\PermisosConfiguracion;
use App\Models\SolapaLegajoProfesor;
use Livewire\Component;

class SolapaLegajoProfesorIndex extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::SOLAPAS_LEGAJO_DOCENTE;
    }

    public bool $showModal = false;

    public ?int $editId = null;

    public string $nombre = '';

    public string $slug = '';

    public bool $showConfirm = false;

    public ?int $deleteId = null;

    public string $deleteInfo = '';

    public function nuevo(): void
    {
        $this->reset(['editId', 'nombre', 'slug']);
        $this->showModal = true;
    }

    public function editar(int $id): void
    {
        $s = SolapaLegajoProfesor::findOrFail($id);
        $this->editId = $id;
        $this->nombre = $s->nombre;
        $this->slug = $s->slug;
        $this->showModal = true;
    }

    public function cerrarModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function guardar(): void
    {
        $slugUnique = 'unique:solapas_legajo_profesor,slug'.($this->editId ? ",{$this->editId}" : '');

        $data = $this->validate([
            'nombre' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_-]+$/', $slugUnique],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'slug.required' => 'El slug es obligatorio.',
            'slug.regex' => 'El slug solo puede contener letras minúsculas, números, guiones y guiones bajos.',
            'slug.unique' => 'Ya existe una solapa con ese slug.',
        ]);

        if ($this->editId) {
            SolapaLegajoProfesor::findOrFail($this->editId)->update($data);
            session()->flash('status', "Solapa «{$data['nombre']}» actualizada.");
        } else {
            $maxOrden = (int) SolapaLegajoProfesor::query()->max('orden');
            SolapaLegajoProfesor::create(array_merge($data, ['orden' => $maxOrden + 1]));
            session()->flash('status', "Solapa «{$data['nombre']}» creada.");
        }

        $this->showModal = false;
    }

    public function confirmarEliminar(int $id): void
    {
        $s = SolapaLegajoProfesor::findOrFail($id);
        if ($s->slug === 'docente') {
            session()->flash('status', 'La solapa DOCENTE no puede eliminarse.');

            return;
        }
        $this->deleteId = $id;
        $this->deleteInfo = $s->nombre;
        $this->showConfirm = true;
    }

    public function eliminar(): void
    {
        if ($this->deleteId) {
            $s = SolapaLegajoProfesor::findOrFail($this->deleteId);
            if ($s->slug === 'docente') {
                session()->flash('status', 'La solapa DOCENTE no puede eliminarse.');
                $this->showConfirm = false;

                return;
            }
            $s->delete();
            session()->flash('status', 'Solapa eliminada. Los campos asignados quedaron sin solapa.');
        }

        $this->showConfirm = false;
        $this->reset('deleteId', 'deleteInfo');
    }

    public function subir(int $id): void
    {
        $actual = SolapaLegajoProfesor::findOrFail($id);
        $anterior = SolapaLegajoProfesor::where('orden', '<', $actual->orden)
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
        $actual = SolapaLegajoProfesor::findOrFail($id);
        $siguiente = SolapaLegajoProfesor::where('orden', '>', $actual->orden)
            ->orderBy('orden')
            ->first();

        if ($siguiente) {
            [$actual->orden, $siguiente->orden] = [$siguiente->orden, $actual->orden];
            $actual->save();
            $siguiente->save();
        }
    }

    public function render()
    {
        $solapas = SolapaLegajoProfesor::orderBy('orden')->get();

        return view('listados::parametrizacion.solapa-legajo-profesor-index', compact('solapas'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Solapas del Legajo del docente']);
    }
}
