<?php

namespace App\Livewire\Estadistica\Concerns;

use App\Support\Navegacion\MenuSecretariaPerfil;
use App\Support\NivelSistema;
use App\Support\PermisosIaCatalog;

trait RequiresPermisoEstadisticaRendimiento
{
    protected function autorizarEstadisticaRendimiento(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ESTADISTICA_RENDIMIENTO_ESCOLAR), 403);
        abort_unless(MenuSecretariaPerfil::muestraCalificacionesSecundario(), 403);
        abort_unless(NivelSistema::esSecundario((int) (schoolCtx()->idNivel ?? 0)), 403);
    }

    protected function idTerlecContexto(): int
    {
        return (int) (schoolCtx()->idTerlec ?? 0);
    }
}
