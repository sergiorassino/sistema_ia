<?php

namespace App\Livewire\SolicitudEvaluacion\Gestion;

use App\Livewire\SolicitudEvaluacion\Concerns\RequiresPermisoGestionSolicitudEvaluacion;
use App\Models\Evaluac;
use App\Support\SolicitudEvaluacion\SolicitudEvaluacionConsulta;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class GestionSolicitudEvaluacionForm extends Component
{
    use RequiresPermisoGestionSolicitudEvaluacion;

    public ?int $evaluacionId = null;

    public int|string $idCurso = '';

    public string $fecha = '';

    public int|string $idMateria = '';

    public string $temas = '';

    public string $obs = '';

    public function mount(?int $id = null): void
    {
        if ($id !== null && $id > 0) {
            $evaluacion = SolicitudEvaluacionConsulta::evaluacionEnContexto($id);
            abort_if($evaluacion === null, 404);

            $this->evaluacionId = (int) $evaluacion->Id;
            $this->idCurso = (string) $evaluacion->idCurso;
            $this->fecha = $evaluacion->fecheval?->format('Y-m-d') ?? '';
            $this->idMateria = (string) $evaluacion->idMateria;
            $this->temas = trim((string) ($evaluacion->temas ?? ''));
            $this->obs = trim((string) ($evaluacion->obs ?? ''));

            return;
        }

        $this->evaluacionId = null;
    }

    public function updatedIdCurso(mixed $value): void
    {
        $this->idCurso = is_scalar($value) ? (string) $value : '';
        $this->idMateria = '';
    }

    protected function rules(): array
    {
        return [
            'idCurso' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'idMateria' => ['required', 'integer', 'min:1'],
            'temas' => ['nullable', 'string', 'max:200'],
            'obs' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'idCurso.required' => 'Seleccione el curso.',
            'fecha.required' => 'Indique la fecha de evaluación.',
            'idMateria.required' => 'Seleccione la materia.',
        ];
    }

    public function save(): mixed
    {
        $key = 'gestion-solicitud-evaluacion:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('idMateria', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return null;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $idCurso = (int) $this->idCurso;
        $fecha = trim($this->fecha);
        $idMateria = (int) $this->idMateria;

        $curso = SolicitudEvaluacionConsulta::cursoEnContexto($idCurso, false);
        if ($curso === null) {
            $this->addError('idCurso', 'El curso seleccionado no está disponible en su contexto.');

            return null;
        }

        if (! SolicitudEvaluacionConsulta::materiaPerteneceAlCurso($idMateria, $idCurso)) {
            $this->addError('idMateria', 'La materia seleccionada no pertenece al curso indicado.');

            return null;
        }

        $payload = [
            'idCurso' => $idCurso,
            'idMateria' => $idMateria,
            'fecheval' => $fecha,
            'temas' => trim($this->temas) !== '' ? trim($this->temas) : null,
            'obs' => trim($this->obs) !== '' ? trim($this->obs) : null,
        ];

        if ($this->evaluacionId !== null) {
            $evaluacion = SolicitudEvaluacionConsulta::evaluacionEnContexto($this->evaluacionId);
            abort_if($evaluacion === null, 404);

            $evaluacion->update($payload);
            session()->flash('success', 'Evaluación actualizada.');
        } else {
            Evaluac::create(array_merge($payload, [
                'fechregi' => now(),
            ]));
            session()->flash('success', 'Evaluación registrada.');
        }

        return $this->redirectRoute('calificacionesSecundario.gestionSolicitudesEvaluacion.index', navigate: false);
    }

    public function render()
    {
        $cursos = SolicitudEvaluacionConsulta::cursosParaSelector(false);
        $idCurso = (int) $this->idCurso;
        $materias = $idCurso > 0
            ? SolicitudEvaluacionConsulta::materiasDelCurso($idCurso)
            : collect();

        $esEdicion = $this->evaluacionId !== null;

        return view('livewire.solicitud-evaluacion.gestion.form', [
            'cursos' => $cursos,
            'materias' => $materias,
            'esEdicion' => $esEdicion,
        ])->layout(layoutMenuStaff(), [
            'pageTitle' => $esEdicion ? 'Editar evaluación' : 'Nueva evaluación',
        ]);
    }
}
