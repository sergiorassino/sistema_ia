<?php

namespace App\Livewire\CalificacionesInicial;

use App\Support\CalificacionesInicial\CalificacionesInicialIndicadoresCatalogo;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\CalificacionesInicialPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Livewire\Component;

/**
 * Listado de espacios curriculares del nivel inicial agrupados por curso/sala.
 */
class EditarIndicadoresIndex extends Component
{
    public bool $modoPortalDocente = false;

    public function mount(): void
    {
        $this->modoPortalDocente = CalificacionesInicialPortalDocente::esPortalDocente();

        if ($this->modoPortalDocente) {
            CalificacionesInicialPortalDocente::abortSiMenuInactivo(CalificacionesInicialPortalDocente::MENU_INDICADORES);
        } else {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para calificaciones.',
            );
        }

        CalificacionesInicialPortalDocente::abortSiNoEsInicial();
        CalificacionesInicialIndicadoresCatalogo::abortSiTablaInexistente();
    }

    public function render()
    {
        $ctx = schoolCtx();
        $grupos = CalificacionesInicialIndicadoresCatalogo::materiasAgrupadasPorCurso(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );
        $grupos = CalificacionesInicialPortalDocente::filtrarGruposMaterias($grupos);

        return view('livewire.calificaciones-inicial.editar-indicadores-index', [
            'grupos' => $grupos,
        ])->layout(CalificacionesInicialPortalDocente::layout(), ['pageTitle' => 'Editar indicadores (inicial)']);
    }
}
