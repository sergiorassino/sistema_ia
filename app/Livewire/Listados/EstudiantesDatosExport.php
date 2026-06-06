<?php

namespace App\Livewire\Listados;

use App\Support\Listados\EstudiantesDatosConsulta;
use App\Support\Navegacion\MenuSecretariaPerfil;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class EstudiantesDatosExport extends Component
{
    public function mount(): void
    {
        MenuSecretariaPerfil::abortSiNoViajesSalidasEducativas();
    }

    /** 1 = cursos, 2 = alumnos */
    public int $paso = 1;

    /** @var list<string> */
    public array $cursosSeleccionados = [];

    /** @var array<string, bool> matricula_id => incluido */
    public array $alumnosIncluidos = [];

    public function continuarConAlumnos(): void
    {
        $this->validate([
            'cursosSeleccionados' => ['required', 'array', 'min:1'],
            'cursosSeleccionados.*' => ['integer', 'min:1'],
        ], [
            'cursosSeleccionados.required' => 'Seleccione al menos un curso.',
            'cursosSeleccionados.min' => 'Seleccione al menos un curso.',
        ]);

        $permitidos = EstudiantesDatosConsulta::cursosEnContexto()
            ->pluck('Id')
            ->map(fn ($id) => (string) (int) $id)
            ->flip();

        $this->cursosSeleccionados = collect($this->cursosSeleccionados)
            ->map(fn ($id) => (string) (int) $id)
            ->filter(fn (string $id) => $permitidos->has($id))
            ->unique()
            ->values()
            ->all();

        if ($this->cursosSeleccionados === []) {
            $this->addError('cursosSeleccionados', 'Seleccione al menos un curso válido.');

            return;
        }

        $this->alumnosIncluidos = [];
        foreach ($this->alumnosCargados() as $alumno) {
            $this->alumnosIncluidos[(string) (int) $alumno->matricula_id] = true;
        }

        $this->paso = 2;
    }

    public function volverACursos(): void
    {
        $this->paso = 1;
        $this->alumnosIncluidos = [];
        $this->resetErrorBag();
    }

    public function marcarTodosAlumnos(): void
    {
        foreach ($this->alumnosCargados() as $alumno) {
            $this->alumnosIncluidos[(string) (int) $alumno->matricula_id] = true;
        }
    }

    public function desmarcarTodosAlumnos(): void
    {
        foreach ($this->alumnosCargados() as $alumno) {
            $this->alumnosIncluidos[(string) (int) $alumno->matricula_id] = false;
        }
    }

    public function getExcelUrlProperty(): string
    {
        $ids = $this->matriculasIncluidas();
        if ($ids === []) {
            return '#';
        }

        return route('listados.estudiantes-datos.excel', [
            'matriculas' => implode(',', $ids),
        ]);
    }

    public function getPdfUrlProperty(): string
    {
        if (! Route::has('listados.estudiantes-datos.pdf')) {
            return '#';
        }

        $ids = $this->matriculasIncluidas();
        if ($ids === []) {
            return '#';
        }

        return route('listados.estudiantes-datos.pdf', [
            'matriculas' => implode(',', $ids),
        ]);
    }

    public function puedeGenerarPdf(): bool
    {
        return Route::has('listados.estudiantes-datos.pdf') && $this->puedeGenerarExport();
    }

    public function puedeGenerarExport(): bool
    {
        return $this->matriculasIncluidas() !== [];
    }

    /** @return list<int> */
    private function matriculasIncluidas(): array
    {
        return collect($this->alumnosIncluidos)
            ->filter(fn ($incluido) => (bool) $incluido)
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
    }

    /** @return Collection<int, object> */
    private function alumnosCargados(): Collection
    {
        if ($this->cursosSeleccionados === []) {
            return collect();
        }

        $cursoIds = collect($this->cursosSeleccionados)
            ->map(fn ($id) => (int) $id)
            ->all();

        return EstudiantesDatosConsulta::alumnosRegularesPorCursos($cursoIds);
    }

    public function render()
    {
        $cursos = EstudiantesDatosConsulta::cursosEnContexto();
        $alumnos = $this->paso === 2 ? $this->alumnosCargados() : collect();

        $alumnosPorCurso = $alumnos->groupBy(fn (object $row) => (string) (int) $row->id_curso);

        $cursosConAlumnos = $cursos
            ->filter(fn ($curso) => $alumnosPorCurso->has((string) (int) $curso->Id))
            ->values();

        $totalIncluidos = count($this->matriculasIncluidas());
        $totalAlumnos = $alumnos->count();

        return view('listados::livewire.listados.estudiantes-datos-export', compact(
            'cursos',
            'alumnos',
            'alumnosPorCurso',
            'cursosConAlumnos',
            'totalIncluidos',
            'totalAlumnos',
        ));
    }
}
