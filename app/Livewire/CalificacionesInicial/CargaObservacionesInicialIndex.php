<?php

namespace App\Livewire\CalificacionesInicial;

use App\Livewire\Concerns\BloqueoEntradaCargaNotasOffSecretaria;
use App\Support\CalificacionesInicial\CalificacionesInicialIndicadoresCatalogo;
use App\Support\CalificacionesInicial\CalificacionesInicialObservacionesDatos;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\CalificacionesInicialPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Livewire\Component;

/**
 * Listado de espacios curriculares agrupados por sala/curso para carga de observaciones.
 */
class CargaObservacionesInicialIndex extends Component
{
    use BloqueoEntradaCargaNotasOffSecretaria;

    public bool $modoPortalDocente = false;

    public function mount(): void
    {
        $this->modoPortalDocente = CalificacionesInicialPortalDocente::esPortalDocente();

        if ($this->modoPortalDocente) {
            CalificacionesInicialPortalDocente::abortSiMenuInactivo(CalificacionesInicialPortalDocente::MENU_OBSERVACIONES);
        } else {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para calificaciones.',
            );
        }

        CalificacionesInicialPortalDocente::abortSiNoEsInicial();
        CalificacionesInicialObservacionesDatos::abortSiColumnasInexistentes();

        $this->redirigirSiSecretariaCargaNotasOff($this->modoPortalDocente);
    }

    public function render()
    {
        $ctx = schoolCtx();
        $grupos = CalificacionesInicialIndicadoresCatalogo::materiasAgrupadasPorCurso(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );
        $grupos = CalificacionesInicialPortalDocente::filtrarGruposMaterias($grupos);

        return view('livewire.calificaciones-inicial.carga-observaciones-inicial-index', [
            'grupos' => $grupos,
        ])->layout(CalificacionesInicialPortalDocente::layout(), ['pageTitle' => 'Carga de observaciones (inicial)']);
    }
}
