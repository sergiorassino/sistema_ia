<?php

namespace App\Livewire\CalificacionesInicial;

use App\Livewire\Concerns\BloqueoEntradaCargaNotasOffSecretaria;
use App\Support\CalificacionesInicial\CalificacionesInicialObservacionesDatos;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\CalificacionesInicialPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Carga de observaciones por alumno y espacio curricular (etapas 1 y 2).
 */
class CargaObservacionesInicialForm extends Component
{
    use BloqueoEntradaCargaNotasOffSecretaria;

    public bool $modoPortalDocente = false;

    public int $idMateria;

    public int $idMatricula;

    public string $alumnoLinea = '';

    public string $materiaNombre = '';

    public string $cursoLabel = '';

    /**
     * Texto de observación por etapa (clave = 1, 2).
     *
     * @var array<int, string>
     */
    public array $observacionesPorEtapa = [];

    /**
     * Indicadores de la materia por etapa (solo lectura en la vista).
     *
     * @var array<int, list<array{ord: int, indicador: string}>>
     */
    public array $indicadoresPorEtapa = [];

    public function mount(int $materia, int $matricula): void
    {
        $this->modoPortalDocente = CalificacionesInicialPortalDocente::esPortalDocente();

        if ($this->modoPortalDocente) {
            CalificacionesInicialPortalDocente::abortSiMenuInactivo(CalificacionesInicialPortalDocente::MENU_OBSERVACIONES);
        } else {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para calificaciones.',
            );
        }

        CalificacionesInicialPortalDocente::abortSiNoEsInicial();
        CalificacionesInicialObservacionesDatos::abortSiColumnasInexistentes();

        $this->redirigirSiSecretariaCargaNotasOff($this->modoPortalDocente);

        $ctx = schoolCtx();
        $mat = CalificacionesInicialObservacionesDatos::materiaEnContexto(
            $materia,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );
        abort_if($mat === null, 404);

        if ($this->modoPortalDocente) {
            CalificacionesInicialPortalDocente::abortSiProfesorSinMateria(
                (int) $mat->id,
                (int) $mat->idCursos,
            );
        }

        $matriculaModel = CalificacionesInicialObservacionesDatos::matriculaEnCursoDeMateria(
            $matricula,
            (int) $mat->idCursos,
        );
        abort_if($matriculaModel === null, 404);

        if ($this->modoPortalDocente) {
            CalificacionesInicialPortalDocente::abortSiProfesorSinMatricula((int) $matriculaModel->id);
        }

        $this->idMateria = (int) $mat->id;
        $this->idMatricula = (int) $matriculaModel->id;
        $this->cargarDesdeBd($matriculaModel, $mat);
    }

    protected function cargarDesdeBd(\App\Models\Matricula $matricula, object $materia): void
    {
        $data = CalificacionesInicialObservacionesDatos::cargarFormulario($matricula, $materia);
        $this->alumnoLinea = $data['alumnoLinea'];
        $this->materiaNombre = $data['materiaNombre'];
        $this->cursoLabel = $data['cursoLabel'];
        $this->observacionesPorEtapa = $data['observaciones'];
        $this->indicadoresPorEtapa = $data['indicadores'];
    }

    public function guardar(): void
    {
        if ($this->secretariaCargaNotasOffBloqueaAccion($this->modoPortalDocente)) {
            return;
        }

        if (! $this->modoPortalDocente) {
            PortalDocenteContext::abortSiStaffSinPermisoIa(PermisosIaCatalog::CALIF_CARGA);
        }

        $key = 'calif-inicial-obs:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $max = CalificacionesInicialObservacionesDatos::MAX_CARACTERES;
        $rules = [];
        foreach (CalificacionesInicialObservacionesDatos::etapasCarga() as $etapa) {
            $rules["observacionesPorEtapa.{$etapa}"] = ['nullable', 'string', 'max:'.$max];
        }
        $this->validate($rules, [
            'observacionesPorEtapa.*.max' => 'Cada observación no puede superar '.$max.' caracteres.',
        ]);

        $ctx = schoolCtx();
        $materia = CalificacionesInicialObservacionesDatos::materiaEnContexto(
            $this->idMateria,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );
        if ($materia === null) {
            abort(404);
        }

        $matricula = CalificacionesInicialObservacionesDatos::matriculaEnCursoDeMateria(
            $this->idMatricula,
            (int) $materia->idCursos,
        );
        if ($matricula === null) {
            abort(404);
        }

        try {
            CalificacionesInicialObservacionesDatos::guardar($matricula, $materia, $this->observacionesPorEtapa);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($e->getStatusCode() === 422) {
                $this->dispatch('se-swal-error', mensaje: $e->getMessage());

                return;
            }
            report($e);
            $this->dispatch('se-swal-error', mensaje: 'No se pudieron guardar las observaciones. Verifique los datos e intente nuevamente.');

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('se-swal-error', mensaje: 'No se pudieron guardar las observaciones. Verifique los datos e intente nuevamente.');

            return;
        }

        session()->flash('success', 'Observaciones guardadas correctamente.');

        $this->redirect(
            CalificacionesInicialPortalDocente::route('observaciones.alumnos', ['materia' => $this->idMateria]),
            navigate: true,
        );
    }

    public function render()
    {
        return view('livewire.calificaciones-inicial.carga-observaciones-inicial-form', [
            'etapas' => CalificacionesInicialObservacionesDatos::etapasCarga(),
        ])->layout(CalificacionesInicialPortalDocente::layout(), ['pageTitle' => 'Carga de observaciones']);
    }
}
