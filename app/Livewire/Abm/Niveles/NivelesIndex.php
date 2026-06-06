<?php

namespace App\Livewire\Abm\Niveles;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Support\PermisosConfiguracion;
use App\Models\Nivel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class NivelesIndex extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::NIVELES;
    }

    public bool $showModal   = false;
    public bool $showConfirm = false;
    public ?int $editId      = null;
    public ?int $deleteId    = null;
    public string $deleteInfo = '';

    public string $nivel = '';
    public string $abrev = '';

    protected function rules(): array
    {
        return [
            'nivel' => ['required', 'string', 'max:50',
                        'unique:niveles,nivel' . ($this->editId ? ",{$this->editId},id" : '')],
            'abrev' => ['required', 'string', 'max:5'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nivel.required' => 'El nombre del nivel es obligatorio.',
            'nivel.max'      => 'El nombre no puede superar los 50 caracteres.',
            'nivel.unique'   => 'Ya existe un nivel con ese nombre.',
            'abrev.required' => 'La abreviatura es obligatoria.',
            'abrev.max'      => 'La abreviatura no puede superar los 5 caracteres.',
        ];
    }

    public function openCreate(): void
    {
        $this->reset('nivel', 'abrev', 'editId');
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $n = Nivel::findOrFail($id);
        $this->editId = $id;
        $this->nivel  = $n->nivel;
        $this->abrev  = $n->abrev;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $key = 'niveles:save:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('nivel', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $this->nivel = trim($this->nivel);
        $this->abrev = trim($this->abrev);

        if ($this->editId) {
            Nivel::findOrFail($this->editId)->update([
                'nivel' => $this->nivel,
                'abrev' => strtoupper($this->abrev),
            ]);
            session()->flash('success', "Nivel \"{$this->nivel}\" actualizado.");
        } else {
            Nivel::create([
                'nivel' => $this->nivel,
                'abrev' => strtoupper($this->abrev),
            ]);
            session()->flash('success', "Nivel \"{$this->nivel}\" creado.");
        }

        $this->showModal = false;
        $this->reset('nivel', 'abrev', 'editId');
    }

    public function confirmDelete(int $id): void
    {
        $nivel = Nivel::findOrFail($id);

        $countLegajos   = DB::table('legajos')->where('idnivel', $id)->count();
        $countMatricula = DB::table('matricula')->where('idNivel', $id)->count();
        $countCursos    = DB::table('cursos')->where('idNivel', $id)->count();
        $countProf      = DB::table('profesores')->where('nivel', $id)->count();

        $total = $countLegajos + $countMatricula + $countCursos + $countProf;

        if ($total > 0) {
            $detail = collect([
                $countLegajos   ? "{$countLegajos} legajos"     : null,
                $countMatricula ? "{$countMatricula} matrículas" : null,
                $countCursos    ? "{$countCursos} cursos"        : null,
                $countProf      ? "{$countProf} profesores"      : null,
            ])->filter()->implode(', ');

            $this->deleteInfo = "No se puede eliminar \"{$nivel->nivel}\" porque tiene: {$detail}.";
            $this->deleteId   = null;
        } else {
            $this->deleteId   = $id;
            $this->deleteInfo = "¿Confirma eliminar el nivel \"{$nivel->nivel}\"?";
        }

        $this->showConfirm = true;
    }

    public function delete(): void
    {
        $key = 'niveles:delete:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('success', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showConfirm = false;
            $this->reset('deleteId', 'deleteInfo');
            return;
        }
        RateLimiter::hit($key, 60);

        if ($this->deleteId) {
            $nivel = Nivel::findOrFail($this->deleteId);
            $nombre = $nivel->nivel;
            $nivel->delete();
            session()->flash('success', "Nivel \"{$nombre}\" eliminado.");
        }

        $this->showConfirm = false;
        $this->reset('deleteId', 'deleteInfo');
    }

    public function render()
    {
        $niveles = Nivel::orderBy('id')->get();

        return view('livewire.abm.niveles.index', compact('niveles'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Niveles Educativos']);
    }
}
