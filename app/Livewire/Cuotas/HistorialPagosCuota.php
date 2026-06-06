<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\GestionAranceles;
use App\Support\Cuotas\HistorialPagosCuotaService;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\PermisosCuotas;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Historial de pagos de una cuota generada (tabla cuotaspagos).
 */
class HistorialPagosCuota extends Component
{
    public int $idLegajo;

    public int $idCuotaGenerada;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $idLegajo = ContextoEstudianteSesion::legajo(ContextoEstudianteSesion::CUOTAS_GESTION);
        $idCuotaGenerada = ContextoEstudianteSesion::cuotaGenerada(ContextoEstudianteSesion::CUOTAS_GESTION);
        abort_if(
            $idLegajo === null
            || $idCuotaGenerada === null
            || GestionAranceles::legajoParaGestion($idLegajo) === null
            || GestionAranceles::cuotaParaGestion($idCuotaGenerada, $idLegajo) === null,
            404,
        );

        $this->idLegajo = $idLegajo;
        $this->idCuotaGenerada = $idCuotaGenerada;
    }

    public function borrarPago(int $idCuotaPago): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $key = 'cuotas:historial-pagos:borrar:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        if (! HistorialPagosCuotaService::eliminar($idCuotaPago, $this->idLegajo, $this->idCuotaGenerada)) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo eliminar el pago.');

            return;
        }

        $this->dispatch('se-swal-exito', mensaje: 'Pago eliminado correctamente.');
    }

    public function render()
    {
        $registro = GestionAranceles::cuotaParaGestion($this->idCuotaGenerada, $this->idLegajo);

        return view('livewire.cuotas.historial-pagos-cuota', [
            'registro' => $registro,
            'encabezado' => GestionAranceles::encabezadoEstudiante($this->idLegajo),
            'pagos' => HistorialPagosCuotaService::pagosTodos(
                $this->idCuotaGenerada,
                $this->idLegajo,
            ),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Historial de pagos']);
    }
}
