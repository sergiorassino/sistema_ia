<?php

namespace App\Livewire\Certificados;

use App\Support\Certificados\CertificadoAlumnoRegular;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Certificado de alumno/a regular — listado y emisión.
 */
class CertificadoAlumnoRegularIndex extends Component
{
    use WithPagination;

    public string $buscar = '';

    public bool $modalAbierto = false;

    public ?int $idLegajosModal = null;

    public string $alumnoModalEtiqueta = '';

    public int $iniFin = CertificadoAlumnoRegular::INI_FIN_INICIO;

    public string $fechIniFin = '';

    public string $prePor = '';

    public string $prePorDni = '';

    public string $preAnte = '';

    public string $fechaEmision = '';

    public bool $guardarAlEmitir = true;

    /** @var array<string, array{except?: mixed}> */
    protected $queryString = [
        'buscar' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(tienePermiso(17), 403, 'Sin permiso para certificados de alumno regular.');

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
        $alumno = CertificadoAlumnoRegular::alumnoMatriculado(
            $idLegajos,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );

        if ($alumno === null) {
            return;
        }

        $this->idLegajosModal = $idLegajos;
        $this->alumnoModalEtiqueta = trim($alumno['apellido'].' '.$alumno['nombre']);
        if ($alumno['dni'] !== '') {
            $this->alumnoModalEtiqueta .= ' — DNI '.$alumno['dni'];
        }

        $guardado = CertificadoAlumnoRegular::ultimoGuardado($idLegajos);
        $defaults = $guardado ?? CertificadoAlumnoRegular::valoresPorDefecto();

        $this->iniFin = (int) ($defaults['iniFin'] ?? CertificadoAlumnoRegular::INI_FIN_INICIO);
        $this->fechIniFin = $defaults['fechIniFin'];
        $this->prePor = $defaults['prePor'];
        $this->prePorDni = $defaults['prePorDni'];
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
        $this->resetValidation();
    }

    public function guardarDatos(): void
    {
        if (! $this->persistirDatos()) {
            return;
        }

        session()->flash('success', 'Datos del certificado guardados.');
    }

    public function emitirPdf(): void
    {
        $validated = $this->validarFormulario();
        if ($validated === null || $this->idLegajosModal === null) {
            return;
        }

        if ($this->guardarAlEmitir) {
            CertificadoAlumnoRegular::guardar($this->idLegajosModal, $validated);
        }

        $this->dispatch('abrir-pdf-post', ...CertificadoAlumnoRegular::pdfPost($this->idLegajosModal, $validated));
    }

    public function render()
    {
        $ctx = schoolCtx();
        $alumnos = CertificadoAlumnoRegular::paginarAlumnos(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            $this->buscar,
            50,
        );

        return view('livewire.certificados.certificado-alumno-regular-index', [
            'alumnos' => $alumnos,
            'anoLectivo' => (int) ($ctx->terlecAno() ?? 0),
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Constancia de Alumno Regular']);
    }

    /**
     * @return array{
     *     iniFin: int,
     *     fechIniFin: string,
     *     prePor: string,
     *     prePorDni: string,
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
            CertificadoAlumnoRegular::reglasFormulario(),
            CertificadoAlumnoRegular::mensajesValidacion(),
        );

        $validated['iniFin'] = (int) $validated['iniFin'];
        $validated['prePor'] = trim($validated['prePor']);
        $validated['prePorDni'] = trim($validated['prePorDni']);
        $validated['preAnte'] = trim($validated['preAnte']);

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

        return CertificadoAlumnoRegular::guardar($this->idLegajosModal, $validated);
    }

    private function ejecutarConLimite(): bool
    {
        $key = 'certificados:guardar:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('guardar', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }
}
