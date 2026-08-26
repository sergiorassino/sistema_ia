<?php

namespace App\Livewire\Certificados;

use App\Support\Certificados\ConstanciaDocumentos;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Constancia de documentos — listado y emisión.
 */
class ConstanciaDocumentosIndex extends Component
{
    use WithPagination;

    public string $buscar = '';

    public bool $modalAbierto = false;

    public ?int $idLegajosModal = null;

    public string $alumnoModalEtiqueta = '';

    public string $dniModal = '';

    public string $certifde = '';

    public string $otorpor = '';

    public string $fechotor = '';

    public string $parnacop = '';

    public string $parapre = '';

    public string $fechemis = '';

    public bool $guardarAlEmitir = true;

    /** @var array<string, array{except?: mixed}> */
    protected $queryString = [
        'buscar' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(tienePermiso(19), 403, 'Sin permiso para constancias de documentos.');

        $ctx = schoolCtx();
        if ($ctx->idNivel < 1) {
            abort(403, 'Seleccione nivel en el contexto activo.');
        }
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function abrirModal(int $idLegajos): void
    {
        $ctx = schoolCtx();
        $alumno = ConstanciaDocumentos::alumnoDelNivel(
            $idLegajos,
            (int) $ctx->idNivel,
        );

        if ($alumno === null) {
            return;
        }

        $this->idLegajosModal = $idLegajos;
        $this->alumnoModalEtiqueta = trim($alumno['apellido'].' '.$alumno['nombre']);
        $this->dniModal = trim($alumno['dni']);
        if ($this->dniModal !== '') {
            $this->alumnoModalEtiqueta .= ' — DNI '.$this->dniModal;
        }

        $guardado = ConstanciaDocumentos::datosGuardados($idLegajos);
        $defaults = $guardado ?? ConstanciaDocumentos::valoresPorDefecto();

        $this->certifde = $defaults['certifde'];
        $this->otorpor = $defaults['otorpor'];
        $this->fechotor = $defaults['fechotor'];
        $this->parnacop = $defaults['parnacop'];
        $this->parapre = $defaults['parapre'];
        $this->fechemis = $defaults['fechemis'];
        $this->guardarAlEmitir = true;

        $this->resetValidation();
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->idLegajosModal = null;
        $this->alumnoModalEtiqueta = '';
        $this->dniModal = '';
        $this->resetValidation();
    }

    public function guardarDatos(): void
    {
        if (! $this->persistirDatos()) {
            return;
        }

        session()->flash('success', 'Datos guardados en constdocu.');
    }

    public function emitirPdf(): void
    {
        $validated = $this->validarFormulario();
        if ($validated === null || $this->idLegajosModal === null) {
            return;
        }

        if ($this->guardarAlEmitir) {
            ConstanciaDocumentos::guardar($this->idLegajosModal, $validated);
        }

        $this->dispatch('abrir-pdf-post', ...ConstanciaDocumentos::pdfPost($this->idLegajosModal, $validated));
    }

    public function render()
    {
        $ctx = schoolCtx();
        $alumnos = ConstanciaDocumentos::paginarAlumnos(
            (int) $ctx->idNivel,
            $this->buscar,
            50,
        );

        return view('livewire.certificados.constancia-documentos-index', [
            'alumnos' => $alumnos,
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Constancia de Documentos']);
    }

    /**
     * @return array{
     *     certifde: string,
     *     otorpor: string,
     *     fechotor: string,
     *     parnacop: string,
     *     parapre: string,
     *     fechemis: string
     * }|null
     */
    private function validarFormulario(): ?array
    {
        if ($this->idLegajosModal === null) {
            return null;
        }

        $validated = $this->validate(
            ConstanciaDocumentos::reglasFormulario(),
            ConstanciaDocumentos::mensajesValidacion(),
        );

        $validated['certifde'] = trim((string) $validated['certifde']);
        $validated['otorpor'] = trim((string) $validated['otorpor']);
        $validated['parnacop'] = trim((string) $validated['parnacop']);
        $validated['parapre'] = trim((string) $validated['parapre']);

        return $validated;
    }

    private function persistirDatos(): bool
    {
        $validated = $this->validarFormulario();
        if ($validated === null || $this->idLegajosModal === null) {
            return false;
        }

        if (! $this->ejecutarConLimite()) {
            return false;
        }

        return ConstanciaDocumentos::guardar($this->idLegajosModal, $validated);
    }

    private function ejecutarConLimite(): bool
    {
        $key = 'certificados:guardar-constdocu:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('guardar', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }
}
