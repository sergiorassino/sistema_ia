<?php

namespace App\Livewire\School;

use App\Models\Profesor;
use App\Models\Terlec;
use App\Support\ProfesorMenuPortal;
use App\Support\SchoolContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ContextSwitcher extends Component
{
    public bool $open = false;

    public int|string $idTerlec = '';

    public function mount(): void
    {
        $this->idTerlec = (string) (schoolCtx()->idTerlec ?? '');
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
        $this->resetErrorBag();
    }

    public function rules(): array
    {
        return [
            'idTerlec' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('terlec', 'id'),
            ],
        ];
    }

    public function changeTerlec(): mixed
    {
        $this->validate();

        /** @var \App\Models\Profesor|null $profesor */
        $profesor = Auth::user();
        if (! $profesor) {
            return redirect()->route('login');
        }

        $ctx = schoolCtx();
        $newIdTerlec = (int) $this->idTerlec;

        if ((int) ($ctx->idTerlec ?? 0) === $newIdTerlec) {
            $this->open = false;
            return null;
        }

        // Actualizar contexto institucional en sesión (mismo usuario + mismo nivel).
        SchoolContext::set(
            idProfesor: (int) $profesor->id,
            idNivel: (int) $ctx->idNivel,
            idTerlec: $newIdTerlec,
        );

        // Persistir "último año" para todos los registros del mismo DNI (coherente con login).
        Profesor::query()
            ->where('dni', (string) $profesor->dni)
            ->update(['ult_idTerlec' => $newIdTerlec]);

        // Redirección completa: reinicia el estado de todos los módulos Livewire.
        return $this->redirectRoute(ProfesorMenuPortal::rutaInicio($profesor), navigate: false);
    }

    public function render()
    {
        $terlecs = Terlec::paraSelector();

        return view('livewire.school.context-switcher', compact('terlecs'));
    }
}

