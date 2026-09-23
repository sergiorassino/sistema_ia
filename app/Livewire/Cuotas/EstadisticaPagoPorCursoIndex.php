<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\EstadisticaPagoPorCursoDatos;
use App\Support\PermisosCuotas;
use Carbon\Carbon;
use Livewire\Component;

/**
 * Filtros para imprimir la estadística de pago por curso y cuota (PDF).
 */
class EstadisticaPagoPorCursoIndex extends Component
{
    public string $fecha = '';

    /** 0 = todos los niveles pedagógicos del alcance */
    public int $idNivel = 0;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeEstadisticaPagoPorCurso(), 403);

        $this->fecha = Carbon::today()->format('Y-m-d');
    }

    public function getPdfUrlProperty(): string
    {
        if (! $this->puedeGenerarPdf()) {
            return '#';
        }

        return route('cuotas.estadistica-pago-por-curso.pdf', [
            'fecha' => $this->fecha,
            'nivel' => $this->idNivel,
        ]);
    }

    public function puedeGenerarPdf(): bool
    {
        if ($this->fecha === '') {
            return false;
        }

        try {
            Carbon::parse($this->fecha)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        if ($this->idNivel > 0) {
            $niveles = collect(EstadisticaPagoPorCursoDatos::nivelesParaSelector())
                ->pluck('id')
                ->map(fn ($id) => (int) $id);
            if (! $niveles->contains($this->idNivel)) {
                return false;
            }
        }

        return true;
    }

    public function render()
    {
        $ano = (int) schoolCtx()->terlecAno();

        return view('livewire.cuotas.estadistica-pago-por-curso', [
            'ano' => $ano,
            'niveles' => EstadisticaPagoPorCursoDatos::nivelesParaSelector(),
            'pdfUrl' => $this->pdfUrl,
            'fechaTexto' => $this->puedeGenerarPdf()
                ? Carbon::parse($this->fecha)->format('d/m/Y')
                : '',
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Estadística de pago por curso — {$ano}"]);
    }
}
