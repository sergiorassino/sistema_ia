<?php

namespace App\Livewire\Cooperadora;

use App\Models\CoopIngreso;
use App\Support\Cooperadora\EnvioReciboCooperadora;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\Cooperadora\ReciboIngresosGrupo;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

class IngresosIndex extends Component
{
    use WithPagination;

    public string $fechaDesde = '';

    public string $fechaHasta = '';

    public function mount(): void
    {
        abort_unless(PermisosCooperadora::puedeIngresos(), 403);
        $this->fechaDesde = now()->startOfMonth()->format('Y-m-d');
        $this->fechaHasta = now()->format('Y-m-d');
    }

    public function updatedFechaDesde(): void
    {
        $this->resetPage();
    }

    public function updatedFechaHasta(): void
    {
        $this->resetPage();
    }

    public function reenviarReciboEmail(int $idIngreso): void
    {
        abort_unless(PermisosCooperadora::puedeIngresos(), 403);

        $key = 'coop:recibo-email:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente de nuevo.');

            return;
        }
        RateLimiter::hit($key, 60);

        $ingreso = CoopIngreso::query()
            ->where('anulado', false)
            ->find($idIngreso);

        if ($ingreso === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró el ingreso.');

            return;
        }

        if (trim((string) ($ingreso->pagador_email ?? '')) === '') {
            $this->dispatch('se-swal-aviso', mensaje: 'Este recibo no tiene email del pagador. Actualice los datos en un nuevo ingreso o desde el legajo del alumno.');

            return;
        }

        $idRef = ReciboIngresosGrupo::idReferenciaPdf($ingreso);
        $ok = EnvioReciboCooperadora::enviar($idRef, reenvio: true);

        if ($ok) {
            $msg = EnvioReciboCooperadora::RECIBO_EMAIL_SIMULADO
                ? 'Reenvío registrado (modo simulado: no se envió correo real).'
                : 'Recibo enviado al pagador.';
            $this->dispatch('se-swal-exito', mensaje: $msg);
        } else {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo registrar el envío del recibo.');
        }
    }

    public function render()
    {
        $query = \App\Models\CoopIngreso::query()
            ->with(['rubro:id,nombre', 'item:id,nombre', 'legajo:id,apellido,nombre'])
            ->where('anulado', false)
            ->orderByDesc('fecha')
            ->orderByDesc('recibo_numero');

        if ($this->fechaDesde !== '') {
            $query->where('fecha', '>=', $this->fechaDesde);
        }
        if ($this->fechaHasta !== '') {
            $query->where('fecha', '<=', $this->fechaHasta);
        }

        return view('livewire.cooperadora.ingresos-index', [
            'ingresos' => $query->paginate(25),
        ])->layout(ProfesorMenuPortal::layoutStaff(), ['pageTitle' => 'Cooperadora — Ingresos']);
    }
}
