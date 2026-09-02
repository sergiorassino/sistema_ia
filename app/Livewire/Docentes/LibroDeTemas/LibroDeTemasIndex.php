<?php

namespace App\Livewire\Docentes\LibroDeTemas;

use App\Livewire\Docentes\LibroDeTemas\Concerns\InteractsWithLibroDeTemas;
use App\Support\LibroDeTemas\LibroDeTemasService;
use Livewire\Component;

class LibroDeTemasIndex extends Component
{
    use InteractsWithLibroDeTemas;

    public ?int $cursoId = null;

    public ?int $materiaId = null;

    public function mount(): void
    {
        $this->inicializarLibroDeTemas();
    }

    public function updatedCursoId(mixed $value): void
    {
        $this->cursoId = ((int) $value) > 0 ? (int) $value : null;
        $this->materiaId = null;
        $this->resetValidation();
    }

    public function updatedMateriaId(mixed $value): void
    {
        $this->materiaId = ((int) $value) > 0 ? (int) $value : null;
        $this->resetValidation('materiaId');
    }

    public function abrirLibro(): mixed
    {
        $idCurso = (int) ($this->cursoId ?? 0);
        $idMateria = (int) ($this->materiaId ?? 0);

        if ($idCurso < 1 || $idMateria < 1) {
            $this->addError('materiaId', 'Seleccione el curso y la materia.');

            return null;
        }

        $materia = LibroDeTemasService::materiaEnAlcance($idMateria, $this->soloPpcDelProfesor());
        if ($materia === null || (int) $materia->idCurso !== $idCurso) {
            $this->addError('materiaId', 'La materia no está disponible en su contexto.');

            return null;
        }

        return $this->redirect(
            route($this->rutaClasesLibroDeTemas(), ['materia' => $idMateria]),
            navigate: true,
        );
    }

    public function render()
    {
        $soloPpc = $this->soloPpcDelProfesor();
        $cursos = LibroDeTemasService::cursosParaSelector($soloPpc);
        $idCurso = (int) ($this->cursoId ?? 0);
        if ($idCurso > 0 && ! $cursos->contains(fn ($c) => (int) $c->Id === $idCurso)) {
            $this->cursoId = null;
            $this->materiaId = null;
            $idCurso = 0;
        }

        $materias = LibroDeTemasService::materiasDelCurso($idCurso, $soloPpc);
        $idMateria = (int) ($this->materiaId ?? 0);
        if ($idMateria > 0 && ! $materias->contains(fn ($m) => (int) $m->id === $idMateria)) {
            $this->materiaId = null;
        }

        return view('livewire.docentes.libro-de-temas.index', [
            'cursos' => $cursos,
            'materias' => $materias,
        ])->layout($this->layoutLibroDeTemas(), ['pageTitle' => 'Libro de temas']);
    }
}
