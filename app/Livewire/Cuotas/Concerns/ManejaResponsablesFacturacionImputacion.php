<?php

namespace App\Livewire\Cuotas\Concerns;

use App\Support\Cuotas\FacturacionAfipComun;
use App\Support\Cuotas\GestionAranceles;
use App\Support\DniInput;
use App\Support\PermisosCuotas;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Madre / padre / responsable administrativo / estudiante al cobrar o emitir AFIP.
 */
trait ManejaResponsablesFacturacionImputacion
{
    public string $facturarA = '';

    public string $facturarMadreNombre = '';

    public string $facturarMadreDni = '';

    public string $facturarPadreNombre = '';

    public string $facturarPadreDni = '';

    public string $facturarRespAdmiNombre = '';

    public string $facturarRespAdmiDni = '';

    public string $facturarEstudianteNombre = '';

    public string $facturarEstudianteDni = '';

    /** @var list<string> */
    private const CAMPOS_RESPONSABLES_EDITABLES = [
        'facturarMadreNombre',
        'facturarMadreDni',
        'facturarPadreNombre',
        'facturarPadreDni',
        'facturarRespAdmiNombre',
        'facturarRespAdmiDni',
    ];

    public function updatedFacturarMadreNombre(): void
    {
        $this->guardarResponsablesAlSalirDelCampo();
    }

    public function updatedFacturarMadreDni(): void
    {
        $this->facturarMadreDni = DniInput::digitsOnly($this->facturarMadreDni);
        $this->guardarResponsablesAlSalirDelCampo();
    }

    public function updatedFacturarPadreNombre(): void
    {
        $this->guardarResponsablesAlSalirDelCampo();
    }

    public function updatedFacturarPadreDni(): void
    {
        $this->facturarPadreDni = DniInput::digitsOnly($this->facturarPadreDni);
        $this->guardarResponsablesAlSalirDelCampo();
    }

    public function updatedFacturarRespAdmiNombre(): void
    {
        $this->guardarResponsablesAlSalirDelCampo();
    }

    public function updatedFacturarRespAdmiDni(): void
    {
        $this->facturarRespAdmiDni = DniInput::digitsOnly($this->facturarRespAdmiDni);
        $this->guardarResponsablesAlSalirDelCampo();
    }

    public function seleccionarFacturarA(string $clave): void
    {
        if (! in_array($clave, ['madre', 'padre', 'resp_admin', 'estudiante'], true)) {
            return;
        }

        $this->facturarA = $clave;
        $this->resetValidation(['facturarA']);
    }

    /**
     * Persiste madre / padre / responsable administrativo al salir del campo.
     *
     * El valor se toma del input (`$event.target.value`) porque `updated*` con
     * `wire:model.blur` no es un gancho fiable en este flujo.
     */
    public function guardarResponsablesAlSalirDelCampo(string $campo = '', mixed $valor = null): void
    {
        abort_unless(PermisosCuotas::puedeArancelesPorEstudiante(), 403);

        $this->aplicarValorResponsableDesdeBlur($campo, $valor);

        $idLegajo = $this->idLegajoResponsables();
        if ($idLegajo < 1) {
            return;
        }

        $rateKey = 'cuotas:responsables-blur:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 40)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 60);

        $resultado = $this->persistirResponsablesFacturacion($idLegajo);
        if (! $resultado['ok']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['mensaje']);
        }
    }

    protected function cargarResponsablesFacturacion(int $idLegajo): void
    {
        $legajo = GestionAranceles::legajoParaFacturacionAfip($idLegajo);
        if ($legajo === null) {
            return;
        }

        $filas = FacturacionAfipComun::opcionesDestinatarioImputacion($legajo);
        $this->facturarMadreNombre = (string) ($filas['madre']['nombre'] ?? '');
        $this->facturarMadreDni = (string) ($filas['madre']['dni'] ?? '');
        $this->facturarPadreNombre = (string) ($filas['padre']['nombre'] ?? '');
        $this->facturarPadreDni = (string) ($filas['padre']['dni'] ?? '');
        $this->facturarRespAdmiNombre = (string) ($filas['resp_admin']['nombre'] ?? '');
        $this->facturarRespAdmiDni = (string) ($filas['resp_admin']['dni'] ?? '');
        $this->facturarEstudianteNombre = (string) ($filas['estudiante']['nombre'] ?? '');
        $this->facturarEstudianteDni = (string) ($filas['estudiante']['dni'] ?? '');
        $this->facturarA = FacturacionAfipComun::claveDestinatarioPorDefecto([
            'madre' => ['nombre' => $this->facturarMadreNombre, 'dni' => $this->facturarMadreDni],
            'padre' => ['nombre' => $this->facturarPadreNombre, 'dni' => $this->facturarPadreDni],
            'resp_admin' => ['nombre' => $this->facturarRespAdmiNombre, 'dni' => $this->facturarRespAdmiDni],
            'estudiante' => ['nombre' => $this->facturarEstudianteNombre, 'dni' => $this->facturarEstudianteDni],
        ]);
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    protected function persistirResponsablesFacturacion(int $idLegajo): array
    {
        $this->facturarMadreDni = DniInput::digitsOnly($this->facturarMadreDni);
        $this->facturarPadreDni = DniInput::digitsOnly($this->facturarPadreDni);
        $this->facturarRespAdmiDni = DniInput::digitsOnly($this->facturarRespAdmiDni);

        return FacturacionAfipComun::persistirResponsablesImputacion($idLegajo, [
            'nombremad' => $this->facturarMadreNombre,
            'dnimad' => $this->facturarMadreDni,
            'nombrepad' => $this->facturarPadreNombre,
            'dnipad' => $this->facturarPadreDni,
            'respAdmiNom' => $this->facturarRespAdmiNombre,
            'respAdmiDni' => $this->facturarRespAdmiDni,
        ]);
    }

    /**
     * @return array{idFamilia: int, responsable: string, dniResp: string, valido: bool, motivo: string}
     */
    protected function destinatarioAfipSeleccionado(): array
    {
        $mapa = [
            'madre' => [$this->facturarMadreNombre, $this->facturarMadreDni],
            'padre' => [$this->facturarPadreNombre, $this->facturarPadreDni],
            'resp_admin' => [$this->facturarRespAdmiNombre, $this->facturarRespAdmiDni],
            'estudiante' => [$this->facturarEstudianteNombre, $this->facturarEstudianteDni],
        ];
        $par = $mapa[$this->facturarA] ?? ['', ''];

        return FacturacionAfipComun::destinatarioDesdeNombreYDni((string) $par[0], (string) $par[1]);
    }

    protected function idLegajoResponsables(): int
    {
        return (int) ($this->idLegajo ?? 0);
    }

    private function aplicarValorResponsableDesdeBlur(string $campo, mixed $valor): void
    {
        if ($campo === '' || ! in_array($campo, self::CAMPOS_RESPONSABLES_EDITABLES, true)) {
            return;
        }

        $texto = is_string($valor) ? $valor : (string) $valor;
        if (str_ends_with($campo, 'Dni')) {
            $texto = DniInput::digitsOnly($texto);
        }

        $this->{$campo} = $texto;
    }
}
