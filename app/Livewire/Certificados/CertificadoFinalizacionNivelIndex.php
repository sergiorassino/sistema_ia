<?php

namespace App\Livewire\Certificados;

use App\Support\Certificados\CertificadoFinalizacionNivel;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Certificado Jardín / Certificado Sexto Grado: cursos → alumnos → datos comunes → PDF.
 */
class CertificadoFinalizacionNivelIndex extends Component
{
    public string $tipo = CertificadoFinalizacionNivel::TIPO_SEXTO;

    public string $paso = CertificadoFinalizacionNivel::PASO_CURSOS;

    public ?int $cursoId = null;

    /** @var list<string> */
    public array $matriculasSeleccionadas = [];

    public string $serie = '';

    public string $mesApro = '';

    public string $anoApro = '';

    public string $diaEmision = '';

    public string $mesEmision = '';

    public string $anoEmision = '';

    public string $ppi = '';

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CERT_JARDIN_SEXTO_GRADO), 403);

        $tipo = CertificadoFinalizacionNivel::tipoDesdeRuta(request()->route()?->getName());
        abort_unless($tipo !== null, 404);

        $this->tipo = $tipo;
        CertificadoFinalizacionNivel::abortSiNivelIncorrecto($this->tipo);

        $ctx = schoolCtx();
        if ($ctx->idNivel < 1 || $ctx->idTerlec < 1) {
            abort(403, 'Seleccione nivel y ciclo lectivo en el contexto activo.');
        }
    }

    public function elegirCurso(int $cursoId): void
    {
        if (! CertificadoFinalizacionNivel::cursoImplicadoValido($this->tipo, $cursoId)) {
            return;
        }

        $this->cursoId = $cursoId;
        $this->matriculasSeleccionadas = [];
        $this->paso = CertificadoFinalizacionNivel::PASO_ALUMNOS;
        $this->resetValidation();
    }

    public function volverACursos(): void
    {
        $this->paso = CertificadoFinalizacionNivel::PASO_CURSOS;
    }

    public function volverAAlumnos(): void
    {
        $this->paso = CertificadoFinalizacionNivel::PASO_ALUMNOS;
    }

    public function continuarAFormulario(): void
    {
        $this->normalizarMatriculasSeleccionadas();
        if (! $this->puedeContinuarAlumnos()) {
            $this->addError('matriculasSeleccionadas', 'Seleccione al menos un estudiante.');

            return;
        }

        $datos = CertificadoFinalizacionNivel::datosComunes($this->tipo);
        $this->serie = $datos['serie'];
        $this->mesApro = $datos['mesApro'];
        $this->anoApro = $datos['anoApro'];
        $this->diaEmision = $datos['diaEmision'];
        $this->mesEmision = $datos['mesEmision'];
        $this->anoEmision = $datos['anoEmision'];
        $this->ppi = $datos['ppi'];
        $this->resetValidation();
        $this->paso = CertificadoFinalizacionNivel::PASO_FORMULARIO;
    }

    public function updatedMatriculasSeleccionadas(): void
    {
        $this->normalizarMatriculasSeleccionadas();
    }

    public function seleccionarTodasMatriculas(): void
    {
        $this->matriculasSeleccionadas = $this->matriculasDelCurso()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function quitarTodasMatriculas(): void
    {
        $this->matriculasSeleccionadas = [];
    }

    public function toggleSeleccionTodas(): void
    {
        if ($this->todasLasMatriculasMarcadas()) {
            $this->quitarTodasMatriculas();
        } else {
            $this->seleccionarTodasMatriculas();
        }
    }

    public function todasLasMatriculasMarcadas(): bool
    {
        $permitidos = $this->matriculasDelCurso()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->sort()
            ->values();

        if ($permitidos->isEmpty()) {
            return false;
        }

        $marcados = collect($this->matriculasSeleccionadas)
            ->map(fn ($v) => (string) $v)
            ->filter(fn ($v) => $v !== '')
            ->sort()
            ->values();

        return $marcados->all() === $permitidos->all();
    }

    public function puedeContinuarAlumnos(): bool
    {
        return collect($this->matriculasSeleccionadas)
            ->filter(fn ($v) => (int) $v > 0)
            ->isNotEmpty();
    }

    public function guardarDatos(): void
    {
        $validated = $this->validarFormulario();
        if ($validated === null) {
            return;
        }

        if (! $this->ejecutarConLimite('guardar')) {
            return;
        }

        $res = CertificadoFinalizacionNivel::guardarDatosComunes($this->tipo, $validated);
        if (! ($res['ok'] ?? false)) {
            $this->addError('guardar', $res['error'] ?? 'No se pudieron guardar los datos.');
            $this->dispatch('se-swal-error', mensaje: $res['error'] ?? 'No se pudieron guardar los datos.');

            return;
        }

        $this->dispatch('se-swal-exito', mensaje: 'Datos del certificado guardados.');
    }

    public function imprimir(): void
    {
        $validated = $this->validarFormulario();
        if ($validated === null || $this->cursoId === null) {
            return;
        }

        $this->normalizarMatriculasSeleccionadas();
        $ids = collect($this->matriculasSeleccionadas)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === [] || count($ids) > CertificadoFinalizacionNivel::MAX_MATRICULAS) {
            $this->addError('matriculasSeleccionadas', 'Seleccione entre 1 y '.CertificadoFinalizacionNivel::MAX_MATRICULAS.' estudiantes.');

            return;
        }

        if (! $this->ejecutarConLimite('imprimir')) {
            return;
        }

        $res = CertificadoFinalizacionNivel::guardarDatosComunes($this->tipo, $validated);
        if (! ($res['ok'] ?? false)) {
            $this->addError('guardar', $res['error'] ?? 'No se pudieron guardar los datos.');
            $this->dispatch('se-swal-error', mensaje: $res['error'] ?? 'No se pudieron guardar los datos.');

            return;
        }

        $this->dispatch(
            'abrir-pdf-post',
            ...CertificadoFinalizacionNivel::pdfPost($this->tipo, (int) $this->cursoId, $ids, $validated)
        );
    }

    public function render()
    {
        $cursos = CertificadoFinalizacionNivel::cursosImplicados($this->tipo);
        $matriculas = $this->matriculasDelCurso();
        $cantidadSeleccionados = collect($this->matriculasSeleccionadas)
            ->filter(fn ($v) => (int) $v > 0)
            ->count();

        $cursoNombre = '';
        if ($this->cursoId) {
            $curso = $cursos->first(fn ($c) => (int) $c->Id === (int) $this->cursoId);
            $cursoNombre = $curso?->nombreParaListado() ?? '';
        }

        return view('livewire.certificados.certificado-finalizacion-nivel-index', [
            'cursos' => $cursos,
            'matriculas' => $matriculas,
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'todasMarcadas' => $this->todasLasMatriculasMarcadas(),
            'hayMatriculas' => $matriculas->isNotEmpty(),
            'titulo' => CertificadoFinalizacionNivel::titulo($this->tipo),
            'cursoNombre' => $cursoNombre,
            'etiquetaCursos' => $this->tipo === CertificadoFinalizacionNivel::TIPO_JARDIN
                ? 'Salas de 5 años'
                : 'Sextos grados',
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => CertificadoFinalizacionNivel::titulo($this->tipo)]);
    }

    /**
     * @return Collection<int, \App\Models\Matricula>
     */
    public function matriculasDelCurso(): Collection
    {
        if (! $this->cursoId) {
            return collect();
        }

        return CertificadoFinalizacionNivel::matriculasDelCurso($this->tipo, (int) $this->cursoId);
    }

    protected function normalizarMatriculasSeleccionadas(): void
    {
        $allowed = $this->matriculasDelCurso()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->matriculasSeleccionadas = collect($this->matriculasSeleccionadas)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0 && in_array($id, $allowed, true))
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * @return array{
     *     serie: string,
     *     mesApro: string,
     *     anoApro: string,
     *     diaEmision: string,
     *     mesEmision: string,
     *     anoEmision: string,
     *     ppi: string
     * }|null
     */
    private function validarFormulario(): ?array
    {
        $validated = $this->validate(
            CertificadoFinalizacionNivel::reglasFormulario(),
            CertificadoFinalizacionNivel::mensajesValidacion(),
        );

        return [
            'serie' => trim((string) ($validated['serie'] ?? '')),
            'mesApro' => trim((string) $validated['mesApro']),
            'anoApro' => trim((string) $validated['anoApro']),
            'diaEmision' => trim((string) $validated['diaEmision']),
            'mesEmision' => trim((string) $validated['mesEmision']),
            'anoEmision' => trim((string) $validated['anoEmision']),
            'ppi' => trim((string) ($validated['ppi'] ?? '')),
        ];
    }

    private function ejecutarConLimite(string $accion): bool
    {
        $key = 'cert-finalizacion:'.$accion.':'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('guardar', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }
}
