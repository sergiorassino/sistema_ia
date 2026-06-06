<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\ListadoPagosPorFechaDatos;
use App\Support\PermisosCuotas;
use Carbon\Carbon;
use Livewire\Component;

/**
 * Filtros para imprimir el listado de pagos por fecha (PDF).
 */
class ListadoPagosPorFechaIndex extends Component
{
    public string $fechaDesde = '';

    public string $fechaHasta = '';

    /** 0 = todos los medios de pago */
    public int $idMedioPago = 0;

    /** 0 = todas las cuotas del ciclo */
    public int $idCuota = 0;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeListadoPagosPorFecha(), 403);

        $hoy = Carbon::today();
        $this->fechaDesde = $hoy->copy()->startOfMonth()->format('Y-m-d');
        $this->fechaHasta = $hoy->format('Y-m-d');
    }

    public function getPdfUrlProperty(): string
    {
        if (! $this->puedeGenerarPdf()) {
            return '#';
        }

        return route('cuotas.listado-pagos-por-fecha.pdf', [
            'fecha_desde' => $this->fechaDesde,
            'fecha_hasta' => $this->fechaHasta,
            'medio' => $this->idMedioPago,
            'cuota' => $this->idCuota,
        ]);
    }

    public function puedeGenerarPdf(): bool
    {
        if ($this->fechaDesde === '' || $this->fechaHasta === '') {
            return false;
        }

        try {
            $desde = Carbon::parse($this->fechaDesde)->startOfDay();
            $hasta = Carbon::parse($this->fechaHasta)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        if ($hasta->lt($desde)) {
            return false;
        }

        if ($this->idMedioPago > 0) {
            $medios = ListadoPagosPorFechaDatos::mediosDePagoParaSelector()->pluck('id')->map(fn ($id) => (int) $id);
            if (! $medios->contains($this->idMedioPago)) {
                return false;
            }
        }

        if ($this->idCuota > 0) {
            $cuotas = ListadoPagosPorFechaDatos::cuotasDelCicloParaSelector()->pluck('id')->map(fn ($id) => (int) $id);
            if (! $cuotas->contains($this->idCuota)) {
                return false;
            }
        }

        return true;
    }

    public function render()
    {
        $ano = (int) schoolCtx()->terlecAno();

        return view('livewire.cuotas.listado-pagos-por-fecha', [
            'ano' => $ano,
            'mediosDePago' => ListadoPagosPorFechaDatos::mediosDePagoParaSelector(),
            'cuotas' => ListadoPagosPorFechaDatos::cuotasDelCicloParaSelector(),
            'pdfUrl' => $this->pdfUrl,
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Listado de pagos por fecha — {$ano}"]);
    }
}
