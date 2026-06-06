<?php

namespace App\Livewire\Certificados;

use App\Support\Certificados\CertificadoEstudiosTramite;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Constancia de certificado de estudios en trámite — listado y emisión.
 */
class CertificadoEstudiosTramiteIndex extends Component
{
    use WithPagination;

    public string $buscar = '';

    public bool $modalAbierto = false;

    public ?int $idLegajosModal = null;

    public string $alumnoModalEtiqueta = '';

    public string $dniModal = '';

    public string $mateAdeud = '';

    public string $idiomaCursado = '';

    public string $preAnte = '';

    public string $fechaEmision = '';

    public bool $guardarAlEmitir = true;

    /** @var array<string, array{except?: mixed}> */
    protected $queryString = [
        'buscar' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(tienePermiso(18), 403, 'Sin permiso para constancias de certificado en trámite.');

        $ctx = schoolCtx();
        if ($ctx->idNivel < 1 || $ctx->idTerlec < 1) {
            abort(403, 'Seleccione nivel y ciclo lectivo en el contexto activo.');
        }
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function abrirModal(int $idLegajos): void
    {
        $ctx = schoolCtx();
        $alumno = CertificadoEstudiosTramite::alumnoMatriculado(
            $idLegajos,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
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

        $guardado = CertificadoEstudiosTramite::datosGuardados($idLegajos);
        $defaults = $guardado ?? CertificadoEstudiosTramite::valoresPorDefecto();

        $this->mateAdeud = $defaults['mateAdeud'];
        $this->idiomaCursado = $defaults['idiomaCursado'];
        $this->preAnte = $defaults['preAnte'];
        $this->fechaEmision = $defaults['fechaEmision'];
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

        session()->flash('success', 'Datos guardados en el legajo.');
    }

    public function emitirPdf(): void
    {
        $validated = $this->validarFormulario();
        if ($validated === null || $this->idLegajosModal === null) {
            return;
        }

        if ($this->guardarAlEmitir) {
            CertificadoEstudiosTramite::guardar($this->idLegajosModal, $validated);
        }

        $this->dispatch('abrir-pdf-post', ...CertificadoEstudiosTramite::pdfPost($this->idLegajosModal, $validated));
    }

    public function render()
    {
        $ctx = schoolCtx();
        $alumnos = CertificadoEstudiosTramite::paginarAlumnos(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            $this->buscar,
            50,
        );

        return view('livewire.certificados.certificado-estudios-tramite-index', [
            'alumnos' => $alumnos,
            'anoLectivo' => (int) ($ctx->terlecAno() ?? 0),
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Constancia de Certificado en Trámite']);
    }

    /**
     * @return array{
     *     mateAdeud: string,
     *     idiomaCursado: string,
     *     preAnte: string,
     *     fechaEmision: string
     * }|null
     */
    private function validarFormulario(): ?array
    {
        if ($this->idLegajosModal === null) {
            return null;
        }

        $validated = $this->validate(
            CertificadoEstudiosTramite::reglasFormulario(),
            CertificadoEstudiosTramite::mensajesValidacion(),
        );

        $validated['mateAdeud'] = trim((string) ($validated['mateAdeud'] ?? ''));
        $validated['idiomaCursado'] = trim((string) $validated['idiomaCursado']);
        $validated['preAnte'] = trim((string) $validated['preAnte']);

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

        return CertificadoEstudiosTramite::guardar($this->idLegajosModal, $validated);
    }

    private function ejecutarConLimite(): bool
    {
        $key = 'certificados:guardar-tram:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('guardar', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }
}
