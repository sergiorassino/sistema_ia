<?php

namespace App\Livewire\SolicitudEvaluacion;

use App\Livewire\SolicitudEvaluacion\Concerns\RequiresSolicitudEvaluacion;
use App\Models\Evaluac;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\SolicitudEvaluacion\SolicitudEvaluacionConsulta;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class SolicitudEvaluacionForm extends Component
{
    use RequiresSolicitudEvaluacion;

    public int|string $idCurso = '';

    public string $fecha = '';

    public int|string $idMateria = '';

    public string $temas = '';

    public string $obs = '';

    public function mount(): void
    {
        $this->fijarOrigenPortalSolicitudEvaluacion();

        $ctx = ContextoEstudianteSesion::leer(ContextoEstudianteSesion::SOLICITUD_EVALUACION);
        if (! $this->solicitudEvaluacionPortalDocente && (int) ($ctx['portal_docente'] ?? 0) === 1) {
            $this->solicitudEvaluacionPortalDocente = true;
        }

        $this->idCurso = (string) ($ctx['curso'] ?? request()->query('idCurso', ''));
        $this->fecha = SolicitudEvaluacionConsulta::normalizarFechaYmd(
            $ctx['fecha'] ?? request()->query('fecha', '')
        );

        $idCurso = (int) $this->idCurso;
        $fecha = $this->fecha;

        if ($idCurso < 1 || $fecha === '') {
            $rutaIndex = $this->esPortalDocente()
                ? 'portalDocente.solicitudEvaluacion'
                : 'calificacionesSecundario.solicitudEvaluacion';

            $this->redirectRoute($rutaIndex, navigate: false);

            return;
        }

        $this->fecha = $fecha;

        $curso = SolicitudEvaluacionConsulta::cursoEnContexto($idCurso, $this->esPortalDocente());
        if ($curso === null || ! SolicitudEvaluacionConsulta::puedeSolicitarNueva($idCurso, $fecha)) {
            $rutaIndex = $this->esPortalDocente()
                ? 'portalDocente.solicitudEvaluacion'
                : 'calificacionesSecundario.solicitudEvaluacion';

            session()->flash('error', 'No puede registrar otra evaluación para ese curso y fecha.');

            $this->redirectRoute($rutaIndex, navigate: false);

            return;
        }
    }

    protected function rules(): array
    {
        return [
            'idMateria' => ['required', 'integer', 'min:1'],
            'temas' => ['nullable', 'string', 'max:200'],
            'obs' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'idMateria.required' => 'Seleccione la materia.',
        ];
    }

    public function save(): mixed
    {
        $key = 'solicitud-evaluacion:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('idMateria', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return null;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $idCurso = (int) $this->idCurso;
        $fecha = SolicitudEvaluacionConsulta::normalizarFechaYmd($this->fecha);
        $this->fecha = $fecha;
        $idMateria = (int) $this->idMateria;

        abort_if($idCurso < 1 || $fecha === '', 404);

        $curso = SolicitudEvaluacionConsulta::cursoEnContexto($idCurso, $this->esPortalDocente());
        abort_if($curso === null, 404);

        abort_unless(
            SolicitudEvaluacionConsulta::puedeSolicitarNueva($idCurso, $fecha),
            403,
            'Ya se alcanzó el máximo de evaluaciones para ese curso en la fecha.',
        );

        if (! SolicitudEvaluacionConsulta::materiaPerteneceAlCurso($idMateria, $idCurso)) {
            $this->addError('idMateria', 'La materia seleccionada no pertenece al curso indicado.');

            return null;
        }

        Evaluac::create([
            'idCurso' => $idCurso,
            'idMateria' => $idMateria,
            'fecheval' => $fecha,
            'temas' => trim($this->temas) !== '' ? trim($this->temas) : null,
            'obs' => trim($this->obs) !== '' ? trim($this->obs) : null,
            'fechregi' => now(),
        ]);

        session()->flash('success', 'Evaluación registrada.');

        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SOLICITUD_EVALUACION, [
            'curso' => $idCurso,
            'fecha' => $fecha,
            'portal_docente' => $this->solicitudEvaluacionPortalDocente ? 1 : 0,
        ]);

        $rutaIndex = $this->esPortalDocente()
            ? 'portalDocente.solicitudEvaluacion'
            : 'calificacionesSecundario.solicitudEvaluacion';

        return $this->redirectRoute($rutaIndex, ['continuar' => 1], navigate: false);
    }

    public function render()
    {
        $idCurso = (int) $this->idCurso;
        $fecha = SolicitudEvaluacionConsulta::normalizarFechaYmd($this->fecha);
        $this->fecha = $fecha;

        $curso = SolicitudEvaluacionConsulta::cursoEnContexto($idCurso, $this->esPortalDocente());
        abort_if($curso === null, 404);

        $materias = SolicitudEvaluacionConsulta::materiasDelCurso($idCurso);
        $evaluaciones = SolicitudEvaluacionConsulta::evaluacionesDelCursoEnFecha($idCurso, $fecha);
        $etiquetasMateria = SolicitudEvaluacionConsulta::etiquetasMateriaParaEvaluaciones($evaluaciones);

        $layout = $this->esPortalDocente() ? 'layouts.docente' : 'layouts.app';
        $rutaVolver = $this->esPortalDocente()
            ? 'portalDocente.solicitudEvaluacion'
            : 'calificacionesSecundario.solicitudEvaluacion';

        return view('livewire.solicitud-evaluacion.form', [
            'curso' => $curso,
            'materias' => $materias,
            'evaluaciones' => $evaluaciones,
            'etiquetasMateria' => $etiquetasMateria,
            'maxPorDia' => SolicitudEvaluacionConsulta::MAX_EVALUACIONES_POR_DIA,
            'rutaVolver' => $rutaVolver,
            'volverConFiltros' => ['continuar' => 1],
        ])->layout($layout, ['pageTitle' => 'Nueva solicitud de evaluación']);
    }
}
