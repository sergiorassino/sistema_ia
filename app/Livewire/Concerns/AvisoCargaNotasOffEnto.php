<?php

namespace App\Livewire\Concerns;

use App\Support\EntoCargaNotas;

/**
 * Aviso modal y modo solo lectura cuando `ento.cargaNotasOff = 1` (Menú de Docentes).
 */
trait AvisoCargaNotasOffEnto
{
    public bool $cargaNotasSoloLectura = false;

    public bool $mostrarModalNotasOff = false;

    public string $mensajeNotasOff = '';

    protected function inicializarAvisoCargaNotasOff(bool $modoPortalDocente): void
    {
        if (! $modoPortalDocente) {
            return;
        }

        $params = EntoCargaNotas::paraNivelActual();
        if (! $params['bloqueada']) {
            return;
        }

        $this->cargaNotasSoloLectura = true;
        $this->mensajeNotasOff = $params['mensaje'];
        $this->mostrarModalNotasOff = true;
    }

    public function aceptarAvisoCargaNotasOff(): void
    {
        $this->mostrarModalNotasOff = false;
    }

    protected function cargaNotasOffBloqueaEdicion(): bool
    {
        return property_exists($this, 'modoPortalDocente')
            && $this->modoPortalDocente
            && $this->cargaNotasSoloLectura;
    }

    /**
     * @return array{soloLectura: bool, mostrarModalNotasOff: bool, mensajeNotasOff: string}
     */
    protected function datosVistaAvisoCargaNotasOff(bool $modoPortalDocente): array
    {
        return [
            'soloLectura' => $modoPortalDocente && $this->cargaNotasSoloLectura,
            'mostrarModalNotasOff' => $modoPortalDocente && $this->mostrarModalNotasOff,
            'mensajeNotasOff' => $this->mensajeNotasOff,
        ];
    }
}
