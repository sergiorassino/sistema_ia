<?php

namespace App\Livewire\Listados;

use App\Models\Terlec;
use App\Support\Listados\EstudiantesDatosConsulta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FichaMatriculaSecretaria extends Component
{
    public function mount(): void
    {
        abort_unless(tenantSecretariaFichaMatriculaHabilitada(), 404);

        $actual = (int) (schoolCtx()->idTerlec ?? 0);
        $this->idTerlec = $this->idsTerlecPermitidos()->contains($actual) ? $actual : '';
    }

    /** 1 = ciclo y cursos, 2 = alumnos */
    public int $paso = 1;

    public int|string $idTerlec = '';

    /** @var list<string> */
    public array $cursosSeleccionados = [];

    /** @var array<string, bool> matricula_id => incluido */
    public array $alumnosIncluidos = [];

    public function updatedIdTerlec(): void
    {
        $this->paso = 1;
        $this->cursosSeleccionados = [];
        $this->alumnosIncluidos = [];
        $this->resetErrorBag();
    }

    public function continuarConAlumnos(): void
    {
        $idsTerlec = $this->idsTerlecPermitidos()->all();
        $this->validate([
            'idTerlec' => ['required', function (string $attribute, mixed $value, \Closure $fail) use ($idsTerlec): void {
                if (! in_array((int) $value, $idsTerlec, true)) {
                    $fail('Seleccione el ciclo lectivo.');
                }
            }],
            'cursosSeleccionados' => ['required', 'array', 'min:1'],
            'cursosSeleccionados.*' => ['integer', 'min:1'],
        ], [
            'idTerlec.required' => 'Seleccione el ciclo lectivo.',
            'cursosSeleccionados.required' => 'Seleccione al menos un curso.',
            'cursosSeleccionados.min' => 'Seleccione al menos un curso.',
        ]);

        $permitidos = EstudiantesDatosConsulta::cursosPorNivelYTerlec(
            (int) schoolCtx()->idNivel,
            (int) $this->idTerlec,
        )
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

    public function getPdfUrlProperty(): string
    {
        return $this->urlDescargaFichas('listados.ficha-matricula.pdf');
    }

    public function getZipUrlProperty(): string
    {
        return $this->urlDescargaFichas('listados.ficha-matricula.zip');
    }

    public function puedeGenerarPdf(): bool
    {
        return Route::has('listados.ficha-matricula.pdf') && $this->matriculasIncluidas() !== [];
    }

    public function puedeGenerarZip(): bool
    {
        return Route::has('listados.ficha-matricula.zip') && $this->matriculasIncluidas() !== [];
    }

    private function urlDescargaFichas(string $routeName): string
    {
        if (! Route::has($routeName)) {
            return '#';
        }

        $ids = $this->matriculasIncluidas();
        if ($ids === []) {
            return '#';
        }

        return route($routeName, [
            'matriculas' => implode(',', $ids),
            'terlec' => (int) $this->idTerlec,
        ]);
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

        $idTerlec = (int) $this->idTerlec;

        return EstudiantesDatosConsulta::alumnosRegularesPorCursos(
            $cursoIds,
            (int) schoolCtx()->idNivel,
            $idTerlec,
        );
    }

    public function render()
    {
        $idTerlec = (int) $this->idTerlec;
        $cursos = $idTerlec > 0
            ? EstudiantesDatosConsulta::cursosPorNivelYTerlec((int) schoolCtx()->idNivel, $idTerlec)
            : collect();
        $anoTerlec = (string) (Terlec::paraSelector()->firstWhere('id', $idTerlec)?->ano ?? '');
        $alumnos = $this->paso === 2 ? $this->alumnosCargados() : collect();
        $alumnosPorCurso = $alumnos->groupBy(fn (object $row) => (string) (int) $row->id_curso);
        $cursosConAlumnos = $cursos
            ->filter(fn ($curso) => $alumnosPorCurso->has((string) (int) $curso->Id))
            ->values();

        $totalIncluidos = count($this->matriculasIncluidas());
        $totalAlumnos = $alumnos->count();
        $etiqueta = tenantSecretariaFichaMatriculaEtiqueta();
        $implementacion = tenantSecretariaFichaMatriculaImplementacion();

        return view('listados::livewire.listados.ficha-matricula-secretaria', compact(
            'cursos',
            'alumnos',
            'alumnosPorCurso',
            'cursosConAlumnos',
            'totalIncluidos',
            'totalAlumnos',
            'etiqueta',
            'implementacion',
            'anoTerlec',
        ));
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function idsTerlecPermitidos(): Collection
    {
        return Terlec::paraSelector()->pluck('id')->map(fn ($id) => (int) $id);
    }
}
