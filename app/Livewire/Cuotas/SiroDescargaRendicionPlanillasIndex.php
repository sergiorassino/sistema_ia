<?php

namespace App\Livewire\Cuotas;

use App\Models\PlanillaDescargaCuota;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionCanal;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionConsulta;
use App\Support\PermisosCuotas;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado y alta de planillas de descarga de rendición SIRO.
 */
class SiroDescargaRendicionPlanillasIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $modalAbierto = false;

    public int $nroPlanilla = 0;

    public string $fecha = '';

    public int $canalPago = 0;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeSiroDescargaRendicion(), 403);
        $this->canalPago = SiroDescargaRendicionCanal::canalPlanillaPorDefecto();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function abrirModalAlta(): void
    {
        abort_unless(PermisosCuotas::puedeSiroDescargaRendicion(), 403);

        $consulta = new SiroDescargaRendicionConsulta;
        $this->nroPlanilla = $consulta->sugerirNroPlanilla();
        $this->fecha = now()->format('Y-m-d');
        $this->canalPago = SiroDescargaRendicionCanal::canalPlanillaPorDefecto();
        $this->resetValidation();
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetValidation();
    }

    public function guardarPlanilla(): void
    {
        abort_unless(PermisosCuotas::puedeSiroDescargaRendicion(), 403);

        $idsCanal = collect(SiroDescargaRendicionCanal::opcionesPlanillaParaAlta())->pluck('id')->map(fn ($id) => (int) $id)->all();

        $validated = $this->validate([
            'nroPlanilla' => ['required', 'integer', 'min:1', Rule::unique('planillasdescargacuotas', 'nroPlanilla')],
            'fecha' => ['required', 'date'],
            'canalPago' => ['required', 'integer', Rule::in($idsCanal)],
        ], [
            'nroPlanilla.unique' => 'Ya existe una planilla con ese número.',
        ]);

        $key = 'siro-descarga-planilla:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $planilla = PlanillaDescargaCuota::query()->create([
            'nroPlanilla' => (int) $validated['nroPlanilla'],
            'fecha' => $validated['fecha'],
            'desde' => null,
            'hasta' => null,
            'canalPago' => (int) $validated['canalPago'],
            'nombreArchivo' => '',
            'impactado' => 0,
        ]);

        $this->modalAbierto = false;
        $this->dispatch('se-swal-exito', mensaje: 'Planilla Nº '.$planilla->nroPlanilla.' creada. Cargue el archivo de rendición para registrar los pagos.');
        $this->redirectRoute('cuotas.siro-descarga.detalle', ['nroPlanilla' => $planilla->nroPlanilla], navigate: true);
    }

    public function render()
    {
        $consulta = new SiroDescargaRendicionConsulta;

        return view('livewire.cuotas.siro-descarga-rendicion-planillas', [
            'planillas' => $consulta->listarPlanillas($this->search),
            'ultimoNro' => $consulta->ultimoNroPlanilla(),
            'canalesPago' => SiroDescargaRendicionCanal::opcionesPlanilla(),
            'canalesPagoAlta' => SiroDescargaRendicionCanal::opcionesPlanillaParaAlta(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Descarga rendición SIRO']);
    }
}
