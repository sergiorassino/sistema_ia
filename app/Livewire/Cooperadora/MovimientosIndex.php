<?php

namespace App\Livewire\Cooperadora;

use App\Models\CoopItemIngreso;
use App\Models\CoopProveedor;
use App\Models\CoopRubroIngreso;
use App\Support\Cooperadora\MedioPagoCooperadora;
use App\Support\Cooperadora\MovimientosConsulta;
use App\Support\Cooperadora\MovimientosFiltros;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\ProfesorMenuPortal;
use Livewire\Component;

class MovimientosIndex extends Component
{
    public string $fechaDesde = '';

    public string $fechaHasta = '';

    public string $tipoMov = '';

    public int|string $idRubro = '';

    public int|string $idItem = '';

    public int|string $idProveedor = '';

    public string $tipoIngreso = '';

    public int|string $idMedioPago = '';

    public string $busqueda = '';

    public function mount(): void
    {
        abort_unless(PermisosCooperadora::puedeMovimientos(), 403);
        $this->fechaDesde = now()->format('Y-m-d');
        $this->fechaHasta = now()->format('Y-m-d');
    }

    public function updatedIdRubro(): void
    {
        $this->idItem = '';
    }

    public function limpiarFiltros(): void
    {
        $this->reset('tipoMov', 'idRubro', 'idItem', 'idProveedor', 'tipoIngreso', 'idMedioPago', 'busqueda');
    }

    public function getPdfUrlProperty(): string
    {
        if ($this->fechaDesde === '' || $this->fechaHasta === '') {
            return '#';
        }

        return route('cooperadora.movimientos.pdf', array_merge(
            ['desde' => $this->fechaDesde, 'hasta' => $this->fechaHasta],
            $this->filtros()->aQuery(),
        ));
    }

    private function filtros(): MovimientosFiltros
    {
        return MovimientosFiltros::desde([
            'tipoMov' => $this->tipoMov,
            'idRubro' => $this->idRubro,
            'idItem' => $this->idItem,
            'idProveedor' => $this->idProveedor,
            'tipoIngreso' => $this->tipoIngreso,
            'idMedioPago' => $this->idMedioPago,
            'busqueda' => $this->busqueda,
        ]);
    }

    public function render()
    {
        $filas = collect();
        $resumen = [
            'total_ingresos' => 0.0,
            'total_egresos' => 0.0,
            'saldo' => 0.0,
            'filas_con_saldo' => collect(),
        ];

        if ($this->fechaDesde !== '' && $this->fechaHasta !== '') {
            $filas = MovimientosConsulta::listado($this->fechaDesde, $this->fechaHasta, $this->filtros());
            $resumen = MovimientosConsulta::conSaldoAcumulado($filas);
        }

        $rubros = CoopRubroIngreso::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $items = collect();
        if ((int) $this->idRubro > 0) {
            $items = CoopItemIngreso::query()
                ->where('id_rubro', (int) $this->idRubro)
                ->where('activo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre']);
        }

        $proveedores = CoopProveedor::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('livewire.cooperadora.movimientos-index', [
            'filas' => $resumen['filas_con_saldo'],
            'totalIngresos' => $resumen['total_ingresos'],
            'totalEgresos' => $resumen['total_egresos'],
            'saldo' => $resumen['saldo'],
            'rubros' => $rubros,
            'items' => $items,
            'proveedores' => $proveedores,
            'mediosPago' => MedioPagoCooperadora::paraSelector(),
            'hayFiltros' => $this->filtros()->tieneAlguno(),
        ])->layout(ProfesorMenuPortal::layoutStaff(), ['pageTitle' => 'Cooperadora — Movimientos']);
    }
}
