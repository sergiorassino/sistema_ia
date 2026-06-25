<?php

namespace App\Livewire\Cuotas;

use App\Models\PlanillaDescargaCuota;
use App\Models\RendicionRoela;
use App\Support\Cuotas\CuotasFormato;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionArchivo;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionCanal;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionConsulta;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionImpacto;
use App\Support\PermisosCuotas;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Detalle de planilla: pagos descargados, carga de archivo e impacto masivo.
 */
class SiroDescargaRendicionDetalle extends Component
{
    use WithFileUploads;

    public int $nroPlanilla;

    public $archivoRendicion;

    public function mount(int $nroPlanilla): void
    {
        abort_unless(PermisosCuotas::puedeSiroDescargaRendicion(), 403);

        $planilla = (new SiroDescargaRendicionConsulta)->planillaPorNro($nroPlanilla);
        abort_if($planilla === null, 404);

        $this->nroPlanilla = $nroPlanilla;
    }

    public function procesarArchivo(): void
    {
        abort_unless(PermisosCuotas::puedeSiroDescargaRendicion(), 403);

        $planilla = $this->planilla();
        abort_if($planilla === null, 404);

        if ((int) ($planilla->impactado ?? 0) === 1) {
            $this->dispatch('se-swal-error', mensaje: 'La planilla ya fue impactada. No puede volver a cargar el archivo.');

            return;
        }

        $this->validate([
            'archivoRendicion' => ['required', 'file', 'max:10240'],
        ], [
            'archivoRendicion.required' => 'Seleccione el archivo de rendición SIRO.',
        ]);

        $key = 'siro-descarga-archivo:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiadas cargas. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 120);

        $contenido = file_get_contents($this->archivoRendicion->getRealPath());
        if ($contenido === false || trim($contenido) === '') {
            $this->dispatch('se-swal-error', mensaje: 'El archivo está vacío o no se pudo leer.');

            return;
        }

        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            $contenido = substr($contenido, 3);
        }

        $nombreSubido = trim((string) $this->archivoRendicion->getClientOriginalName());
        if ($nombreSubido !== '') {
            $planilla->nombreArchivo = mb_substr($nombreSubido, 0, 50);
            $planilla->save();
        }

        $idTerlec = (int) schoolCtx()->idTerlec;
        $resultado = SiroDescargaRendicionArchivo::procesar($planilla, $contenido, $idTerlec);
        $resumen = $resultado['resumen'];

        $this->archivoRendicion = null;

        $tipo = $resumen->procesados > 0 ? 'se-swal-exito' : 'se-swal-aviso';
        $this->dispatch($tipo, mensaje: $resumen->mensajeSwal());
    }

    public function impactarTodos(): void
    {
        abort_unless(PermisosCuotas::puedeSiroDescargaRendicion(), 403);

        $planilla = $this->planilla();
        abort_if($planilla === null, 404);

        $key = 'siro-descarga-impacto:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos de impacto. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 120);

        $resumen = SiroDescargaRendicionImpacto::impactarPlanilla($planilla, (int) schoolCtx()->idTerlec);

        $tipo = $resumen->impactados > 0 ? 'se-swal-exito' : 'se-swal-aviso';
        $this->dispatch($tipo, mensaje: $resumen->mensajeSwal());
    }

    public function borrarTodos(): void
    {
        abort_unless(PermisosCuotas::puedeSiroDescargaRendicion(), 403);

        $planilla = $this->planilla();
        abort_if($planilla === null, 404);

        if ((int) ($planilla->impactado ?? 0) === 1) {
            $this->dispatch('se-swal-error', mensaje: 'No puede borrar pagos de una planilla ya impactada.');

            return;
        }

        RendicionRoela::query()->where('nroPlanilla', $this->nroPlanilla)->delete();
        $planilla->impactado = 0;
        $planilla->desde = null;
        $planilla->hasta = null;
        $planilla->save();

        $this->dispatch('se-swal-exito', mensaje: 'Se eliminaron todos los pagos descargados de la planilla.');
    }

    public function borrarRendicion(int $id): void
    {
        abort_unless(PermisosCuotas::puedeSiroDescargaRendicion(), 403);

        $planilla = $this->planilla();
        abort_if($planilla === null, 404);

        if ((int) ($planilla->impactado ?? 0) === 1) {
            $this->dispatch('se-swal-error', mensaje: 'No puede borrar pagos de una planilla ya impactada.');

            return;
        }

        $rendicion = RendicionRoela::query()
            ->where('id', $id)
            ->where('nroPlanilla', $this->nroPlanilla)
            ->first();

        if ($rendicion === null) {
            return;
        }

        $rendicion->delete();
        $this->dispatch('se-swal-exito', mensaje: 'Pago eliminado de la planilla.');
    }

    private function planilla(): ?PlanillaDescargaCuota
    {
        return (new SiroDescargaRendicionConsulta)->planillaPorNro($this->nroPlanilla);
    }

    public function render()
    {
        $consulta = new SiroDescargaRendicionConsulta;
        $planilla = $consulta->planillaPorNro($this->nroPlanilla);
        abort_if($planilla === null, 404);

        $rendiciones = $consulta->rendicionesDePlanilla($this->nroPlanilla);
        $canal = collect(SiroDescargaRendicionCanal::opcionesPlanilla())
            ->firstWhere('id', (int) ($planilla->canalPago ?? 0));

        $hayObsEnPlanilla = $rendiciones->contains(
            fn (RendicionRoela $r): bool => trim((string) ($r->obs ?? '')) !== ''
        );

        return view('livewire.cuotas.siro-descarga-rendicion-detalle', [
            'planilla' => $planilla,
            'rendiciones' => $rendiciones,
            'totalCobrado' => $consulta->totalCobradoPlanilla($this->nroPlanilla),
            'etiquetaCanal' => $canal['label'] ?? (string) ($planilla->canalPago ?? ''),
            'hayObsEnPlanilla' => $hayObsEnPlanilla,
            'fmtImporte' => fn (float $v) => CuotasFormato::formatearImporte($v),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Planilla SIRO '.$this->nroPlanilla]);
    }
}
