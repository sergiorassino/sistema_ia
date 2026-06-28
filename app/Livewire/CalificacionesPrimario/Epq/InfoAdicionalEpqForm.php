<?php

namespace App\Livewire\CalificacionesPrimario\Epq;

use App\Support\CalificacionesPrimario\CalificacionesPrimarioDatos;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;
use App\Support\CalificacionesPrimario\Epq\CalificacionesEpqCatalogo;
use App\Support\CalificacionesPrimario\Epq\CalificacionesEpqDatos;
use App\Support\PortalDocente\CalificacionesPrimarioPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * EPQ — información adicional de matrícula (md01–md37).
 */
class InfoAdicionalEpqForm extends Component
{
    public int $idMatricula;

    public int $cursoId;

    public string $alumnoLinea = '';

    public string $cursoLabel = '';

    /** @var array<string, string> */
    public array $campos = [];

    public bool $modoPortalDocente = false;

    public function mount(int $matricula): void
    {
        CalificacionesPrimarioModulos::abortSiImplementacionInactiva(
            CalificacionesPrimarioModulos::CARGA_ESTUDIANTE,
            CalificacionesEpqCatalogo::IMPLEMENTACION,
        );

        $this->modoPortalDocente = CalificacionesPrimarioPortalDocente::esPortalDocente();

        if (! $this->modoPortalDocente) {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                \App\Support\PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para cargar calificaciones.',
            );
        }

        CalificacionesPrimarioPortalDocente::abortSiNoEsPrimario();

        $mat = CalificacionesPrimarioDatos::matriculaEnContexto($matricula);
        abort_if($mat === null, 404);

        if ($this->modoPortalDocente) {
            CalificacionesPrimarioPortalDocente::abortSiProfesorSinCurso((int) $mat->idCursos);
        }

        $this->idMatricula = (int) $mat->id;
        $this->cursoId = (int) $mat->idCursos;
        $mat->loadMissing(['legajo', 'curso']);
        $this->campos = CalificacionesEpqDatos::cargarInfoAdicional($this->idMatricula);
        $this->alumnoLinea = trim(((string) ($mat->legajo?->apellido ?? '')).' '.((string) ($mat->legajo?->nombre ?? '')));
        $this->cursoLabel = $mat->curso?->nombreParaListado() ?? '—';
    }

    #[Renderless]
    public function saveCampo(string $campo, mixed $value): void
    {
        PortalDocenteContext::abortSiStaffSinPermisoIa(\App\Support\PermisosIaCatalog::CALIF_CARGA);

        $key = 'calificacionesEpq:info:cell:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 240)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $campo = trim($campo);
        if (! in_array($campo, CalificacionesEpqCatalogo::camposInfoAdicional(), true)) {
            abort(400);
        }

        if (CalificacionesPrimarioDatos::matriculaEnContexto($this->idMatricula) === null) {
            abort(404);
        }

        $value = is_string($value) ? trim($value) : (string) ($value ?? '');
        $max = in_array($campo, CalificacionesEpqCatalogo::CAMPOS_HABILIDADES_INTELECTUALES, true) ? 1500 : 120;

        Validator::make(
            ['value' => $value],
            ['value' => ['nullable', 'string', 'max:'.$max]],
        )->validate();

        CalificacionesEpqDatos::guardarInfoAdicional($this->idMatricula, $campo, $value);
        $this->campos[$campo] = $value;
    }

    public function render()
    {
        return view('livewire.calificaciones-primario.epq.info-adicional-form')
            ->layout(CalificacionesPrimarioPortalDocente::layout(), ['pageTitle' => 'Información adicional (EPQ)']);
    }
}
