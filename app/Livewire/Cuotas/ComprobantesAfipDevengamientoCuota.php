<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\ComprobantesAfipCuotaService;
use App\Support\Cuotas\GestionAranceles;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\PermisosCuotas;
use Livewire\Component;

/**
 * Comprobantes AFIP (facturas y notas de crédito) de una cuota en modo devengamiento.
 */
class ComprobantesAfipDevengamientoCuota extends Component
{
    public int $idLegajo;

    public int $idCuotaGenerada;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);
        abort_unless(ComprobantesAfipCuotaService::moduloDisponible(), 404);
        abort_unless(tenantCuotasFacturacionAfipEnDevengamiento(), 404);

        $idLegajo = ContextoEstudianteSesion::legajo(ContextoEstudianteSesion::CUOTAS_GESTION);
        $idCuotaGenerada = ContextoEstudianteSesion::cuotaGenerada(ContextoEstudianteSesion::CUOTAS_GESTION);

        abort_if(
            $idLegajo === null
            || $idCuotaGenerada === null
            || GestionAranceles::legajoParaGestion($idLegajo) === null
            || GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo) === null,
            404,
        );

        $this->idLegajo = $idLegajo;
        $this->idCuotaGenerada = $idCuotaGenerada;
    }

    public function render()
    {
        $registro = GestionAranceles::cuotaDelLegajo($this->idCuotaGenerada, $this->idLegajo);

        return view('livewire.cuotas.comprobantes-afip-devengamiento-cuota', [
            'registro' => $registro,
            'encabezado' => GestionAranceles::encabezadoEstudiante($this->idLegajo),
            'comprobantes' => ComprobantesAfipCuotaService::comprobantesDeCuota(
                $this->idLegajo,
                $this->idCuotaGenerada,
            ),
            'facturaVigente' => ComprobantesAfipCuotaService::facturaVigentePorCuotaGenerada($this->idCuotaGenerada),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Comprobantes AFIP']);
    }
}
