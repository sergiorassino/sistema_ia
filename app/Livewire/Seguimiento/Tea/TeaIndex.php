<?php

namespace App\Livewire\Seguimiento\Tea;

use App\Livewire\Seguimiento\Tea\Concerns\RequiresPermisoTeaGestion;
use App\Models\Curso;
use App\Models\Matricula;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\Tea\ReincoTea;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class TeaIndex extends Component
{
    use RequiresPermisoTeaGestion;

    public int|string $idCurso = '';

    public int|string $idMatricula = '';

    public function mount(): void
    {
        $ctx = ContextoEstudianteSesion::leer(ContextoEstudianteSesion::SEGUIMIENTO_TEA);
        $this->idCurso = (string) ($ctx['curso'] ?? '');
        $this->idMatricula = (string) ($ctx['matricula'] ?? '');
    }

    private function persistirContextoEnSesion(): void
    {
        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SEGUIMIENTO_TEA, [
            'curso' => (int) $this->idCurso ?: null,
            'matricula' => (int) $this->idMatricula ?: null,
        ]);
    }

    public function updatedIdCurso(mixed $value): void
    {
        $this->idCurso = is_scalar($value) ? (string) $value : '';
        $this->idMatricula = '';
        $this->persistirContextoEnSesion();
    }

    public function updatedIdMatricula(mixed $value): void
    {
        $this->idMatricula = is_scalar($value) ? (string) $value : '';
        $this->persistirContextoEnSesion();
    }

    /** @return Collection<int, Curso> */
    private function cursosDelContexto(): Collection
    {
        return Curso::query()
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden', 'idTurnoClase', 'c', 's']);
    }

    /** @return Collection<int, object> */
    private function alumnosDelCurso(int $idCurso): Collection
    {
        return Matricula::query()
            ->where('matricula.idNivel', schoolCtx()->idNivel)
            ->where('matricula.idTerlec', schoolCtx()->idTerlec)
            ->where('matricula.idCursos', $idCurso)
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre')
            ->select([
                'matricula.id',
                'matricula.idLegajos',
                'legajos.apellido',
                'legajos.nombre',
                'legajos.dni',
            ])
            ->get();
    }

    private function matriculaSeleccionada(): ?Matricula
    {
        $id = (int) $this->idMatricula;
        if ($id <= 0) {
            return null;
        }

        return Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->find($id);
    }

    public function borrar(int $id): void
    {
        $matricula = $this->matriculaSeleccionada();
        if (! $matricula) {
            abort(404);
        }

        $key = 'tea-registros:delete:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $registro = ReincoTea::queryRegistros()
            ->where('idMatricula', (int) $matricula->id)
            ->findOrFail($id);

        $registro->delete();

        $this->dispatch('se-swal-exito', mensaje: 'Registro TEA borrado.');
    }

    public function render()
    {
        $tablasDisponibles = ReincoTea::tablasDisponibles();
        $cursos = $this->cursosDelContexto();

        $alumnos = collect();
        $cursoId = (int) $this->idCurso;
        if ($cursoId > 0) {
            $alumnos = $this->alumnosDelCurso($cursoId);
        }

        $matricula = $this->matriculaSeleccionada();
        $registros = collect();

        if ($matricula && $tablasDisponibles) {
            $registros = ReincoTea::registrosDeMatricula((int) $matricula->id);
        } elseif ((int) $this->idMatricula > 0) {
            $this->idMatricula = '';
        }

        return view('livewire.seguimiento.tea.index', compact(
            'tablasDisponibles',
            'cursos',
            'alumnos',
            'matricula',
            'registros',
        ))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Gestión de TEA']);
    }
}
