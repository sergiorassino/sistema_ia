<?php

namespace App\Livewire\Seguimiento\Disciplinario;

use App\Livewire\Seguimiento\Disciplinario\Concerns\RequiresPermisoSeguimientoDisciplinario;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Sancion;
use App\Support\Navegacion\ContextoEstudianteSesion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class DisciplinarioIndex extends Component
{
    use RequiresPermisoSeguimientoDisciplinario;

    public int|string $idCurso = '';
    public int|string $idMatricula = '';

    public bool $showDeleteConfirm = false;
    public ?int $deleteId = null;
    public string $deleteInfo = '';

    public function mount(): void
    {
        $ctx = ContextoEstudianteSesion::leer(ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO);
        $this->idCurso = (string) ($ctx['curso'] ?? '');
        $this->idMatricula = (string) ($ctx['matricula'] ?? '');
    }

    private function persistirContextoEnSesion(): void
    {
        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO, [
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

    /** @return Collection<int, Sancion> */
    private function sancionesDeMatricula(int $idMatricula): Collection
    {
        return Sancion::query()
            ->with(['tipo', 'profesor'])
            ->where('idMatricula', $idMatricula)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    public function confirmDelete(int $id): void
    {
        $m = $this->matriculaSeleccionada();
        if (! $m) {
            abort(404);
        }

        $s = Sancion::query()
            ->where('idMatricula', (int) $m->id)
            ->with('tipo')
            ->findOrFail($id);

        $fecha = $s->fecha ? $s->fecha->format('d/m/Y') : '—';
        $tipo = $s->tipo?->tipo ?? ('#'.$s->idTipoSancion);

        $this->deleteId = (int) $s->id;
        $this->deleteInfo = "¿Confirma borrar el evento \"{$tipo}\" ({$fecha})?";
        $this->showDeleteConfirm = true;
    }

    public function delete(): void
    {
        $m = $this->matriculaSeleccionada();
        if (! $m) {
            abort(404);
        }

        $key = 'sanciones:delete:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('success', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showDeleteConfirm = false;
            $this->reset('deleteId', 'deleteInfo');
            return;
        }
        RateLimiter::hit($key, 60);

        if ($this->deleteId) {
            Sancion::query()
                ->where('idMatricula', (int) $m->id)
                ->findOrFail((int) $this->deleteId)
                ->delete();

            session()->flash('success', 'Evento borrado.');
        }

        $this->showDeleteConfirm = false;
        $this->reset('deleteId', 'deleteInfo');
    }

    public function render()
    {
        $cursos = $this->cursosDelContexto();

        $alumnos = collect();
        $cursoId = (int) $this->idCurso;
        if ($cursoId > 0) {
            $alumnos = $this->alumnosDelCurso($cursoId);
        }

        $matricula = $this->matriculaSeleccionada();
        $sanciones = collect();

        if ($matricula) {
            $sanciones = $this->sancionesDeMatricula((int) $matricula->id);
        } else {
            // si hay matrícula inválida para el curso/contexto, limpiar selección
            if ((int) $this->idMatricula > 0) {
                $this->idMatricula = '';
            }
        }

        return view('livewire.seguimiento.disciplinario.index', compact('cursos', 'alumnos', 'matricula', 'sanciones'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Seguimiento disciplinario']);
    }
}

