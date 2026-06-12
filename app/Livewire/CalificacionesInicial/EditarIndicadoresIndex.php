<?php

namespace App\Livewire\CalificacionesInicial;

use App\Support\CalificacionesInicial\CalificacionesInicialIndicadoresCatalogo;
use App\Support\NivelSistema;
use Livewire\Component;

/**
 * Listado de espacios curriculares del nivel inicial agrupados por curso/sala.
 */
class EditarIndicadoresIndex extends Component
{
    public function mount(): void
    {
        abort_unless(tienePermiso(\App\Support\PermisosIaCatalog::CALIF_CARGA), 403, 'Sin permiso para calificaciones.');
        abort_unless(
            NivelSistema::esInicial((int) schoolCtx()->idNivel),
            403,
            'Este módulo corresponde al nivel inicial. Cambie el contexto de nivel en el menú lateral.'
        );
        CalificacionesInicialIndicadoresCatalogo::abortSiTablaInexistente();
    }

    public function render()
    {
        $ctx = schoolCtx();
        $grupos = CalificacionesInicialIndicadoresCatalogo::materiasAgrupadasPorCurso(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );

        return view('livewire.calificaciones-inicial.editar-indicadores-index', [
            'grupos' => $grupos,
        ])->layout('layouts.app', ['pageTitle' => 'Editar indicadores (inicial)']);
    }
}
