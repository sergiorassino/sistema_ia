<?php

namespace App\Livewire\Examenes;

use App\Livewire\Examenes\Concerns\RequiresPermisoExamenes;
use App\Support\Examenes\HistorialExamenes;
use App\Support\Examenes\MateriasAdeudadasAlumnosListado;
use App\Support\Examenes\MateriasAdeudadasCargaManual;
use App\Support\Examenes\MateriasAdeudadasFiltros;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class HistorialExamenesIndex extends Component
{
    use RequiresPermisoExamenes;

    public int $idLegajos;

    public bool $modalEditarAbierto = false;

    public bool $modalBorrarAbierto = false;

    public ?int $idNotaSeleccionada = null;

    public string $materiaEtiqueta = '';

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
    }

    public function abrirEditar(int $idNota): void
    {
        $ctx = schoolCtx();
        $registro = HistorialExamenes::registro($idNota, $this->idLegajos, (int) $ctx->idNivel);

        if ($registro === null) {
            $this->addError('historial', 'No se encontró el registro de examen.');

            return;
        }

        $this->idNotaSeleccionada = $idNota;
        $this->materiaEtiqueta = $registro['materia'] !== '' ? $registro['materia'] : '—';
        $this->fecha = $registro['fecha_iso'] !== '' ? $registro['fecha_iso'] : now()->format('Y-m-d');
        $this->nota = $registro['nota'];
        $this->condExamen = $registro['condicion'];
        $this->libro = $registro['libro'];
        $this->folio = $registro['folio'];
        $this->modalBorrarAbierto = false;
        $this->modalEditarAbierto = true;
        $this->resetValidation();
    }

    public function abrirBorrar(int $idNota): void
    {
        $ctx = schoolCtx();
        $registro = HistorialExamenes::registro($idNota, $this->idLegajos, (int) $ctx->idNivel);

        if ($registro === null) {
            $this->addError('historial', 'No se encontró el registro de examen.');

            return;
        }

        $this->idNotaSeleccionada = $idNota;
        $this->materiaEtiqueta = $registro['materia'] !== '' ? $registro['materia'] : '—';
        $this->modalEditarAbierto = false;
        $this->modalBorrarAbierto = true;
    }

    public function cerrarModales(): void
    {
        $this->modalEditarAbierto = false;
        $this->modalBorrarAbierto = false;
        $this->idNotaSeleccionada = null;
        $this->materiaEtiqueta = '';
        $this->resetValidation();
    }

    public function guardarEdicion(): void
    {
        if ($this->idNotaSeleccionada === null) {
            return;
        }

        if (! $this->ejecutarConLimite('editar')) {
            return;
        }

        $validated = $this->validate($this->reglasFormulario());

        $ctx = schoolCtx();
        $resultado = HistorialExamenes::actualizar(
            $this->idNotaSeleccionada,
            $this->idLegajos,
            (int) $ctx->idNivel,
            [
                'fecha' => $validated['fecha'],
                'nota' => $validated['nota'],
                'condExamen' => $validated['condExamen'] !== '' ? $validated['condExamen'] : null,
                'libro' => $validated['libro'] !== '' ? $validated['libro'] : null,
                'folio' => $validated['folio'] !== '' ? $validated['folio'] : null,
            ],
        );

        match ($resultado) {
            'ok' => (function () {
                session()->flash('success', 'Registro de examen actualizado.');
                $this->cerrarModales();
            })(),
            'condicion_invalida' => $this->addError('condExamen', 'Condición no válida. Use PR, EQ o TM.'),
            default => $this->addError('historial', 'No se pudo actualizar el registro.'),
        };
    }

    public function confirmarBorrado(): void
    {
        if ($this->idNotaSeleccionada === null) {
            return;
        }

        if (! $this->ejecutarConLimite('borrar')) {
            return;
        }

        $ctx = schoolCtx();
        $resultado = HistorialExamenes::eliminar(
            $this->idNotaSeleccionada,
            $this->idLegajos,
            (int) $ctx->idNivel,
        );

        if ($resultado === 'ok') {
            session()->flash('success', 'Registro de examen eliminado.');
            $this->cerrarModales();
        } else {
            $this->addError('historial', 'No se pudo eliminar el registro.');
        }
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

    private function ejecutarConLimite(string $accion): bool
    {
        $key = 'examenes:historial:'.$accion.':'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->addError('historial', 'Demasiados intentos. Espere un minuto e intente de nuevo.');

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

        $materias = HistorialExamenes::porMateria($this->idLegajos, $idNivel);
        $totalRegistros = HistorialExamenes::totalRegistros($this->idLegajos, $idNivel);

        return view('livewire.examenes.historial-examenes', [
            'alumno' => $alumno,
            'materias' => $materias,
            'totalMaterias' => count($materias),
            'totalRegistros' => $totalRegistros,
            'condiciones' => MateriasAdeudadasFiltros::CONDICIONES,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Historial de exámenes — materias adeudadas']);
    }
}
