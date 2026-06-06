<?php

namespace App\Livewire\Certificados;

use App\Support\Certificados\SolicitudDePase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Solicitud de pase — listado de legajos de nivel medio y emisión de certificado.
 */
class SolicitudDePaseIndex extends Component
{
    use WithPagination;

    public string $buscar = '';

    public bool $modalAbierto = false;

    public ?int $idLegajosModal = null;

    public string $alumnoModalEtiqueta = '';

    public string $fechaEmision = '';

    public string $cursosCompletos = '';

    public string $mateAdeud = '';

    public string $cursar = '';

    public string $preAnte = '';

    public bool $guardarAlEmitir = true;

    /** @var array<string, array{except?: mixed}> */
    protected $queryString = [
        'buscar' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(tienePermiso(22), 403, 'Sin permiso para solicitud de pase.');
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function abrirModal(int $idLegajos): void
    {
        $alumno = SolicitudDePase::alumnoElegible($idLegajos);

        if ($alumno === null) {
            return;
        }

        $this->idLegajosModal = $idLegajos;
        $this->alumnoModalEtiqueta = trim($alumno['apellido'].' '.$alumno['nombre']);
        if ($alumno['dni'] !== '') {
            $this->alumnoModalEtiqueta .= ' — DNI '.$alumno['dni'];
        }
        if ($alumno['curso'] !== '') {
            $this->alumnoModalEtiqueta .= ' · '.$alumno['curso'];
        }

        $guardado = SolicitudDePase::datosGuardados($idLegajos);
        $defaults = $guardado ?? SolicitudDePase::valoresPorDefecto();

        $this->fechaEmision = $defaults['fechaEmision'];
        $this->cursosCompletos = $defaults['cursosCompletos'];
        $this->mateAdeud = $defaults['mateAdeud'];
        $this->cursar = $defaults['cursar'];
        $this->preAnte = $defaults['preAnte'];
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

        session()->flash('success', 'Datos guardados en paseprovisorio.');
    }

    public function emitirPdf(): void
    {
        $validated = $this->validarFormulario();
        if ($validated === null || $this->idLegajosModal === null) {
            return;
        }

        if ($this->guardarAlEmitir) {
            SolicitudDePase::guardar($this->idLegajosModal, $validated);
        }

        $this->dispatch('abrir-pdf-post', ...SolicitudDePase::pdfPost($this->idLegajosModal, $validated));
    }

    public function render()
    {
        $alumnos = SolicitudDePase::paginarAlumnos($this->buscar, 50);

        return view('livewire.certificados.solicitud-de-pase-index', [
            'alumnos' => $alumnos,
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Solicitud de Pase']);
    }

    /**
     * @return array{
     *     fechaEmision: string,
     *     cursosCompletos: string,
     *     mateAdeud: string,
     *     cursar: string,
     *     preAnte: string
     * }|null
     */
    private function validarFormulario(): ?array
    {
        if ($this->idLegajosModal === null) {
            return null;
        }

        $validated = $this->validate(
            SolicitudDePase::reglasFormulario(),
            SolicitudDePase::mensajesValidacion(),
        );

        $validated['cursosCompletos'] = trim((string) ($validated['cursosCompletos'] ?? ''));
        $validated['mateAdeud'] = trim((string) ($validated['mateAdeud'] ?? ''));
        $validated['cursar'] = trim((string) ($validated['cursar'] ?? ''));
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

        return SolicitudDePase::guardar($this->idLegajosModal, $validated);
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
