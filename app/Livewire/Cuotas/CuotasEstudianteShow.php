<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\ComprobantesAfipCuotaService;
use App\Support\Cuotas\GestionAranceles;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\PermisosCuotas;
use Livewire\Component;

/**
 * Listado de cuotas del estudiante: vista normal (año actual + impagas anteriores) o historial completo.
 */
class CuotasEstudianteShow extends Component
{
    public int $idLegajo;

    public bool $mostrarHistorial = false;

    /** @var list<int|string> */
    public array $cuotasSeleccionadas = [];

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $idLegajo = ContextoEstudianteSesion::legajo(ContextoEstudianteSesion::CUOTAS_GESTION);
        abort_if($idLegajo === null || GestionAranceles::legajoParaGestion($idLegajo) === null, 404);

        $this->idLegajo = $idLegajo;
        $this->mostrarHistorial = ContextoEstudianteSesion::mostrarHistorialCuotas(
            ContextoEstudianteSesion::CUOTAS_GESTION,
        );
    }

    public function alternarVistaCuotas(): void
    {
        $this->mostrarHistorial = ! $this->mostrarHistorial;
        $this->cuotasSeleccionadas = [];
        ContextoEstudianteSesion::fijarVistaCuotas(
            ContextoEstudianteSesion::CUOTAS_GESTION,
            $this->mostrarHistorial,
        );
    }

    public function seleccionarTodasAdeudadas(): void
    {
        $this->cuotasSeleccionadas = $this->cuotasListado()
            ->filter(fn ($c) => (float) ($c->faltapa ?? 0) > 0)
            ->map(fn ($c) => (string) (int) $c->id)
            ->values()
            ->all();
    }

    public function limpiarSeleccion(): void
    {
        $this->cuotasSeleccionadas = [];
    }

    public function irImputarSeleccionadas(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $this->cuotasSeleccionadas),
            fn (int $id) => $id > 0,
        )));

        if ($ids === []) {
            $this->dispatch('se-swal-aviso', mensaje: 'Seleccioná al menos una cuota adeudada.', titulo: 'Cobro de cuotas');

            return;
        }

        $validas = GestionAranceles::cuotasParaImputacion($ids, $this->idLegajo)->pluck('id')->all();
        if (count($validas) !== count($ids)) {
            $this->dispatch('se-swal-error', mensaje: 'Alguna cuota seleccionada ya no está disponible para cobro.', titulo: 'Cobro de cuotas');
            $this->cuotasSeleccionadas = $validas;

            return;
        }

        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::CUOTAS_GESTION, [
            'idLegajos' => $this->idLegajo,
            'idsCuotasGeneradas' => $ids,
            'vistaCuotas' => $this->mostrarHistorial
                ? ContextoEstudianteSesion::VISTA_CUOTAS_HISTORIAL
                : ContextoEstudianteSesion::VISTA_CUOTAS_ANIO,
        ]);

        $this->redirectRoute('cuotas.cuota.imputar', navigate: true);
    }

    public function render()
    {
        $cuotas = $this->cuotasListado();
        $idsCuotas = $cuotas->pluck('id')->map(fn ($id) => (int) $id)->all();
        $afipEnDevengamiento = tenantCuotasFacturacionAfipEnDevengamiento();
        $muestraComprobanteAfip = ComprobantesAfipCuotaService::moduloDisponible() && $afipEnDevengamiento;

        return view('livewire.cuotas.estudiante-show', [
            'encabezado' => GestionAranceles::encabezadoEstudiante($this->idLegajo),
            'cuotas' => $cuotas,
            'totalesAdeudados' => GestionAranceles::totalizarSaldosAdeudados($cuotas),
            'cantidadSeleccionadas' => count($this->cuotasSeleccionadas),
            'muestraComprobanteAfip' => $muestraComprobanteAfip,
            'facturasAfipPorCuota' => [],
            'cuotasConComprobanteAfip' => $muestraComprobanteAfip
                ? ComprobantesAfipCuotaService::cuotasConComprobantesAfip($idsCuotas)
                : [],
            'afipEnDevengamiento' => $afipEnDevengamiento,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Gestión de aranceles']);
    }

    private function cuotasListado()
    {
        return $this->mostrarHistorial
            ? GestionAranceles::cuotasHistorial($this->idLegajo)
            : GestionAranceles::cuotasDelEstudiante($this->idLegajo);
    }
}
