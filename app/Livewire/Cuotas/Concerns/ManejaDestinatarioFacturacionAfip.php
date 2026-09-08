<?php

namespace App\Livewire\Cuotas\Concerns;

use App\Support\Alumnos\ActualizacionDatosPersonalesComun;
use App\Support\Cuotas\FacturacionAfipComun;
use App\Support\Cuotas\GestionAranceles;
use App\Support\DniInput;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Modal para cargar `legajos.respAdmiNom` / `respAdmiDni` al facturar AFIP.
 */
trait ManejaDestinatarioFacturacionAfip
{
    public bool $modalRespAdmiAbierto = false;

    public ?int $respAdmiIdLegajo = null;

    public string $respAdmiNombre = '';

    public string $respAdmiDni = '';

    public string $respAdmiVinculo = '';

    /** @var array<string, array{apellido: string, nombrePila: string, nombre: string, dni: string, email: string, tieneDatos: bool}> */
    public array $respAdmiVinculos = [];

    public string $respAdmiEstudianteEtiqueta = '';

    abstract protected function autorizarEdicionDestinatarioAfip(int $idLegajo): bool;

    protected function idLegajoDestinatarioAfipPorDefecto(): int
    {
        return property_exists($this, 'idLegajo') ? (int) $this->idLegajo : 0;
    }

    public function abrirModalRespAdmi(?int $idLegajo = null): void
    {
        $id = (int) ($idLegajo ?? $this->idLegajoDestinatarioAfipPorDefecto());
        if ($id < 1 || ! $this->autorizarEdicionDestinatarioAfip($id)) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo validar el estudiante seleccionado.');

            return;
        }

        $legajo = GestionAranceles::legajoParaFacturacionAfip($id);
        if ($legajo === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró el estudiante.');

            return;
        }

        $this->respAdmiIdLegajo = $id;
        $this->respAdmiNombre = FacturacionAfipComun::nombreDestinatarioAfipDesdeLegajo($legajo);
        $this->respAdmiDni = FacturacionAfipComun::dniDestinatarioAfipDesdeLegajo($legajo);
        $this->respAdmiVinculos = FacturacionAfipComun::vinculosResponsableEconomico($legajo);
        $this->respAdmiVinculo = '';
        $this->respAdmiEstudianteEtiqueta = trim(($legajo->apellido ?? '').', '.($legajo->nombre ?? ''));
        $this->resetValidation(['respAdmiNombre', 'respAdmiDni', 'respAdmiVinculo']);
        $this->modalRespAdmiAbierto = true;
    }

    public function cerrarModalRespAdmi(): void
    {
        $this->modalRespAdmiAbierto = false;
        $this->respAdmiIdLegajo = null;
        $this->respAdmiNombre = '';
        $this->respAdmiDni = '';
        $this->respAdmiVinculo = '';
        $this->respAdmiVinculos = [];
        $this->respAdmiEstudianteEtiqueta = '';
        $this->resetValidation(['respAdmiNombre', 'respAdmiDni', 'respAdmiVinculo']);
    }

    public function seleccionarRespAdmiVinculo(string $vinculo): void
    {
        if (! in_array($vinculo, ['padre', 'madre', 'tutor'], true)) {
            return;
        }

        $fila = $this->respAdmiVinculos[$vinculo] ?? ['nombre' => '', 'dni' => ''];
        $this->respAdmiVinculo = $vinculo;
        $this->respAdmiNombre = trim((string) ($fila['nombre'] ?? ''));
        $this->respAdmiDni = (string) ($fila['dni'] ?? '');
        $this->resetValidation(['respAdmiNombre', 'respAdmiDni']);
    }

    public function guardarRespAdmi(): void
    {
        $idLegajo = (int) ($this->respAdmiIdLegajo ?? 0);
        if ($idLegajo < 1 || ! $this->autorizarEdicionDestinatarioAfip($idLegajo)) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo validar el estudiante seleccionado.');

            return;
        }

        $rateKey = 'cuotas:facturacion-afip:resp-admi:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 20)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 60);

        $this->respAdmiDni = DniInput::digitsOnly($this->respAdmiDni);
        $this->validate([
            'respAdmiNombre' => ActualizacionDatosPersonalesComun::reglaNombreDestinatarioFacturacionAfip(),
            'respAdmiDni' => ActualizacionDatosPersonalesComun::reglaDniDestinatarioFacturacionAfip(),
        ]);

        $resultado = FacturacionAfipComun::persistirDestinatarioEnLegajo(
            $idLegajo,
            $this->respAdmiNombre,
            $this->respAdmiDni,
        );
        if (! $resultado['ok']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['mensaje']);

            return;
        }

        $this->cerrarModalRespAdmi();
        $this->afterGuardarDestinatarioAfip();
        $this->dispatch('se-swal-exito', mensaje: $resultado['mensaje']);
    }

    /**
     * Abre el modal si falta destinatario AFIP. True = hay que abortar la facturación.
     */
    protected function abrirModalSiFaltaDestinatarioAfip(?int $idLegajo = null): bool
    {
        $id = (int) ($idLegajo ?? $this->idLegajoDestinatarioAfipPorDefecto());
        $legajo = GestionAranceles::legajoParaFacturacionAfip($id);
        if ($legajo === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró el estudiante.');

            return true;
        }

        $destinatario = FacturacionAfipComun::destinatarioFacturaDesdeLegajo($legajo);
        if ($destinatario['valido']) {
            return false;
        }

        $this->abrirModalRespAdmi($id);

        return true;
    }

    protected function afterGuardarDestinatarioAfip(): void
    {
    }
}
