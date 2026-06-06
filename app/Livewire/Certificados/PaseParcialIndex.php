<?php

namespace App\Livewire\Certificados;

use App\Support\Certificados\PaseParcial;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pase parcial — listado de legajos de nivel medio y emisión de solicitud.
 */
class PaseParcialIndex extends Component
{
    use WithPagination;

    public string $buscar = '';

    public bool $modalAbierto = false;

    public ?int $idLegajosModal = null;

    public string $alumnoModalEtiqueta = '';

    public string $fecha = '';

    public string $destino = '';

    public bool $guardarAlEmitir = true;

    /** @var array<string, array{except?: mixed}> */
    protected $queryString = [
        'buscar' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(tienePermiso(21), 403, 'Sin permiso para pase parcial.');

        $ctx = schoolCtx();
        if ($ctx->idTerlec < 1) {
            abort(403, 'Seleccione nivel y ciclo lectivo en el contexto activo.');
        }
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function abrirModal(int $idLegajos): void
    {
        $alumno = PaseParcial::alumnoElegible($idLegajos);

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

        $guardado = PaseParcial::datosGuardados($idLegajos);
        $defaults = $guardado ?? PaseParcial::valoresPorDefecto();

        $this->fecha = $defaults['fecha'];
        $this->destino = $defaults['destino'];
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

        session()->flash('success', 'Datos de la solicitud guardados.');
    }

    public function emitirPdf(): void
    {
        $validated = $this->validarFormulario();
        if ($validated === null || $this->idLegajosModal === null) {
            return;
        }

        if ($this->guardarAlEmitir) {
            PaseParcial::guardar($this->idLegajosModal, $validated);
        }

        $this->dispatch('abrir-pdf-post', ...PaseParcial::pdfPost($this->idLegajosModal, $validated));
    }

    public function render()
    {
        $alumnos = PaseParcial::paginarAlumnos($this->buscar, 50);

        return view('livewire.certificados.pase-parcial-index', [
            'alumnos' => $alumnos,
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Pase Parcial']);
    }

    /**
     * @return array{fecha: string, destino: string}|null
     */
    private function validarFormulario(): ?array
    {
        if ($this->idLegajosModal === null) {
            return null;
        }

        $validated = $this->validate(
            PaseParcial::reglasFormulario(),
            PaseParcial::mensajesValidacion(),
        );

        $validated['destino'] = trim($validated['destino']);

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

        return PaseParcial::guardar($this->idLegajosModal, $validated);
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
