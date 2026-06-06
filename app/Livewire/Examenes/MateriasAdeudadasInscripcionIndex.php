<?php

namespace App\Livewire\Examenes;

use App\Livewire\Examenes\Concerns\RequiresPermisoExamenes;
use App\Support\Examenes\MateriasAdeudadasAlumnosListado;
use App\Support\Examenes\MateriasAdeudadasCargaManual;
use App\Support\Examenes\MateriasAdeudadasInscripcion;
use App\Support\Examenes\MateriasAdeudadasFiltros;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class MateriasAdeudadasInscripcionIndex extends Component
{
    use RequiresPermisoExamenes;

    public int $idLegajos;

    public function mount(): void
    {
        $idLegajos = \App\Support\Navegacion\ContextoEstudianteSesion::legajo(
            \App\Support\Navegacion\ContextoEstudianteSesion::EXAMENES_MATERIAS_ADEUDADAS,
        );
        abort_if($idLegajos === null, 404);

        $ctx = schoolCtx();
        if (! $ctx->isValid() || ! MateriasAdeudadasAlumnosListado::esNivelSecundario($ctx)) {
            abort(403, 'Este módulo requiere contexto de Secundario.');
        }

        if (! MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion(MateriasAdeudadasPreparacion::MODULO_GESTION)) {
            $this->redirectRoute('examenes.materias-adeudadas.gestion.entrar');

            return;
        }

        $alumno = MateriasAdeudadasCargaManual::alumnoEnGestion(
            $idLegajos,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );

        if ($alumno === null) {
            abort(404, 'Alumno no encontrado en la matrícula activa del ciclo lectivo actual.');
        }

        $this->idLegajos = $idLegajos;
    }

    public function cambiarCondicion(int $idCalificacion, string $condicion): void
    {
        if (! $this->ejecutarConLimite()) {
            return;
        }

        $ctx = schoolCtx();
        $resultado = MateriasAdeudadasInscripcion::actualizarCondicion(
            $idCalificacion,
            $this->idLegajos,
            (int) $ctx->idNivel,
            $condicion,
        );

        match ($resultado) {
            'ok' => session()->flash('success', 'Condición actualizada.'),
            'condicion_invalida' => $this->addError('inscripcion', 'Condición no válida. Use PR, EQ o TM.'),
            default => $this->addError('inscripcion', 'No se encontró la calificación o no pertenece a este alumno.'),
        };
    }

    public function cambiarInscripcion(int $idCalificacion, bool $inscripto): void
    {
        if (! $this->ejecutarConLimite()) {
            return;
        }

        $ctx = schoolCtx();
        $resultado = MateriasAdeudadasInscripcion::actualizarInscripcion(
            $idCalificacion,
            $this->idLegajos,
            (int) $ctx->idNivel,
            $inscripto,
        );

        if ($resultado === 'ok') {
            session()->flash(
                'success',
                $inscripto ? 'Materia inscripta a examen.' : 'Inscripción a examen anulada.',
            );
        } else {
            $this->addError('inscripcion', 'No se encontró la calificación o no pertenece a este alumno.');
        }
    }

    private function ejecutarConLimite(): bool
    {
        $key = 'examenes:ma-inscribir:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError('inscripcion', 'Demasiados intentos. Espere un minuto e intente de nuevo.');

            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    public function render()
    {
        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;

        $alumno = MateriasAdeudadasCargaManual::alumnoEnGestion(
            $this->idLegajos,
            $idNivel,
            (int) $ctx->idTerlec,
        ) ?? [];

        $filas = MateriasAdeudadasInscripcion::filas($this->idLegajos, $idNivel);

        return view('livewire.examenes.materias-adeudadas-inscripcion', [
            'alumno' => $alumno,
            'filas' => $filas,
            'totalFilas' => count($filas),
            'condiciones' => MateriasAdeudadasFiltros::CONDICIONES,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Inscribir — materias adeudadas']);
    }
}
