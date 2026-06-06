<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\CancelarTodasReservasService;
use App\Support\PermisosCuotas;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Cancelar todas las reservas sin pago del ciclo lectivo activo.
 */
class CancelarTodasReservas extends Component
{
    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeCancelarTodasReservas(), 403);
    }

    public function cancelar(): void
    {
        abort_unless(PermisosCuotas::puedeCancelarTodasReservas(), 403);

        $rateKey = 'cuotas:cancelar-todas-reservas:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 300);

        $resultado = CancelarTodasReservasService::cancelar();

        $this->dispatch(
            'se-swal-exito',
            mensaje: CancelarTodasReservasService::mensajeInforme($resultado),
            titulo: CancelarTodasReservasService::tituloInforme($resultado),
        );
    }

    public function render()
    {
        $ano = (int) schoolCtx()->terlecAno();
        $resumen = CancelarTodasReservasService::resumen();
        $conImporte = number_format($resumen['conImporte'], 0, ',', '.');
        $enCero = number_format($resumen['enCero'], 0, ',', '.');

        $mensajeAdvertencia = "Reservas con importe: {$conImporte}\n"
            ."Reservas en cero: {$enCero}\n\n"
            .'¿Seguro que desea cancelar las TODAS reservas de TODOS los estudiantes? Esta operación no puede revertise';

        return view('livewire.cuotas.cancelar-todas-reservas', [
            'ano' => $ano,
            'resumen' => $resumen,
            'mensajeAdvertencia' => $mensajeAdvertencia,
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Cancelar todas las reservas — {$ano}"]);
    }
}
