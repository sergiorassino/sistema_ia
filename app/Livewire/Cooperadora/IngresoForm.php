<?php

namespace App\Livewire\Cooperadora;

use App\Models\CoopItemIngreso;
use App\Models\CoopRubroIngreso;
use App\Support\Cooperadora\BusquedaEstudianteCooperadora;
use App\Support\Cooperadora\CooperadoraConfig;
use App\Support\Cooperadora\DescuentoHermanos;
use App\Support\Cooperadora\MedioPagoCooperadora;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\Cooperadora\RegistroIngresoService;
use App\Support\Cooperadora\ValidacionFormularioCooperadora;
use App\Support\ProfesorMenuPortal;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

class IngresoForm extends Component
{
    public string $modo = 'por_alumno';

    public int|string $idRubro = '';

    public int|string $idItem = '';

    public string $search = '';

    public ?int $idLegajo = null;

    public string $pagadorNombre = '';

    public string $fecha = '';

    public string $concepto = '';

    public string $importeBruto = '';

    public string $descuentoPct = '0';

    public string $importe = '';

    public int|string $idMedioPago = '';

    public function mount(): void
    {
        abort_unless(PermisosCooperadora::puedeIngresos(), 403);
        $tipo = request()->query('tipo');
        $this->modo = in_array($tipo, ['por_alumno', 'eventual', 'uniforme'], true) ? (string) $tipo : 'por_alumno';
        $this->fecha = now()->format('Y-m-d');
    }

    public function updatedModo(): void
    {
        $this->idRubro = '';
        $this->idItem = '';
        $this->resetEstudiante();
    }

    public function updatedIdRubro(): void
    {
        $this->idItem = '';
        $this->recalcularImporte();
    }

    public function updatedIdItem(): void
    {
        $this->recalcularImporte();
    }

    public function updatedIdLegajo(): void
    {
        $this->recalcularImporte();
    }

    public function seleccionarLegajo(int $id): void
    {
        $legajo = BusquedaEstudianteCooperadora::legajo($id);
        abort_unless($legajo !== null, 404);
        $this->idLegajo = $id;
        $this->search = '';
        $this->pagadorNombre = BusquedaEstudianteCooperadora::nombrePagadorDesdeLegajo($legajo);
        $this->recalcularImporte();
    }

    public function recalcularImporte(): void
    {
        $bruto = 0.0;
        if ($this->idItem !== '' && $this->idItem !== '0') {
            $item = CoopItemIngreso::query()->find((int) $this->idItem);
            $bruto = (float) ($item?->precio ?? 0);
        } elseif ($this->importeBruto !== '') {
            $bruto = (float) str_replace(',', '.', $this->importeBruto);
        }

        $pct = $this->idLegajo
            ? DescuentoHermanos::porcentajeParaLegajo((int) $this->idLegajo)
            : 0.0;

        $this->descuentoPct = number_format($pct, 2, '.', '');
        $this->importeBruto = number_format($bruto, 2, '.', '');
        $this->importe = number_format(DescuentoHermanos::importeConDescuento($bruto, $pct), 2, '.', '');
    }

    public function guardar(): void
    {
        abort_unless(PermisosCooperadora::puedeIngresos(), 403);

        $key = 'coop:ingreso:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 15)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $rubrosIds = CoopRubroIngreso::query()
            ->where('tipo', $this->modo)
            ->where('activo', true)
            ->pluck('id')
            ->all();

        $rubro = CoopRubroIngreso::query()
            ->where('tipo', $this->modo)
            ->where('activo', true)
            ->find((int) $this->idRubro);

        $itemsIds = [];
        if ($rubro !== null) {
            $q = CoopItemIngreso::query()
                ->where('id_rubro', $rubro->id)
                ->where('activo', true);
            if ($rubro->es_anual) {
                $q->where('anio', CooperadoraConfig::anioVigente());
            }
            $itemsIds = $q->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $rules = [
            'modo' => ['required', Rule::in(['por_alumno', 'eventual', 'uniforme'])],
            'idRubro' => ['required', 'integer', Rule::in($rubrosIds)],
            'idItem' => ['required', 'integer', Rule::in($itemsIds)],
            'fecha' => ['required', 'date'],
            'pagadorNombre' => ['required', 'string', 'max:200'],
            'importe' => ['required', 'numeric', 'min:0.01'],
            'idMedioPago' => ['required', 'integer', Rule::in(MedioPagoCooperadora::idsActivos())],
            'concepto' => ['nullable', 'string', 'max:2000'],
        ];

        if (in_array($this->modo, ['por_alumno', 'uniforme'], true)) {
            $rules['idLegajo'] = ['required', 'integer', 'min:1'];
        }

        $validated = ValidacionFormularioCooperadora::validar($this, $rules, [
            'idRubro' => 'Rubro',
            'idItem' => 'Ítem',
            'idLegajo' => 'Alumno',
            'pagadorNombre' => 'Señor / pagador',
            'fecha' => 'Fecha',
            'importe' => 'Importe a cobrar',
            'idMedioPago' => 'Medio de pago',
        ]);

        $matricula = null;
        if (! empty($validated['idLegajo'])) {
            $matricula = BusquedaEstudianteCooperadora::matriculaActiva((int) $validated['idLegajo']);
        }

        $ingreso = RegistroIngresoService::registrar([
            'tipo' => $validated['modo'],
            'id_rubro' => (int) $validated['idRubro'],
            'id_item' => (int) $validated['idItem'],
            'id_legajo' => $validated['idLegajo'] ?? null,
            'id_matricula' => $matricula?->id,
            'pagador_nombre' => $validated['pagadorNombre'],
            'fecha' => $validated['fecha'],
            'concepto' => $validated['concepto'] ?? null,
            'importe_bruto' => (float) $this->importeBruto,
            'descuento_pct' => (float) $this->descuentoPct,
            'importe' => (float) $validated['importe'],
            'id_medio_pago' => (int) $validated['idMedioPago'],
        ]);

        $ref = OpaqueRouteToken::forCoopRecibo((int) $ingreso->id);
        $urlPdf = route('cooperadora.recibo.pdf', ['ref' => $ref]);

        session()->flash('success', 'Ingreso registrado. Recibo Nº '.$ingreso->recibo_numero);
        $this->dispatch('cooperadora-abrir-pdf', url: $urlPdf);
        $this->redirectRoute('cooperadora.ingresos', navigate: true);
    }

    private function resetEstudiante(): void
    {
        $this->search = '';
        $this->idLegajo = null;
        $this->pagadorNombre = '';
        $this->descuentoPct = '0';
    }

    public function render()
    {
        $rubros = CoopRubroIngreso::query()
            ->where('tipo', $this->modo)
            ->where('activo', true)
            ->orderBy('orden')
            ->get();

        $items = collect();
        if ((int) $this->idRubro > 0) {
            $rubro = CoopRubroIngreso::query()->find((int) $this->idRubro);
            $q = CoopItemIngreso::query()
                ->where('id_rubro', (int) $this->idRubro)
                ->where('activo', true)
                ->orderBy('orden');
            if ($rubro?->es_anual) {
                $q->where('anio', CooperadoraConfig::anioVigente());
            }
            $items = $q->get();
        }

        $legajos = trim($this->search) !== '' && in_array($this->modo, ['por_alumno', 'uniforme'], true)
            ? BusquedaEstudianteCooperadora::buscarLegajos($this->search)
            : null;

        $legajoSel = $this->idLegajo ? BusquedaEstudianteCooperadora::legajo($this->idLegajo) : null;
        $matricula = $this->idLegajo ? BusquedaEstudianteCooperadora::matriculaActiva($this->idLegajo) : null;

        return view('livewire.cooperadora.ingreso-form', [
            'rubros' => $rubros,
            'items' => $items,
            'legajos' => $legajos,
            'legajoSel' => $legajoSel,
            'matricula' => $matricula,
            'etiquetaCurso' => BusquedaEstudianteCooperadora::etiquetaCurso($matricula),
            'mediosPago' => MedioPagoCooperadora::paraSelector(),
        ])->layout(ProfesorMenuPortal::layoutStaff(), ['pageTitle' => 'Cooperadora — Nuevo ingreso']);
    }
}
