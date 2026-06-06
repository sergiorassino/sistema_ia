<?php

namespace App\Livewire\Certificados;

use App\Support\Certificados\CertificadoAsistenciaProfesor;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Certificado de asistencia del profesor — listado y emisión.
 */
class CertificadoAsistenciaProfesorIndex extends Component
{
    use WithPagination;

    public string $buscar = '';

    public bool $modalAbierto = false;

    public ?int $idProfesoresModal = null;

    public string $profesorModalEtiqueta = '';

    public string $fecha = '';

    public string $texto = '';

    public string $parapre = '';

    public bool $guardarAlEmitir = true;

    /** @var array<string, array{except?: mixed}> */
    protected $queryString = [
        'buscar' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(tienePermiso(20), 403, 'Sin permiso para certificados de asistencia del profesor.');
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function abrirModal(int $idProfesores): void
    {
        $profesor = CertificadoAsistenciaProfesor::profesorElegible($idProfesores);

        if ($profesor === null) {
            return;
        }

        $this->idProfesoresModal = $idProfesores;
        $this->profesorModalEtiqueta = trim($profesor['apellido'].' '.$profesor['nombre']);
        if ($profesor['dni'] !== '') {
            $this->profesorModalEtiqueta .= ' — DNI '.$profesor['dni'];
        }
        if ($profesor['rol'] !== '') {
            $this->profesorModalEtiqueta .= ' · '.$profesor['rol'];
        }

        $guardado = CertificadoAsistenciaProfesor::datosGuardados($idProfesores);
        $defaults = $guardado ?? CertificadoAsistenciaProfesor::valoresPorDefecto();

        $this->fecha = $defaults['fecha'];
        $this->texto = $defaults['texto'];
        $this->parapre = $defaults['parapre'];
        $this->guardarAlEmitir = true;

        $this->resetValidation();
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->idProfesoresModal = null;
        $this->profesorModalEtiqueta = '';
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
        if ($validated === null || $this->idProfesoresModal === null) {
            return;
        }

        if ($this->guardarAlEmitir) {
            CertificadoAsistenciaProfesor::guardar($this->idProfesoresModal, $validated);
        }

        $this->dispatch('abrir-pdf-post', ...CertificadoAsistenciaProfesor::pdfPost($this->idProfesoresModal, $validated));
    }

    public function render()
    {
        $profesores = CertificadoAsistenciaProfesor::paginarProfesores($this->buscar, 50);

        return view('livewire.certificados.certificado-asistencia-profesor-index', [
            'profesores' => $profesores,
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Certificado de Asistencia del Profesor']);
    }

    /**
     * @return array{
     *     fecha: string,
     *     texto: string,
     *     parapre: string
     * }|null
     */
    private function validarFormulario(): ?array
    {
        if ($this->idProfesoresModal === null) {
            return null;
        }

        $validated = $this->validate(
            CertificadoAsistenciaProfesor::reglasFormulario(),
            CertificadoAsistenciaProfesor::mensajesValidacion(),
        );

        $validated['texto'] = trim($validated['texto']);
        $validated['parapre'] = trim($validated['parapre']);

        return $validated;
    }

    private function persistirDatos(): bool
    {
        $validated = $this->validarFormulario();
        if ($validated === null || $this->idProfesoresModal === null) {
            return false;
        }

        if (! $this->ejecutarConLimite()) {
            return false;
        }

        return CertificadoAsistenciaProfesor::guardar($this->idProfesoresModal, $validated);
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
