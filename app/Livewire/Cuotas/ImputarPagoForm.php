<?php

namespace App\Livewire\Cuotas;

use App\Models\CuotaGenerada;
use App\Support\Cuotas\CuotasFormato;
use App\Support\Cuotas\FacturacionAfipImputacionPago;
use App\Support\Cuotas\GestionAranceles;
use App\Support\Cuotas\ImputacionPagoCalculo;
use App\Support\Cuotas\ImputacionPagoService;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\PermisosCuotas;
use App\Support\Security\OpaqueRouteToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Imputación manual de pago sobre una cuota generada.
 */
class ImputarPagoForm extends Component
{
    public int $idLegajo;

    public int $idCuotaGenerada;

    public int $idCuotastipopago = 1;

    public string $saldoAPagar = '';

    public string $porcent = '';

    public string $interesImporte = '';

    public string $aPagar = '';

    public string $obs = '';

    public bool $avisoPago = false;

    public bool $facturarAfip = true;

    public string $fechaPago = '';

    /** Etiqueta dinámica del campo porcent según tramo (interés o bonificación). */
    public string $etiquetaPorcent = '% INTERÉS';

    /** Etiqueta dinámica del importe calculado (interés o bonificación). */
    public string $etiquetaImporteAjuste = 'IMPORTE INTERÉS';

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $idLegajo = ContextoEstudianteSesion::legajo(ContextoEstudianteSesion::CUOTAS_GESTION);
        $idCuotaGenerada = ContextoEstudianteSesion::cuotaGenerada(ContextoEstudianteSesion::CUOTAS_GESTION);
        abort_if($idLegajo === null || $idCuotaGenerada === null, 404);

        abort_if(
            GestionAranceles::legajoParaGestion($idLegajo) === null
            || GestionAranceles::cuotaParaGestion($idCuotaGenerada, $idLegajo) === null,
            404,
        );

        $this->idLegajo = $idLegajo;
        $this->idCuotaGenerada = $idCuotaGenerada;

        $registro = $this->registro();
        abort_unless($registro !== null, 404);

        $this->fechaPago = Carbon::today()->format('Y-m-d');
        $this->saldoAPagar = CuotasFormato::importeParaInput($registro->faltapa);
        $this->avisoPago = (int) ($registro->avisoPago ?? 0) === 1;
        $this->facturarAfip = tenantCuotasFacturacionAfipHabilitada();

        if (! in_array($this->idCuotastipopago, GestionAranceles::idsMediosPagoImputacion(), true)) {
            $this->idCuotastipopago = GestionAranceles::IDS_MEDIOS_PAGO_IMPUTACION[0];
        }

        $this->sugerirPorcentDesdeCuota();
        $this->recalcular();
    }

    public function updatedSaldoAPagar(): void
    {
        $this->recalcular();
    }

    public function updatedPorcent(): void
    {
        $this->recalcular();
    }

    public function updatedFechaPago(): void
    {
        $this->recalcular();
    }

    public function recalcular(): void
    {
        $registro = $this->registro();
        if ($registro === null) {
            return;
        }

        $saldo = CuotasFormato::parseImporte($this->saldoAPagar);
        $faltapa = (float) ($registro->faltapa ?? 0);
        if ($saldo > $faltapa) {
            $saldo = $faltapa;
            $this->saldoAPagar = CuotasFormato::importeParaInput($saldo);
        }

        $fecha = $this->fechaPagoValida() ?? Carbon::today();
        $porcentRaw = trim($this->porcent);
        $porcentManual = $porcentRaw !== '' ? (float) str_replace(',', '.', $porcentRaw) : 0.0;

        $calc = ImputacionPagoCalculo::calcular($registro, $saldo, $fecha, $porcentManual);

        $this->interesImporte = CuotasFormato::importeParaInput(
            $calc['esRecargo'] ? $calc['interes'] : $calc['bonificacion'],
        );
        $this->aPagar = CuotasFormato::importeParaInput($calc['aPagar']);
        $this->etiquetaPorcent = self::etiquetaPorcentDesdeCalculo($calc);
        $this->etiquetaImporteAjuste = $calc['esRecargo']
            ? 'IMPORTE INTERÉS'
            : 'IMPORTE BONIFICACIÓN';
    }

    public function guardar(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $key = 'cuotas:imputar:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 15)) {
            session()->flash('error', 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $registro = $this->registro();
        abort_unless($registro !== null, 404);

        $validated = $this->validate([
            'idCuotastipopago' => ['required', 'integer', Rule::in(GestionAranceles::idsMediosPagoImputacion())],
            'saldoAPagar' => ['required', 'string'],
            'porcent' => ['nullable', 'numeric', 'min:0'],
            'fechaPago' => ['required', 'date'],
            'obs' => ['nullable', 'string', 'max:500'],
            'avisoPago' => ['boolean'],
            'facturarAfip' => ['boolean'],
        ]);

        $saldo = CuotasFormato::parseImporte($validated['saldoAPagar']);
        $faltapa = (float) ($registro->faltapa ?? 0);

        if ($saldo > $faltapa) {
            $this->addError('saldoAPagar', 'El importe no puede superar el saldo adeudado.');

            return;
        }

        $fecha = Carbon::parse($validated['fechaPago'])->startOfDay();
        $porcentRaw = trim((string) ($validated['porcent'] ?? ''));
        $porcentManual = $porcentRaw !== '' ? (float) $validated['porcent'] : null;
        $calc = ImputacionPagoCalculo::calcular($registro, $saldo, $fecha, $porcentManual);

        if ($saldo <= 0 && ! $validated['avisoPago']) {
            $this->addError('saldoAPagar', 'Indique un importe a abonar o active aviso de pago.');

            return;
        }

        if ($saldo > 0 && (int) $validated['idCuotastipopago'] <= 0) {
            $this->addError('idCuotastipopago', 'Seleccione el medio de pago.');

            return;
        }

        $pago = ImputacionPagoService::registrar($registro, [
            'idCuotastipopago' => (int) $validated['idCuotastipopago'],
            'saldoAPagar' => $saldo,
            'interes' => $calc['interes'],
            'bonificacion' => $calc['bonificacion'],
            'aPagar' => $calc['aPagar'],
            'fechaPago' => $fecha->format('Y-m-d'),
            'obs' => trim((string) ($validated['obs'] ?? '')),
            'avisoPago' => (bool) $validated['avisoPago'],
        ]);

        session()->flash('success', 'Pago imputado correctamente.');

        $facturarAfip = tenantCuotasFacturacionAfipHabilitada()
            && (bool) ($validated['facturarAfip'] ?? false)
            && $saldo > 0
            && $pago !== null;

        if ($facturarAfip) {
            $registro->loadMissing(['cuota', 'terlec']);
            $resultadoAfip = FacturacionAfipImputacionPago::facturar(
                $pago,
                $registro,
                $this->idLegajo,
                $saldo,
            );

            session()->flash('afip_swal_tipo', $resultadoAfip['ok'] ? 'exito' : 'error');
            session()->flash('afip_swal_mensaje', $resultadoAfip['mensaje']);
        }

        if ($pago !== null && $saldo > 0) {
            $url = route('cuotas.comprobante-imputacion', [
                'ref' => OpaqueRouteToken::forComprobantePagoImputacionAdministracion((int) $pago->id, $this->idLegajo),
            ]);
            $this->dispatch('cuotas-imputar-pago-abrir-comprobante', url: $url);
        }

        $this->redirectRoute('cuotas.estudiante', navigate: true);
    }

    /**
     * Sugiere el porcentaje según la fórmula de la cuota solo al abrir el formulario.
     */
    private function sugerirPorcentDesdeCuota(): void
    {
        $registro = $this->registro();
        if ($registro === null) {
            return;
        }

        $calc = ImputacionPagoCalculo::calcular(
            $registro,
            CuotasFormato::parseImporte($this->saldoAPagar),
            $this->fechaPagoValida() ?? Carbon::today(),
            null,
        );

        $this->porcent = self::formatearPorcent($calc['porcent']);
    }

    private function registro(): ?CuotaGenerada
    {
        return GestionAranceles::cuotaParaGestion($this->idCuotaGenerada, $this->idLegajo);
    }

    private static function formatearPorcent(float $valor): string
    {
        $s = rtrim(rtrim(number_format($valor, 4, '.', ''), '0'), '.');

        return $s === '' ? '0' : $s;
    }

    /**
     * @param  array{esRecargo: bool, usaDias: bool, diasMora: int}  $calc
     */
    private static function etiquetaPorcentDesdeCalculo(array $calc): string
    {
        if (! $calc['esRecargo']) {
            return 'PORCENTAJE BONIFICACIÓN';
        }

        if ($calc['usaDias']) {
            $dias = (int) $calc['diasMora'];
            $sufijoDias = $dias === 1 ? 'día' : 'días';

            return '% INTERÉS - '.$dias.' '.$sufijoDias.' de mora';
        }

        return '% INTERÉS';
    }

    private function fechaPagoValida(): ?Carbon
    {
        $raw = trim($this->fechaPago);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function render()
    {
        $registro = $this->registro();

        return view('livewire.cuotas.imputar-pago', [
            'registro' => $registro,
            'encabezado' => GestionAranceles::encabezadoEstudiante($this->idLegajo),
            'mediosPago' => GestionAranceles::mediosDePagoImputacion(),
            'muestraFacturarAfip' => tenantCuotasFacturacionAfipHabilitada(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Imputar pago']);
    }
}
