<?php

namespace App\Livewire\Examenes;

use App\Livewire\Examenes\Concerns\RequiresPermisoExamenes;
use App\Support\Examenes\BorrarInscripcionesExamen;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class BorrarInscripcionesExamenIndex extends Component
{
    use RequiresPermisoExamenes;

    public bool $showConfirm = false;

    public int $pendientes = 0;

    public ?int $ultimasAfectadas = null;

    public function mount(): void
    {
        $this->refrescarConteo();
    }

    public function refrescarConteo(): void
    {
        $this->pendientes = BorrarInscripcionesExamen::contarInscriptos();
    }

    public function abrirConfirmacion(): void
    {
        $this->refrescarConteo();
        $this->ultimasAfectadas = null;
        $this->showConfirm = true;
    }

    public function cerrarConfirmacion(): void
    {
        $this->showConfirm = false;
    }

    public function ejecutarBorrado(): void
    {
        $key = 'examenes:borrar-inscripciones:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('borrado', 'Demasiados intentos. Espere un minuto e intente de nuevo.');

            return;
        }
        RateLimiter::hit($key, 60);

        $afectadas = BorrarInscripcionesExamen::ejecutar();
        $this->ultimasAfectadas = $afectadas;
        $this->showConfirm = false;
        $this->refrescarConteo();

        session()->flash(
            'success',
            $afectadas > 0
                ? "Se anularon {$afectadas} inscripciones a examen (inscri pasó de 1 a 0)."
                : 'No había registros con inscri = 1 en calificaciones.'
        );
    }

    public function render()
    {
        return view('livewire.examenes.borrar-inscripciones-examen');
    }
}
