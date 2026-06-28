<?php

namespace App\Livewire\Alumnos;

use App\Support\Alumnos\ArancelesEscolares;
use App\Support\Cuotas\GestionAranceles;
use Livewire\Component;

class ArancelesEscolaresIndex extends Component
{
    public bool $mostrarHistorial = false;

    public function mount(): void
    {
        abort_unless(tenantAutogestionArancelesEscolaresHabilitada(), 404);
        abort_unless(tenantAutogestionArancelesEscolaresImplementacion() !== 'gestion_aranceles', 404);
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

        return view('livewire.alumnos.aranceles-escolares-index', [
            'cuotas' => $cuotas,
            'encabezado' => ArancelesEscolares::encabezadoAutogestion(),
            'totalesAdeudados' => $this->mostrarHistorial
                ? ['neto' => 0.0, 'conIntereses' => 0.0]
                : GestionAranceles::totalizarSaldosAdeudados($cuotas),
            'facturasAfip' => $this->mostrarHistorial
                ? ArancelesEscolares::facturasAfipVigentesHistorial($cuotas)
                : [],
            'muestraComprobanteAfip' => $this->mostrarHistorial && tenantCuotasFacturacionAfipHabilitada(),
        ])->layout('layouts.alumno', ['pageTitle' => 'Aranceles Escolares']);
    }
}
