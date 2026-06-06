<?php

namespace App\Livewire\Abm\Terlec;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Models\Terlec;
use App\Support\PermisosConfiguracion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class TerlecIndex extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::TERLEC;
    }

    public bool $showModal    = false;
    public bool $showConfirm  = false;
    public ?int $editId       = null;
    public ?int $deleteId     = null;
    public string $deleteInfo = '';

    public int|string $ano   = '';
    public int|string $orden = '';

    protected function rules(): array
    {
        return [
            'ano'   => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100',
                        'unique:terlec,ano' . ($this->editId ? ",{$this->editId},id" : '')],
            'orden' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function messages(): array
    {
        return [
            'ano.required'   => 'El año es obligatorio.',
            'ano.digits'     => 'El año debe tener exactamente 4 dígitos.',
            'ano.min'        => 'El año debe ser mayor a 2000.',
            'ano.unique'     => 'Ya existe un término lectivo para ese año.',
            'orden.required' => 'El orden es obligatorio.',
            'orden.integer'  => 'El orden debe ser un número entero.',
        ];
    }

    public function openCreate(): void
    {
        $this->reset('ano', 'orden', 'editId');
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $terlec = Terlec::findOrFail($id);
        $this->editId = $id;
        $this->ano    = $terlec->ano;
        $this->orden  = $terlec->orden;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $key = 'terlec:save:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('ano', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        if (is_string($this->ano)) {
            $this->ano = trim($this->ano);
        }
        if (is_string($this->orden)) {
            $this->orden = trim($this->orden);
        }

        if ($this->editId) {
            Terlec::findOrFail($this->editId)->update([
                'ano'   => (int) $this->ano,
                'orden' => (int) $this->orden,
            ]);
            session()->flash('success', "Término lectivo {$this->ano} actualizado.");
        } else {
            Terlec::create([
                'ano'   => (int) $this->ano,
                'orden' => (int) $this->orden,
            ]);
            session()->flash('success', "Término lectivo {$this->ano} creado.");
        }

        $this->showModal = false;
        $this->reset('ano', 'orden', 'editId');
    }

    public function confirmDelete(int $id): void
    {
        $terlec = Terlec::findOrFail($id);

        $countMatricula      = DB::table('matricula')->where('idTerlec', $id)->count();
        $countCursos         = DB::table('cursos')->where('idTerlec', $id)->count();
        $countCalificaciones = DB::table('calificaciones')->where('idTerlec', $id)->count();
        $countCuotas         = DB::table('cuotas')->where('idTerlec', $id)->count();

        $total = $countMatricula + $countCursos + $countCalificaciones + $countCuotas;

        if ($total > 0) {
            $detail = collect([
                $countMatricula      ? "{$countMatricula} matrículas"      : null,
                $countCursos         ? "{$countCursos} cursos"              : null,
                $countCalificaciones ? "{$countCalificaciones} calificaciones" : null,
                $countCuotas         ? "{$countCuotas} cuotas"              : null,
            ])->filter()->implode(', ');

            $this->deleteInfo = "No se puede eliminar el año {$terlec->ano} porque tiene: {$detail}.";
            $this->deleteId   = null;
        } else {
            $this->deleteId   = $id;
            $this->deleteInfo = "¿Confirma eliminar el término lectivo {$terlec->ano}?";
        }

        $this->showConfirm = true;
    }

    public function delete(): void
    {
        $key = 'terlec:delete:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('success', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showConfirm = false;
            $this->reset('deleteId', 'deleteInfo');
            return;
        }
        RateLimiter::hit($key, 60);

        if ($this->deleteId) {
            $terlec = Terlec::findOrFail($this->deleteId);
            $ano    = $terlec->ano;
            $terlec->delete();
            session()->flash('success', "Término lectivo {$ano} eliminado.");
        }

        $this->showConfirm = false;
        $this->reset('deleteId', 'deleteInfo');
    }

    public function render()
    {
        $terlecs = Terlec::ordenado()->get();

        return view('livewire.abm.terlec.index', compact('terlecs'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Términos Lectivos']);
    }
}
