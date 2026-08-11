<?php

namespace App\Livewire\Cuotas;

use App\Models\PlanillaDescargaCuota;
use App\Models\RendicionRoela;
use App\Support\Cuotas\CuotasFormato;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionArchivo;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionCanal;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionConsulta;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionImpacto;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionResumen;
use App\Support\PermisosCuotas;
use App\Support\Security\OpaqueRouteToken;
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

    public bool $modalResumenAbierto = false;

    public string $modalResumenTitulo = '';

    /** @var list<string> */
    public array $modalResumenEncabezado = [];

    /** @var list<array{linea: ?int, mensaje: string}> */
    public array $modalResumenProblemas = [];

    /** @var list<array{linea: int, canal: string, idFacturaBuscado: string, estado: string, detalle: ?string}> */
    public array $modalRegistrosArchivo = [];

    public string $modalResumenContexto = '';

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

        $idTerlec = (int) schoolCtx()->idTerlec;
        $nombreSubido = (string) $this->archivoRendicion->getClientOriginalName();
        $resultado = SiroDescargaRendicionArchivo::procesar($planilla, $contenido, $idTerlec, $nombreSubido);
        $resumen = $resultado['resumen'];

        $this->archivoRendicion = null;

        $this->presentarResumenOperacion($resumen, 'Resultado de la carga del archivo', 'descarga');
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

        $this->presentarResumenOperacion($resumen, 'Resultado del impacto de pagos', 'impacto');
    }

    public function cerrarModalResumen(): void
    {
        $this->modalResumenAbierto = false;
        $this->modalResumenTitulo = '';
        $this->modalResumenEncabezado = [];
        $this->modalResumenProblemas = [];
        $this->modalRegistrosArchivo = [];
        $this->modalResumenContexto = '';
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

    /**
     * Elimina la cabecera de planilla solo si no tiene pagos en rendicionesroela.
     */
    public function borrarPlanilla(): void
    {
        abort_unless(PermisosCuotas::puedeSiroDescargaRendicion(), 403);

        $planilla = $this->planilla();
        abort_if($planilla === null, 404);

        if (RendicionRoela::query()->where('nroPlanilla', $this->nroPlanilla)->exists()) {
            $this->dispatch('se-swal-error', mensaje: 'No se puede borrar la planilla: tiene pagos descargados. Primero elimine los registros.');

            return;
        }

        if ((int) ($planilla->impactado ?? 0) === 1) {
            $this->dispatch('se-swal-error', mensaje: 'No se puede borrar una planilla ya impactada.');

            return;
        }

        $nro = (int) $planilla->nroPlanilla;
        $planilla->delete();

        session()->flash('se_swal_exito', 'Planilla Nº '.$nro.' eliminada.');
        $this->redirectRoute('cuotas.siro-descarga', navigate: true);
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

    private function presentarResumenOperacion(
        SiroDescargaRendicionResumen $resumen,
        string $titulo,
        string $contexto,
    ): void {
        if ($resumen->debeMostrarModal($contexto)) {
            $datos = $resumen->paraModal($titulo, $contexto);
            $this->modalResumenTitulo = $datos['titulo'];
            $this->modalResumenEncabezado = $datos['encabezado'];
            $this->modalResumenProblemas = $datos['problemas'];
            $this->modalRegistrosArchivo = $datos['registrosArchivo'];
            $this->modalResumenContexto = $datos['contexto'];
            $this->modalResumenAbierto = true;

            return;
        }

        $huboExito = $resumen->procesados > 0 || $resumen->impactados > 0;
        $tipo = $huboExito ? 'se-swal-exito' : 'se-swal-aviso';
        $this->dispatch($tipo, mensaje: $resumen->mensajeExitoBreve());
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

        $pdfUrl = se_route_url('cuotas.siro-descarga.pdf', [
            'ref' => OpaqueRouteToken::forSiroDescargaPlanilla($this->nroPlanilla),
        ]);

        return view('livewire.cuotas.siro-descarga-rendicion-detalle', [
            'planilla' => $planilla,
            'rendiciones' => $rendiciones,
            'totalCobrado' => $consulta->totalCobradoPlanilla($this->nroPlanilla),
            'etiquetaCanal' => $canal['label'] ?? (string) ($planilla->canalPago ?? ''),
            'hayObsEnPlanilla' => $hayObsEnPlanilla,
            'pdfUrl' => $pdfUrl,
            'fmtImporte' => fn (float $v) => CuotasFormato::formatearImporte($v),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Planilla SIRO '.$this->nroPlanilla]);
    }
}
