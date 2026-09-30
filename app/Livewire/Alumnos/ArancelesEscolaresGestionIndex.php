<?php

namespace App\Livewire\Alumnos;

use App\Support\Alumnos\ArancelesEscolares;
use App\Support\Cuotas\ComprobantesAfipCuotaService;
use Livewire\Component;

/**
 * Gestión de aranceles — portal familia (variante UI legacy / Banco Roela).
 *
 * Misma lógica de datos que {@see ArancelesEscolaresIndex}; estética distinta.
 * Activar con `autogestion.aranceles_escolares.implementacion` = `gestion_aranceles`.
 * El historial incluye las cuotas pagadas y, si el tenant factura AFIP, el PDF vigente.
 */
class ArancelesEscolaresGestionIndex extends Component
{
    public bool $mostrarHistorial = false;

    public function mount(): void
    {
        abort_unless(tenantAutogestionArancelesEscolaresHabilitada(), 404);
        abort_unless(tenantAutogestionArancelesEscolaresImplementacion() === 'gestion_aranceles', 404);
    }

    public function alternarVistaCuotas(): void
    {
        $this->mostrarHistorial = ! $this->mostrarHistorial;
    }

    public function render()
    {
        $cuotas = $this->mostrarHistorial
            ? ArancelesEscolares::cuotasHistorial()
            : ArancelesEscolares::cuotasPendientes();

        $muestraComprobanteAfip = ComprobantesAfipCuotaService::moduloDisponible();

        return view('livewire.alumnos.aranceles-escolares-gestion-index', [
            'cuotas' => $cuotas,
            'encabezado' => ArancelesEscolares::encabezadoAutogestion(),
            'botonPagosUrl' => tenantArancelesEscolaresBotonPagosUrl(),
            'facturasAfip' => $muestraComprobanteAfip
                ? ArancelesEscolares::facturasAfipVigentes($cuotas)
                : [],
            'muestraComprobanteAfip' => $muestraComprobanteAfip,
        ])->layout('layouts.alumno', [
            'pageTitle' => tenantAutogestionArancelesEscolaresMenuEtiqueta(),
        ]);
    }
}
