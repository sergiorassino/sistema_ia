<?php

namespace App\Livewire\Cooperadora;

use App\Models\CoopProveedor;
use App\Support\Cooperadora\MedioPagoCooperadora;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\Cooperadora\RegistroEgresoService;
use App\Support\Cooperadora\ValidacionFormularioCooperadora;
use App\Support\ProfesorMenuPortal;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

class EgresoForm extends Component
{
    public int|string $idProveedor = '';

    public string $fecha = '';

    public string $concepto = '';

    public string $importe = '';

    public string $firmante = '';

    public int|string $idMedioPago = '';

    public function mount(): void
    {
        abort_unless(PermisosCooperadora::puedeEgresos(), 403);
        $this->fecha = now()->format('Y-m-d');
    }

    public function guardar(): void
    {
        abort_unless(PermisosCooperadora::puedeEgresos(), 403);

        $key = 'coop:egreso:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 15)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $validated = ValidacionFormularioCooperadora::validar($this, [
            'idProveedor' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'concepto' => ['required', 'string', 'max:2000'],
            'importe' => ['required', 'numeric', 'min:0.01'],
            'firmante' => ['nullable', 'string', 'max:120'],
            'idMedioPago' => ['required', 'integer', Rule::in(MedioPagoCooperadora::idsActivos())],
        ], [
            'idProveedor' => 'Proveedor',
            'fecha' => 'Fecha',
            'concepto' => 'Concepto',
            'importe' => 'Importe',
            'idMedioPago' => 'Medio de pago',
        ]);

        CoopProveedor::query()->where('activo', true)->findOrFail((int) $validated['idProveedor']);

        $egreso = RegistroEgresoService::registrar([
            'id_proveedor' => (int) $validated['idProveedor'],
            'fecha' => $validated['fecha'],
            'concepto' => $validated['concepto'],
            'importe' => (float) $validated['importe'],
            'firmante' => $validated['firmante'] ?? null,
            'id_medio_pago' => (int) $validated['idMedioPago'],
        ]);

        $ref = OpaqueRouteToken::forCoopOrdenPago((int) $egreso->id);
        $urlPdf = route('cooperadora.orden-pago.pdf', ['ref' => $ref]);

        session()->flash('success', 'Egreso registrado. Orden Nº '.$egreso->orden_numero);
        $this->dispatch('cooperadora-abrir-pdf', url: $urlPdf);
        $this->redirectRoute('cooperadora.egresos', navigate: true);
    }

    public function render()
    {
        $proveedores = CoopProveedor::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('livewire.cooperadora.egreso-form', [
            'proveedores' => $proveedores,
            'mediosPago' => MedioPagoCooperadora::paraSelector(),
        ])->layout(ProfesorMenuPortal::layoutStaff(), ['pageTitle' => 'Cooperadora — Nuevo egreso']);
    }
}
