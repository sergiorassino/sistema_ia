<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\GestionAranceles;
use App\Support\Cuotas\HistorialPagosCuotaService;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\PermisosCuotas;
use Carbon\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Historial de pagos de una cuota generada (tabla cuotaspagos).
 */
class HistorialPagosCuota extends Component
{
    public int $idLegajo;

    public int $idCuotaGenerada;

    public bool $modalFechaPagoAbierto = false;

    public ?int $idCuotaPagoFecha = null;

    public string $fechaPagoEdit = '';

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $idLegajo = ContextoEstudianteSesion::legajo(ContextoEstudianteSesion::CUOTAS_GESTION);
        $idCuotaGenerada = ContextoEstudianteSesion::cuotaGenerada(ContextoEstudianteSesion::CUOTAS_GESTION);
        abort_if(
            $idLegajo === null
            || $idCuotaGenerada === null
            || GestionAranceles::legajoParaGestion($idLegajo) === null
            || GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo) === null,
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

        $validacion = HistorialPagosCuotaService::puedeEliminar(
            $idCuotaPago,
            $this->idLegajo,
            $this->idCuotaGenerada,
        );
        if (! $validacion['ok']) {
            $this->dispatch('se-swal-error', mensaje: $validacion['mensaje']);

            return;
        }

        if (! HistorialPagosCuotaService::eliminar($idCuotaPago, $this->idLegajo, $this->idCuotaGenerada)) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo eliminar el pago.');

            return;
        }

        $this->dispatch('se-swal-exito', mensaje: 'Pago eliminado correctamente.');
    }

    public function abrirModalFechaPago(int $idCuotaPago): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);
        abort_unless(tenantCuotasFacturacionAfipModo() === 'pago', 404);

        $pago = HistorialPagosCuotaService::pagoDelHistorial(
            $idCuotaPago,
            $this->idLegajo,
            $this->idCuotaGenerada,
        );
        if ($pago === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró el pago.');

            return;
        }

        $fechaRaw = trim((string) ($pago->fechhora ?? ''));
        try {
            $fecha = $fechaRaw !== '' ? Carbon::parse($fechaRaw) : Carbon::today();
        } catch (\Throwable) {
            $fecha = Carbon::today();
        }

        $this->idCuotaPagoFecha = $idCuotaPago;
        $this->fechaPagoEdit = $fecha->format('Y-m-d');
        $this->modalFechaPagoAbierto = true;
        $this->resetValidation();
    }

    public function cerrarModalFechaPago(): void
    {
        $this->modalFechaPagoAbierto = false;
        $this->idCuotaPagoFecha = null;
        $this->fechaPagoEdit = '';
        $this->resetValidation();
    }

    public function guardarFechaPago(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);
        abort_unless(tenantCuotasFacturacionAfipModo() === 'pago', 404);

        $idCuotaPago = (int) ($this->idCuotaPagoFecha ?? 0);
        if ($idCuotaPago <= 0) {
            $this->dispatch('se-swal-error', mensaje: 'No hay pago seleccionado.');

            return;
        }

        $this->validate([
            'fechaPagoEdit' => ['required', 'date'],
        ]);

        $key = 'cuotas:historial-pagos:fecha:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        try {
            $fecha = Carbon::parse($this->fechaPagoEdit)->startOfDay();
        } catch (\Throwable) {
            $this->addError('fechaPagoEdit', 'Fecha inválida.');

            return;
        }

        if (! HistorialPagosCuotaService::actualizarFechaPago(
            $idCuotaPago,
            $this->idLegajo,
            $this->idCuotaGenerada,
            $fecha,
        )) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo actualizar la fecha del pago.');

            return;
        }

        $this->cerrarModalFechaPago();
        $this->dispatch('se-swal-exito', mensaje: 'Fecha de pago actualizada correctamente.');
    }

    public function render()
    {
        $registro = GestionAranceles::cuotaDelLegajo($this->idCuotaGenerada, $this->idLegajo);

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
