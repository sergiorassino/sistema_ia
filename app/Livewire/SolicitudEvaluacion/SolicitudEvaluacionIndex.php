<?php

namespace App\Livewire\SolicitudEvaluacion;

use App\Livewire\SolicitudEvaluacion\Concerns\RequiresSolicitudEvaluacion;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\SolicitudEvaluacion\SolicitudEvaluacionConsulta;
use Livewire\Component;

class SolicitudEvaluacionIndex extends Component
{
    use RequiresSolicitudEvaluacion;

    public string $fecha = '';

    public int|string $idCurso = '';

    public function mount(): void
    {
        $this->fijarOrigenPortalSolicitudEvaluacion();

        if (request()->boolean('continuar')) {
            $ctx = ContextoEstudianteSesion::leer(ContextoEstudianteSesion::SOLICITUD_EVALUACION);
            $this->fecha = (string) ($ctx['fecha'] ?? '');
            $this->idCurso = (string) ($ctx['curso'] ?? '');

            return;
        }

        ContextoEstudianteSesion::limpiar(ContextoEstudianteSesion::SOLICITUD_EVALUACION);
        $this->fecha = '';
        $this->idCurso = '';
    }

    private function persistirContextoEnSesion(): void
    {
        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SOLICITUD_EVALUACION, [
            'fecha' => $this->fecha !== '' ? $this->fecha : null,
            'curso' => (int) $this->idCurso ?: null,
            'portal_docente' => $this->solicitudEvaluacionPortalDocente ? 1 : 0,
        ]);
    }

    public function updatedFecha(mixed $value): void
    {
        $this->fecha = is_scalar($value) ? trim((string) $value) : '';
        $this->persistirContextoEnSesion();
    }

    public function updatedIdCurso(mixed $value): void
    {
        $this->idCurso = is_scalar($value) ? (string) $value : '';
        $this->persistirContextoEnSesion();
    }

    public function irASolicitarNueva(): mixed
    {
        $idCurso = (int) $this->idCurso;
        $fecha = trim($this->fecha);

        if ($idCurso < 1 || $fecha === '') {
            $this->addError('idCurso', 'Seleccione la fecha y el curso antes de continuar.');

            return null;
        }

        $soloDocente = $this->esPortalDocente();
        $curso = SolicitudEvaluacionConsulta::cursoEnContexto($idCurso, $soloDocente);
        if ($curso === null) {
            $this->addError('idCurso', 'El curso seleccionado no está disponible en su contexto.');

            return null;
        }

        if (! SolicitudEvaluacionConsulta::puedeSolicitarNueva($idCurso, $fecha)) {
            $this->addError('fecha', 'Ya hay '.SolicitudEvaluacionConsulta::MAX_EVALUACIONES_POR_DIA.' evaluaciones para ese curso en la fecha indicada.');

            return null;
        }

        $this->persistirContextoEnSesion();

        $ruta = $soloDocente
            ? 'portalDocente.solicitudEvaluacion.create'
            : 'calificacionesSecundario.solicitudEvaluacion.create';

        return $this->redirectRoute($ruta, navigate: false);
    }

    public function render()
    {
        $soloDocente = $this->esPortalDocente();
        $cursos = SolicitudEvaluacionConsulta::cursosParaSelector($soloDocente);

        $idCurso = (int) $this->idCurso;
        $fecha = $this->fecha;
        $curso = null;
        $evaluaciones = collect();
        $etiquetasMateria = [];
        $puedeNueva = false;

        if ($idCurso > 0 && $fecha !== '') {
            $curso = SolicitudEvaluacionConsulta::cursoEnContexto($idCurso, $soloDocente);
            if ($curso) {
                $evaluaciones = SolicitudEvaluacionConsulta::evaluacionesDelCursoEnFecha($idCurso, $fecha);
                $etiquetasMateria = SolicitudEvaluacionConsulta::etiquetasMateriaParaEvaluaciones($evaluaciones);
                $puedeNueva = SolicitudEvaluacionConsulta::puedeSolicitarNueva($idCurso, $fecha);
            }
        }

        $layout = $soloDocente ? 'layouts.docente' : 'layouts.app';

        return view('livewire.solicitud-evaluacion.index', [
            'cursos' => $cursos,
            'curso' => $curso,
            'evaluaciones' => $evaluaciones,
            'etiquetasMateria' => $etiquetasMateria,
            'puedeNueva' => $puedeNueva,
            'maxPorDia' => SolicitudEvaluacionConsulta::MAX_EVALUACIONES_POR_DIA,
        ])->layout($layout, ['pageTitle' => 'Solicitud de evaluación']);
    }
}
