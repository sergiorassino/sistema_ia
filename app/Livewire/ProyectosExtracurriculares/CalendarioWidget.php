<?php

namespace App\Livewire\ProyectosExtracurriculares;

use App\Support\Navegacion\MenuSecretariaPerfil;
use App\Support\ProfesorMenuPortal;
use App\Support\ProyectosExtracurriculares\ExtActividadesService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CalendarioWidget extends Component
{
    public function render()
    {
        $modoPortalDocente = ProfesorMenuPortal::usaMenuDocentes(Auth::user());
        $visible = $modoPortalDocente || MenuSecretariaPerfil::muestraProyectosExtracurriculares();

        $tablasOk = $visible && ExtActividadesService::tablasDisponibles();
        $eventos = $tablasOk ? ExtActividadesService::proximosEventos(6) : collect();

        return view('livewire.proyectos-extracurriculares.calendario-widget', [
            'visible' => $visible,
            'tablasOk' => $tablasOk,
            'eventos' => $eventos,
            'rutaCalendario' => $modoPortalDocente
                ? 'portalDocente.calendarioEscolar'
                : 'calendarioEscolar',
        ]);
    }
}
