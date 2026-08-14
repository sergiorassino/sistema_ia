<?php

namespace App\Livewire\Alumnos\Concerns;

use App\Models\Legajo;
use App\Support\Alumnos\FotoCarnetLegajo;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Subida de foto carnet en actualización de datos (portal familia),
 * solo si el tenant lo habilita y `fotoCarnet` está en alguna solapa del legajo.
 */
trait ConFotoCarnetActualizacionDatos
{
    /** Path relativo en `legajos.fotoCarnet` (disco privado). */
    public string $fotoCarnetPath = '';

    /** @var TemporaryUploadedFile|null */
    public $fotoCarnetUpload = null;

    public bool $removeFotoCarnet = false;

    protected function montarFotoCarnetDesdeLegajo(Legajo $legajo): void
    {
        if (! FotoCarnetLegajo::habilitadaEnAutogestion()) {
            $this->fotoCarnetPath = '';
            $this->fotoCarnetUpload = null;
            $this->removeFotoCarnet = false;

            return;
        }

        $this->fotoCarnetPath = trim((string) ($legajo->fotoCarnet ?? ''));
        $this->fotoCarnetUpload = null;
        $this->removeFotoCarnet = false;
    }

    public function updatedFotoCarnetUpload(): void
    {
        $this->resetValidation('fotoCarnetUpload');

        if (! FotoCarnetLegajo::habilitadaEnAutogestion()) {
            $this->fotoCarnetUpload = null;

            return;
        }

        if ($this->fotoCarnetUpload === null) {
            return;
        }

        if (! ($this->fotoCarnetUpload instanceof TemporaryUploadedFile)) {
            $this->fotoCarnetUpload = null;

            return;
        }

        $this->removeFotoCarnet = false;

        $error = FotoCarnetLegajo::validarUpload($this->fotoCarnetUpload);
        if ($error !== null) {
            $this->addError('fotoCarnetUpload', $error);
            $this->fotoCarnetUpload = null;
        }
    }

    public function onFotoCarnetUploadFailed(): void
    {
        $this->addError(
            'fotoCarnetUpload',
            'No se pudo subir la foto. Verifique el tamaño (máx. 2 MB) e intente de nuevo.'
        );
        $this->fotoCarnetUpload = null;
    }

    public function marcarQuitarFotoCarnet(): void
    {
        if (! FotoCarnetLegajo::habilitadaEnAutogestion() || $this->bloqueado) {
            return;
        }

        $this->fotoCarnetUpload = null;
        $this->resetValidation('fotoCarnetUpload');

        if (trim($this->fotoCarnetPath) !== '') {
            $this->removeFotoCarnet = true;
        } else {
            $this->removeFotoCarnet = false;
        }

        $this->dispatch('act-datos-foto-carnet-cleared');
    }

    public function deshacerQuitarFotoCarnet(): void
    {
        if (! FotoCarnetLegajo::habilitadaEnAutogestion()) {
            return;
        }

        $this->removeFotoCarnet = false;
    }

    /**
     * Valida el estado de la foto antes de guardar el resto del formulario.
     * Devuelve false si hay error (ya cargado en el bag).
     */
    protected function validarFotoCarnetAntesDeGuardar(): bool
    {
        if (! FotoCarnetLegajo::habilitadaEnAutogestion()) {
            return true;
        }

        if ($this->fotoCarnetUpload !== null && ! ($this->fotoCarnetUpload instanceof TemporaryUploadedFile)) {
            $this->addError(
                'fotoCarnetUpload',
                'La subida de la foto no terminó. Espere a que finalice o vuelva a seleccionar el archivo.'
            );

            return false;
        }

        if ($this->fotoCarnetUpload instanceof TemporaryUploadedFile) {
            $error = FotoCarnetLegajo::validarUpload($this->fotoCarnetUpload);
            if ($error !== null) {
                $this->addError('fotoCarnetUpload', $error);

                return false;
            }
        }

        return true;
    }

    /**
     * Persiste cambios de foto tras guardar los datos del legajo.
     * Devuelve false si hay error (ya cargado en el bag).
     */
    protected function persistirFotoCarnetTrasGuardar(Legajo $legajo): bool
    {
        if (! FotoCarnetLegajo::habilitadaEnAutogestion()) {
            return true;
        }

        $hayCambio = $this->fotoCarnetUpload instanceof TemporaryUploadedFile
            || ($this->removeFotoCarnet && trim($this->fotoCarnetPath) !== '');

        if (! $hayCambio) {
            return true;
        }

        $resultado = FotoCarnetLegajo::persistirCambio(
            (int) $legajo->id,
            $legajo->dni ?? $this->dni,
            $this->fotoCarnetPath,
            $this->fotoCarnetUpload instanceof TemporaryUploadedFile ? $this->fotoCarnetUpload : null,
            $this->removeFotoCarnet,
        );

        if (! $resultado['ok']) {
            $this->addError('fotoCarnetUpload', $resultado['error']);

            return false;
        }

        $this->fotoCarnetPath = $resultado['path'];
        $this->fotoCarnetUpload = null;
        $this->removeFotoCarnet = false;

        return true;
    }

    /**
     * @return array{
     *     fotoCarnetHabilitada: bool,
     *     fotoCarnetUrl: ?string,
     *     etiquetaFotoCarnet: string
     * }
     */
    protected function datosVistaFotoCarnet(): array
    {
        $habilitada = FotoCarnetLegajo::habilitadaEnAutogestion();
        $url = null;

        if ($habilitada && ! $this->removeFotoCarnet) {
            $url = FotoCarnetLegajo::dataUrlPreview(
                $this->fotoCarnetPath !== '' ? $this->fotoCarnetPath : null
            );
        }

        return [
            'fotoCarnetHabilitada' => $habilitada,
            'fotoCarnetUrl' => $url,
            'etiquetaFotoCarnet' => $habilitada
                ? FotoCarnetLegajo::etiquetaDesdeSolapas()
                : 'Foto carnet',
        ];
    }
}
