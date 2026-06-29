<?php

namespace App\Livewire\CalificacionesInicial\Sfq;

use App\Support\CalificacionesInicial\CalificacionesInicialModulos;
use App\Support\CalificacionesInicial\Sfq\CalificacionesInicialSfqCatalogo;
use App\Support\CalificacionesInicial\Sfq\CalificacionesInicialSfqDatos;
use App\Support\PortalDocente\CalificacionesInicialSfqPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * SFQ — observaciones pedagógicas (obs01–03) y Bellas Artes (baObs01–03).
 */
class CargaCalificacionesInicialSfqObservacionesForm extends Component
{
    public int $idMatricula;

    public int $cursoId;

    public string $alumnoLinea = '';

    public string $obs01 = '';

    public string $obs02 = '';

    public string $obs03 = '';

    public string $baObs01 = '';

    public string $baObs02 = '';

    public string $baObs03 = '';

    public bool $modoPortalDocente = false;

    public function mount(int $matricula): void
    {
        CalificacionesInicialModulos::abortSiImplementacionInactiva(
            CalificacionesInicialModulos::CARGA_NOTAS,
            CalificacionesInicialSfqCatalogo::IMPLEMENTACION,
        );

        CalificacionesInicialSfqDatos::abortSiEsquemaIncompleto();
        CalificacionesInicialSfqDatos::abortSiObservacionesBaInexistentes();

        $this->modoPortalDocente = CalificacionesInicialSfqPortalDocente::esPortalDocente();

        if ($this->modoPortalDocente) {
            CalificacionesInicialSfqPortalDocente::abortSiMenuInactivo();
        } else {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                \App\Support\PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para cargar calificaciones.',
            );
        }

        CalificacionesInicialSfqPortalDocente::abortSiNoEsInicial();

        $mat = CalificacionesInicialSfqDatos::matriculaEnContexto($matricula);
        abort_if($mat === null, 404);

        if ($this->modoPortalDocente) {
            CalificacionesInicialSfqPortalDocente::abortSiProfesorSinMatricula($matricula);
        }

        $this->idMatricula = (int) $mat->id;
        $this->cursoId = (int) $mat->idCursos;

        $data = CalificacionesInicialSfqDatos::cargarFormularioObservaciones($mat);
        $this->alumnoLinea = $data['alumnoLinea'];
        $this->obs01 = $data['obs01'];
        $this->obs02 = $data['obs02'];
        $this->obs03 = $data['obs03'];
        $this->baObs01 = $data['baObs01'];
        $this->baObs02 = $data['baObs02'];
        $this->baObs03 = $data['baObs03'];
    }

    #[Renderless]
    public function guardarCampo(string $campo, mixed $value): void
    {
        PortalDocenteContext::abortSiStaffSinPermisoIa(\App\Support\PermisosIaCatalog::CALIF_CARGA);

        $key = 'calificacionesInicialSfq:obs:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 120)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $camposPermitidos = array_merge(
            CalificacionesInicialSfqCatalogo::CAMPOS_OBS_PEDAG,
            CalificacionesInicialSfqCatalogo::CAMPOS_OBS_BA,
        );
        if (! in_array($campo, $camposPermitidos, true)) {
            abort(400);
        }

        $value = is_string($value) ? trim($value) : (string) ($value ?? '');

        $mat = CalificacionesInicialSfqDatos::matriculaEnContexto($this->idMatricula);
        if ($mat === null) {
            abort(404);
        }

        if ($this->modoPortalDocente) {
            CalificacionesInicialSfqPortalDocente::abortSiProfesorSinMatricula($this->idMatricula);
        }

        CalificacionesInicialSfqDatos::guardarObservacionCampo($mat, $campo, $value);

        if (property_exists($this, $campo)) {
            $this->{$campo} = $value;
        }
    }

    public function render()
    {
        return view('livewire.calificaciones-inicial.sfq.observaciones-form', [
            'maxCaracteres' => CalificacionesInicialSfqCatalogo::MAX_OBS_CARACTERES,
        ])->layout(CalificacionesInicialSfqPortalDocente::layout(), [
            'pageTitle' => 'Observaciones (Inicial SFQ)',
        ]);
    }
}
