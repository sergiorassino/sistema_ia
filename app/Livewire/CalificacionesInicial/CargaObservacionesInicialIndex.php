<?php

namespace App\Livewire\CalificacionesInicial;

use App\Support\CalificacionesInicial\CalificacionesInicialIndicadoresCatalogo;
use App\Support\CalificacionesInicial\CalificacionesInicialObservacionesDatos;
use App\Support\NivelSistema;
use Livewire\Component;

/**
 * Listado de espacios curriculares agrupados por sala/curso para carga de observaciones.
 */
class CargaObservacionesInicialIndex extends Component
{
    public function mount(): void
    {
        abort_unless(tienePermiso(\App\Support\PermisosIaCatalog::CALIF_CARGA), 403, 'Sin permiso para calificaciones.');
        abort_unless(
            NivelSistema::esInicial((int) schoolCtx()->idNivel),
            403,
            'Este módulo corresponde al nivel inicial. Cambie el contexto de nivel en el menú lateral.'
        );
        CalificacionesInicialObservacionesDatos::abortSiColumnasInexistentes();
    }

    public function render()
    {
        $ctx = schoolCtx();
        $grupos = CalificacionesInicialIndicadoresCatalogo::materiasAgrupadasPorCurso(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );

        return view('livewire.calificaciones-inicial.carga-observaciones-inicial-index', [
            'grupos' => $grupos,
        ])->layout('layouts.app', ['pageTitle' => 'Carga de observaciones (inicial)']);
    }
}
