<?php

namespace App\Livewire\Examenes;

use App\Livewire\Examenes\Concerns\RequiresPermisoExamenes;
use App\Support\Examenes\MateriasAdeudadasAlumnosListado;
use App\Support\Examenes\MateriasAdeudadasCargaManual;
use App\Support\Examenes\MateriasAdeudadasFiltros;
use App\Support\Examenes\MateriasAdeudadasInscripcion;
use App\Support\Examenes\MateriasAdeudadasNotasExamen;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class MateriasAdeudadasNotasIndex extends Component
{
    use RequiresPermisoExamenes;

    public int $idLegajos;

    public ?int $idCalificacionSeleccionada = null;

    public bool $modalAbierto = false;

    public string $fecha = '';

    public string $nota = '';

    public string $condExamen = '';

    public string $libro = '';

    public string $folio = '';

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

        $filas = MateriasAdeudadasInscripcion::filas($idLegajos, (int) $ctx->idNivel);
        if ($filas !== []) {
            $this->idCalificacionSeleccionada = $filas[0]['id'];
        }
    }

    public function seleccionarMateria(int $idCalificacion): void
    {
        $ctx = schoolCtx();
        if (! MateriasAdeudadasNotasExamen::calificacionAdeudadaDelAlumno(
            $idCalificacion,
            $this->idLegajos,
            (int) $ctx->idNivel,
        )) {
            return;
        }

        $this->idCalificacionSeleccionada = $idCalificacion;
        $this->cerrarModal();
    }

    public function abrirModalNuevaNota(): void
    {
        if ($this->idCalificacionSeleccionada === null) {
            return;
        }

        $this->resetFormularioModal();
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetFormularioModal();
        $this->resetValidation();
    }

    public function guardarNuevaNota(): void
    {
        if ($this->idCalificacionSeleccionada === null) {
            $this->addError('notas', 'Seleccione una materia adeudada.');

            return;
        }

        if (! $this->ejecutarConLimite()) {
            return;
        }

        $validated = $this->validate($this->reglasFormulario());

        $ctx = schoolCtx();
        $resultado = MateriasAdeudadasNotasExamen::registrarNueva([
            'idCalificacion' => $this->idCalificacionSeleccionada,
            'idLegajos' => $this->idLegajos,
            'idNivel' => (int) $ctx->idNivel,
            'fecha' => $validated['fecha'],
            'nota' => $validated['nota'],
            'condExamen' => $validated['condExamen'] !== '' ? $validated['condExamen'] : null,
            'libro' => $validated['libro'] !== '' ? $validated['libro'] : null,
            'folio' => $validated['folio'] !== '' ? $validated['folio'] : null,
        ]);

        match ($resultado) {
            'ok' => (function () {
                session()->flash('success', 'Nota de examen registrada.');
                $this->cerrarModal();
            })(),
            'condicion_invalida' => $this->addError('condExamen', 'Condición no válida. Use PR, EQ o TM.'),
            default => $this->addError('notas', 'No se pudo registrar la nota. Verifique los datos y la materia seleccionada.'),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    private function reglasFormulario(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'nota' => ['required', 'string', 'max:10'],
            'condExamen' => ['nullable', 'string', 'in:'.implode(',', MateriasAdeudadasFiltros::CONDICIONES)],
            'libro' => ['nullable', 'string', 'max:10'],
            'folio' => ['nullable', 'string', 'max:10'],
        ];
    }

    private function resetFormularioModal(): void
    {
        $this->fecha = now()->format('Y-m-d');
        $this->nota = '';
        $this->condExamen = '';
        $this->libro = '';
        $this->folio = '';
    }

    private function ejecutarConLimite(): bool
    {
        $key = 'examenes:ma-notas:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->addError('notas', 'Demasiados intentos. Espere un minuto e intente de nuevo.');

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

        $materias = MateriasAdeudadasInscripcion::filas($this->idLegajos, $idNivel);

        if ($this->idCalificacionSeleccionada !== null) {
            $ids = array_column($materias, 'id');
            if (! in_array($this->idCalificacionSeleccionada, $ids, true)) {
                $this->idCalificacionSeleccionada = $materias[0]['id'] ?? null;
            }
        }

        $materiaSeleccionada = null;
        $historial = [];
        if ($this->idCalificacionSeleccionada !== null) {
            foreach ($materias as $fila) {
                if ($fila['id'] === $this->idCalificacionSeleccionada) {
                    $materiaSeleccionada = $fila;
                    break;
                }
            }
            $historial = MateriasAdeudadasNotasExamen::historial(
                $this->idCalificacionSeleccionada,
                $this->idLegajos,
                $idNivel,
            );
        }

        return view('livewire.examenes.materias-adeudadas-notas', [
            'alumno' => $alumno,
            'materias' => $materias,
            'totalMaterias' => count($materias),
            'materiaSeleccionada' => $materiaSeleccionada,
            'historial' => $historial,
            'totalHistorial' => count($historial),
            'condiciones' => MateriasAdeudadasFiltros::CONDICIONES,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Carga de notas — materias adeudadas']);
    }
}
