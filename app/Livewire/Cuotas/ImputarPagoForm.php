<?php

namespace App\Livewire\Cuotas;

use App\Models\CuotaGenerada;
use App\Models\CuotaPago;
use App\Support\Cuotas\ComprobantesAfipCuotaService;
use App\Support\Cuotas\CuotasFormato;
use App\Support\Cuotas\FacturacionAfipImputacionPago;
use App\Support\Cuotas\GestionAranceles;
use App\Support\Cuotas\ImputacionPagoCalculo;
use App\Support\Cuotas\ImputacionPagoService;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\PermisosCuotas;
use App\Support\Security\OpaqueRouteToken;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Imputación manual de pago sobre una o varias cuotas generadas.
 */
class ImputarPagoForm extends Component
{
    public int $idLegajo;

    /** @var list<int> */
    public array $idsCuotasGeneradas = [];

    public int $idCuotaGenerada = 0;

    public int $idCuotastipopago = 1;

    public string $saldoAPagar = '';

    public string $porcent = '';

    public string $interesImporte = '';

    public string $aPagar = '';

    public string $obs = '';

    public bool $avisoPago = false;

    /** `afip` = comprobante electrónico AFIP; `interno` = recibo interno del sistema. */
    public string $tipoComprobanteImputacion = 'interno';

    public string $fechaPago = '';

    /** Etiqueta dinámica del campo porcent según tramo (interés o bonificación). */
    public string $etiquetaPorcent = '% INTERÉS';

    /** Etiqueta dinámica del importe calculado (interés o bonificación). */
    public string $etiquetaImporteAjuste = 'IMPORTE INTERÉS';

    /**
     * Saldo y porcentaje editables por cuota (cobro múltiple).
     *
     * @var array<int, array{saldo: string, porcent: string}>
     */
    public array $lineasImputacion = [];

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $idLegajo = ContextoEstudianteSesion::legajo(ContextoEstudianteSesion::CUOTAS_GESTION);
        $idsCuotas = ContextoEstudianteSesion::cuotasGeneradasParaImputar(ContextoEstudianteSesion::CUOTAS_GESTION);
        abort_if($idLegajo === null || $idsCuotas === [], 404);

        $registros = GestionAranceles::cuotasParaImputacion($idsCuotas, $idLegajo);
        abort_if($registros->count() !== count($idsCuotas), 404);

        $this->idLegajo = $idLegajo;
        $this->idsCuotasGeneradas = $registros->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->idCuotaGenerada = (int) ($this->idsCuotasGeneradas[0] ?? 0);

        $this->fechaPago = ImputacionPagoService::ahoraParaInput();
        $this->tipoComprobanteImputacion = tenantCuotasFacturacionAfipMuestraEnImputacionPago() ? 'afip' : 'interno';

        if (! in_array($this->idCuotastipopago, GestionAranceles::idsMediosPagoImputacion(), true)) {
            $this->idCuotastipopago = GestionAranceles::IDS_MEDIOS_PAGO_IMPUTACION[0];
        }

        if ($this->esUnaCuota()) {
            $registro = $registros->first();
            abort_unless($registro instanceof CuotaGenerada, 404);

            $this->saldoAPagar = CuotasFormato::importeParaInput($registro->faltapa);
            $this->avisoPago = (int) ($registro->avisoPago ?? 0) === 1;
            $this->sugerirPorcentDesdeCuota();
            $this->recalcular();
        } else {
            $this->inicializarLineasImputacion($registros);
        }
    }

    public function updatedSaldoAPagar(): void
    {
        if ($this->esUnaCuota()) {
            $this->recalcular();
        }
    }

    public function updatedPorcent(): void
    {
        if ($this->esUnaCuota()) {
            $this->recalcular();
        }
    }

    public function updatedFechaPago(): void
    {
        if ($this->esUnaCuota()) {
            $this->recalcular();
        }
    }

    public function updatedLineasImputacion(mixed $value, string $key): void
    {
        if ($this->esUnaCuota()) {
            return;
        }

        $this->normalizarLineaImputacion($key);
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

        $fecha = $this->fechaPagoValida() ?? Carbon::now(ImputacionPagoService::TIMEZONE_PAGO)->startOfDay();
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

        if ($this->esUnaCuota()) {
            $this->guardarUnaCuota();

            return;
        }

        $this->guardarVariasCuotas();
    }

    private function guardarUnaCuota(): void
    {
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
            'tipoComprobanteImputacion' => ['required', Rule::in(['afip', 'interno'])],
        ]);

        $avisoPago = tenantCuotasSiroHabilitado() && (bool) ($validated['avisoPago'] ?? false);

        $saldo = CuotasFormato::parseImporte($validated['saldoAPagar']);
        $faltapa = (float) ($registro->faltapa ?? 0);

        if ($saldo > $faltapa) {
            $this->addError('saldoAPagar', 'El importe no puede superar el saldo adeudado.');

            return;
        }

        $fechaHora = ImputacionPagoService::fechaHoraPago((string) $validated['fechaPago']);
        $porcentRaw = trim((string) ($validated['porcent'] ?? ''));
        $porcentManual = $porcentRaw !== '' ? (float) $validated['porcent'] : null;
        $calc = ImputacionPagoCalculo::calcular($registro, $saldo, $fechaHora, $porcentManual);

        if ($saldo <= 0 && ! $avisoPago) {
            $this->addError('saldoAPagar', 'Indique un importe a abonar'.(tenantCuotasSiroHabilitado() ? ' o active aviso de pago.' : '.'));

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
            'fechaPago' => $fechaHora->format('Y-m-d H:i:s'),
            'obs' => trim((string) ($validated['obs'] ?? '')),
            'avisoPago' => $avisoPago,
        ]);

        $this->finalizarGuardado(
            collect($pago !== null ? [$pago] : []),
            (string) ($validated['tipoComprobanteImputacion'] ?? 'interno'),
            $saldo > 0,
        );
    }

    private function guardarVariasCuotas(): void
    {
        $key = 'cuotas:imputar:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 15)) {
            session()->flash('error', 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $validated = $this->validate([
            'idCuotastipopago' => ['required', 'integer', Rule::in(GestionAranceles::idsMediosPagoImputacion())],
            'fechaPago' => ['required', 'date'],
            'obs' => ['nullable', 'string', 'max:500'],
            'tipoComprobanteImputacion' => ['required', Rule::in(['afip', 'interno'])],
        ]);

        $registros = $this->registros();
        if ($registros->isEmpty()) {
            abort(404);
        }

        $fechaHora = ImputacionPagoService::fechaHoraPago((string) $validated['fechaPago']);
        $obs = trim((string) ($validated['obs'] ?? ''));
        $items = [];

        foreach ($registros as $registro) {
            $id = (int) $registro->id;
            $faltapa = round((float) ($registro->faltapa ?? 0), 2);
            if ($faltapa <= 0) {
                $this->addError('fechaPago', 'Alguna cuota seleccionada ya no tiene saldo adeudado.');

                return;
            }

            $linea = $this->lineasImputacion[$id] ?? null;
            $saldo = CuotasFormato::parseImporte(is_array($linea) ? ($linea['saldo'] ?? '') : '');
            if ($saldo > $faltapa) {
                $this->addError('lineasImputacion.'.$id.'.saldo', 'El importe no puede superar el saldo adeudado de la cuota.');

                return;
            }
            if ($saldo <= 0) {
                $this->addError('lineasImputacion.'.$id.'.saldo', 'Indique un importe a abonar en cada cuota.');

                return;
            }

            $porcentRaw = trim(is_array($linea) ? (string) ($linea['porcent'] ?? '') : '');
            $porcentManual = $porcentRaw !== '' ? (float) str_replace(',', '.', $porcentRaw) : null;
            $calc = ImputacionPagoCalculo::calcular($registro, $saldo, $fechaHora, $porcentManual);
            $items[] = [
                'registro' => $registro,
                'datos' => [
                    'idCuotastipopago' => (int) $validated['idCuotastipopago'],
                    'saldoAPagar' => $saldo,
                    'interes' => $calc['interes'],
                    'bonificacion' => $calc['bonificacion'],
                    'aPagar' => $calc['aPagar'],
                    'fechaPago' => $fechaHora->format('Y-m-d H:i:s'),
                    'obs' => $obs,
                    'avisoPago' => false,
                ],
            ];
        }

        $pagos = ImputacionPagoService::registrarLote($items);

        $this->finalizarGuardado(
            collect($pagos),
            (string) ($validated['tipoComprobanteImputacion'] ?? 'interno'),
            true,
        );
    }

    /**
     * @param  Collection<int, CuotaPago>  $pagos
     */
    private function finalizarGuardado(Collection $pagos, string $tipoComprobante, bool $abrirComprobante): void
    {
        session()->flash('success', $pagos->count() > 1
            ? 'Se imputaron '.$pagos->count().' cuotas correctamente.'
            : 'Pago imputado correctamente.');

        if ($tipoComprobante === 'afip' && ! tenantCuotasFacturacionAfipMuestraEnImputacionPago()) {
            $tipoComprobante = 'interno';
        }

        $facturarAfip = $tipoComprobante === 'afip' && $abrirComprobante && $pagos->isNotEmpty();

        if ($facturarAfip) {
            $itemsAfip = [];
            foreach ($pagos as $pago) {
                $registro = GestionAranceles::cuotaDelLegajo((int) $pago->idCuotasGeneradas, $this->idLegajo);
                if ($registro === null) {
                    continue;
                }
                $registro->loadMissing(['cuota', 'terlec']);
                $itemsAfip[] = [
                    'pago' => $pago,
                    'registro' => $registro,
                    'importe' => ComprobantesAfipCuotaService::importeFacturable($pago),
                ];
            }

            $resultadoAfip = FacturacionAfipImputacionPago::facturarLote($itemsAfip, $this->idLegajo);

            session()->flash('afip_swal_tipo', $resultadoAfip['ok'] ? 'exito' : 'error');
            session()->flash('afip_swal_mensaje', $resultadoAfip['mensaje']);

            if ($resultadoAfip['ok'] && ! empty($resultadoAfip['idComprobanteAfip'])) {
                $url = se_route_url('cuotas.comprobante-afip', [
                    'ref' => OpaqueRouteToken::forComprobanteAfipRegistro((int) $resultadoAfip['idComprobanteAfip'], $this->idLegajo),
                ]);
                $this->dispatch('cuotas-imputar-pago-abrir-comprobante', url: $url);
            }
        } elseif ($pagos->isNotEmpty() && $abrirComprobante) {
            $url = $pagos->count() === 1
                ? se_route_url('cuotas.comprobante-imputacion', [
                    'ref' => OpaqueRouteToken::forComprobantePagoImputacionAdministracion((int) $pagos->first()->id, $this->idLegajo),
                ])
                : se_route_url('cuotas.comprobante-imputacion', [
                    'ref' => OpaqueRouteToken::forComprobantePagoImputacionMultipleAdministracion(
                        $pagos->pluck('id')->map(fn ($id) => (int) $id)->all(),
                        $this->idLegajo,
                    ),
                ]);
            $this->dispatch('cuotas-imputar-pago-abrir-comprobante', url: $url);
        }

        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::CUOTAS_GESTION, [
            'idLegajos' => $this->idLegajo,
            'idsCuotasGeneradas' => [],
            'idCuotaGenerada' => 0,
        ]);

        $this->redirectRoute('cuotas.estudiante', navigate: true);
    }

    /**
     * Sugiere el porcentaje según la fórmula de la cuota solo al abrir el formulario.
     */
    /**
     * @param  Collection<int, CuotaGenerada>  $registros
     */
    private function inicializarLineasImputacion(Collection $registros): void
    {
        $fecha = $this->fechaPagoValida() ?? Carbon::now(ImputacionPagoService::TIMEZONE_PAGO)->startOfDay();
        $lineas = [];

        foreach ($registros as $registro) {
            $id = (int) $registro->id;
            $saldo = round((float) ($registro->faltapa ?? 0), 2);
            $calc = ImputacionPagoCalculo::calcular($registro, $saldo, $fecha, null);
            $lineas[$id] = [
                'saldo' => CuotasFormato::importeParaInput($saldo),
                'porcent' => self::formatearPorcent($calc['porcent']),
            ];
        }

        $this->lineasImputacion = $lineas;
    }

    private function normalizarLineaImputacion(string $key): void
    {
        if (! preg_match('/^(\d+)\.(saldo|porcent)$/', $key, $coincidencias)) {
            return;
        }

        $id = (int) $coincidencias[1];
        $campo = $coincidencias[2];
        if (! isset($this->lineasImputacion[$id])) {
            return;
        }

        if ($campo === 'saldo') {
            $registro = GestionAranceles::cuotaParaGestion($id, $this->idLegajo);
            if ($registro === null) {
                return;
            }

            $saldo = CuotasFormato::parseImporte($this->lineasImputacion[$id]['saldo'] ?? '');
            $faltapa = (float) ($registro->faltapa ?? 0);
            if ($saldo > $faltapa) {
                $this->lineasImputacion[$id]['saldo'] = CuotasFormato::importeParaInput($faltapa);
            }
        }
    }

    private function sugerirPorcentDesdeCuota(): void
    {
        $registro = $this->registro();
        if ($registro === null) {
            return;
        }

        $calc = ImputacionPagoCalculo::calcular(
            $registro,
            CuotasFormato::parseImporte($this->saldoAPagar),
            $this->fechaPagoValida() ?? Carbon::now(ImputacionPagoService::TIMEZONE_PAGO)->startOfDay(),
            null,
        );

        $this->porcent = self::formatearPorcent($calc['porcent']);
    }

    private function esUnaCuota(): bool
    {
        return count($this->idsCuotasGeneradas) === 1;
    }

    private function registro(): ?CuotaGenerada
    {
        if (! $this->esUnaCuota()) {
            return null;
        }

        return GestionAranceles::cuotaParaGestion($this->idCuotaGenerada, $this->idLegajo);
    }

    /**
     * @return Collection<int, CuotaGenerada>
     */
    private function registros(): Collection
    {
        return GestionAranceles::cuotasParaImputacion($this->idsCuotasGeneradas, $this->idLegajo);
    }

    /**
     * @return array{
     *     lineas: list<array<string, mixed>>,
     *     neto: float,
     *     interes: float,
     *     bonificacion: float,
     *     total: float
     * }
     */
    private function resumenMultiples(): array
    {
        $fecha = $this->fechaPagoValida() ?? Carbon::now(ImputacionPagoService::TIMEZONE_PAGO)->startOfDay();
        $lineas = [];
        $neto = 0.0;
        $interes = 0.0;
        $bonificacion = 0.0;
        $total = 0.0;

        foreach ($this->registros() as $registro) {
            $id = (int) $registro->id;
            $faltapa = round((float) ($registro->faltapa ?? 0), 2);
            $linea = $this->lineasImputacion[$id] ?? ['saldo' => CuotasFormato::importeParaInput($faltapa), 'porcent' => '0'];
            $saldo = CuotasFormato::parseImporte($linea['saldo'] ?? '');
            if ($saldo > $faltapa) {
                $saldo = $faltapa;
            }
            $porcentRaw = trim((string) ($linea['porcent'] ?? ''));
            $porcentManual = $porcentRaw !== '' ? (float) str_replace(',', '.', $porcentRaw) : null;
            $calc = ImputacionPagoCalculo::calcular($registro, $saldo, $fecha, $porcentManual);
            $neto += $saldo;
            $interes += (float) $calc['interes'];
            $bonificacion += (float) $calc['bonificacion'];
            $total += (float) $calc['aPagar'];

            $lineas[] = [
                'id' => $id,
                'nombre' => trim((string) ($registro->cuota?->nombre ?? '')),
                'ano' => (string) ($registro->terlec?->ano ?? ''),
                'saldo' => CuotasFormato::importeParaInput($saldo),
                'porcent' => $linea['porcent'] ?? self::formatearPorcent($calc['porcent']),
                'etiquetaPorcent' => self::etiquetaPorcentDesdeCalculo($calc),
                'interesFmt' => CuotasFormato::formatearImporte($calc['interes']),
                'bonificacionFmt' => CuotasFormato::formatearImporte($calc['bonificacion']),
                'totalFmt' => CuotasFormato::formatearImporte($calc['aPagar']),
            ];
        }

        return [
            'lineas' => $lineas,
            'neto' => round($neto, 2),
            'interes' => round($interes, 2),
            'bonificacion' => round($bonificacion, 2),
            'total' => round($total, 2),
        ];
    }

    private static function formatearPorcent(float $valor): string
    {
        $s = rtrim(rtrim(number_format($valor, 4, '.', ''), '0'), '.');

        return $s === '' ? '0' : $s;
    }

    /**
     * @param  array{esRecargo: bool, usaDias: bool, diasMora: int, usaMeses?: bool, mesesMora?: int, porcan?: string}  $calc
     */
    private static function etiquetaPorcentDesdeCalculo(array $calc): string
    {
        if (! $calc['esRecargo']) {
            return 'PORCENTAJE BONIFICACIÓN';
        }

        if (($calc['porcan'] ?? '') === '$') {
            return 'INTERÉS ($)';
        }

        if ($calc['usaMeses'] ?? false) {
            $meses = (int) ($calc['mesesMora'] ?? 0);
            $sufijoMeses = $meses === 1 ? 'mes' : 'meses';

            if (($calc['porcan'] ?? '') === 'm') {
                return 'INTERÉS MENSUAL ($) - '.$meses.' '.$sufijoMeses;
            }

            return '% INTERÉS MENSUAL - '.$meses.' '.$sufijoMeses;
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
            return ImputacionPagoService::fechaHoraPago($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public function render()
    {
        $registro = $this->registro();
        $esUnaCuota = $this->esUnaCuota();

        return view('livewire.cuotas.imputar-pago', [
            'registro' => $registro,
            'esUnaCuota' => $esUnaCuota,
            'cantidadCuotas' => count($this->idsCuotasGeneradas),
            'resumenMultiples' => $esUnaCuota ? null : $this->resumenMultiples(),
            'encabezado' => GestionAranceles::encabezadoEstudiante($this->idLegajo),
            'mediosPago' => GestionAranceles::mediosDePagoImputacion(),
            'muestraOpcionesComprobante' => tenantCuotasFacturacionAfipMuestraEnImputacionPago(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Imputar pago']);
    }
}
