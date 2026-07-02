<?php

namespace App\Livewire\Concerns;

use App\Support\EntoCargaNotas;

/**
 * Bloquea la entrada a módulos de carga desde el Menú de Secretaría cuando `ento.cargaNotasOff = 1`.
 */
trait BloqueoEntradaCargaNotasOffSecretaria
{
    protected function redirigirSiSecretariaCargaNotasOff(bool $modoPortalDocente): void
    {
        if ($modoPortalDocente || ! EntoCargaNotas::entradaSecretariaBloqueada()) {
            return;
        }

        session()->flash('carga_notas_off_aviso', EntoCargaNotas::mensajeEntradaSecretariaBloqueada());
        $this->redirect(route('dashboard'), navigate: false);
    }

    protected function secretariaCargaNotasOffBloqueaAccion(bool $modoPortalDocente): bool
    {
        return ! $modoPortalDocente && EntoCargaNotas::entradaSecretariaBloqueada();
    }
}
