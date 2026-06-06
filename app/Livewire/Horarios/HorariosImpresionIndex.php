<?php

namespace App\Livewire\Horarios;

use App\Models\Curso;
use App\Support\HorariosProfesores;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class HorariosImpresionIndex extends Component
{
    /*
     * Depuración SQL en pantalla — desactivada (mismo criterio que HorariosCargaIndex).
     * Para reactivar: descomentar propiedad, bloque en render() y Blade en horarios-impresion-index.
     *
     * public bool $mostrarPanelSqlImpresionCurso = true;
     */

    /** curso|profesor */
    public string $modo = 'curso';

    /** @var list<array{id:int, label:string}> */
    public array $cursosSeleccionados = [];

    /** @var list<array{id:int, label:string}> */
    public array $profesoresSeleccionados = [];

    /** Si no es null, el PDF usa este turnos_clase (debe estar activo en configuración). */
    public ?int $pdfTurnoClase = null;

    public bool $modalCursoAbierto = false;

    public string $modalCursoFiltro = '';

    /** @var list<array{id:int, label:string}> */
    public array $modalCursoLista = [];

    /** @var list<int|string> */
    public array $modalCursoMarcados = [];

    public bool $modalProfesorAbierto = false;

    public string $modalProfesorFiltro = '';

    /** @var list<array{id:int, label:string}> */
    public array $modalProfesorLista = [];

    /** @var list<int|string> */
    public array $modalProfesorMarcados = [];

    public function updatedModo(): void
    {
        $this->cursosSeleccionados = [];
        $this->profesoresSeleccionados = [];
        $this->pdfTurnoClase = null;
        $this->cerrarModalCurso();
        $this->cerrarModalProfesor();
    }

    public function updatedModalCursoFiltro(): void
    {
        if ($this->modalCursoAbierto) {
            $this->recargarModalCursoLista();
        }
    }

    public function updatedModalProfesorFiltro(): void
    {
        if ($this->modalProfesorAbierto) {
            $this->recargarModalProfesorLista();
        }
    }

    public function updatedPdfTurnoClase(mixed $value): void
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            $this->pdfTurnoClase = null;
        } else {
            $this->pdfTurnoClase = (int) $value;
        }
    }

    public function abrirModalCurso(): void
    {
        $this->modalCursoAbierto = true;
        $this->modalCursoFiltro = '';
        $this->modalCursoMarcados = array_map(fn (array $c) => (int) $c['id'], $this->cursosSeleccionados);
        $this->recargarModalCursoLista();
    }

    public function cerrarModalCurso(): void
    {
        $this->modalCursoAbierto = false;
    }

    public function aplicarModalCurso(): void
    {
        $labelsPorId = collect($this->modalCursoLista)->keyBy('id');
        $prev = collect($this->cursosSeleccionados)->keyBy('id');
        $out = [];
        foreach (array_unique(array_map('intval', $this->modalCursoMarcados)) as $id) {
            if ($id <= 0) {
                continue;
            }
            $fromLista = $labelsPorId->get($id);
            if ($fromLista !== null) {
                $out[] = ['id' => $id, 'label' => (string) $fromLista['label']];

                continue;
            }
            $fromPrev = $prev->get($id);
            if ($fromPrev !== null) {
                $out[] = ['id' => $id, 'label' => (string) $fromPrev['label']];
            }
        }
        $this->cursosSeleccionados = $out;
        $this->pdfTurnoClase = null;
        $this->modalCursoAbierto = false;
    }

    public function modalCursoSeleccionarTodosVisibles(): void
    {
        $ids = array_map(fn (array $r) => (int) $r['id'], $this->modalCursoLista);
        $this->modalCursoMarcados = array_values(array_unique(array_merge(
            array_map('intval', $this->modalCursoMarcados),
            $ids
        )));
    }

    public function modalCursoQuitarVisibles(): void
    {
        $vis = array_flip(array_map(fn (array $r) => (int) $r['id'], $this->modalCursoLista));
        $this->modalCursoMarcados = array_values(array_filter(
            array_map('intval', $this->modalCursoMarcados),
            fn (int $id) => ! isset($vis[$id])
        ));
    }

    public function removeCurso(int $id): void
    {
        $this->cursosSeleccionados = array_values(
            array_filter($this->cursosSeleccionados, fn (array $c) => (int) $c['id'] !== $id)
        );
        $this->pdfTurnoClase = null;
    }

    public function quitarTodosCursos(): void
    {
        $this->cursosSeleccionados = [];
        $this->pdfTurnoClase = null;
    }

    public function abrirModalProfesor(): void
    {
        $this->modalProfesorAbierto = true;
        $this->modalProfesorFiltro = '';
        $this->modalProfesorMarcados = array_map(fn (array $d) => (int) $d['id'], $this->profesoresSeleccionados);
        $this->recargarModalProfesorLista();
    }

    public function cerrarModalProfesor(): void
    {
        $this->modalProfesorAbierto = false;
    }

    public function aplicarModalProfesor(): void
    {
        $labelsPorId = collect($this->modalProfesorLista)->keyBy('id');
        $prev = collect($this->profesoresSeleccionados)->keyBy('id');
        $out = [];
        foreach (array_unique(array_map('intval', $this->modalProfesorMarcados)) as $id) {
            if ($id <= 0) {
                continue;
            }
            $fromLista = $labelsPorId->get($id);
            if ($fromLista !== null) {
                $out[] = ['id' => $id, 'label' => (string) $fromLista['label']];

                continue;
            }
            $fromPrev = $prev->get($id);
            if ($fromPrev !== null) {
                $out[] = ['id' => $id, 'label' => (string) $fromPrev['label']];
            }
        }
        $this->profesoresSeleccionados = $out;
        $this->pdfTurnoClase = null;
        $this->modalProfesorAbierto = false;
    }

    public function modalProfesorSeleccionarTodosVisibles(): void
    {
        $ids = array_map(fn (array $r) => (int) $r['id'], $this->modalProfesorLista);
        $this->modalProfesorMarcados = array_values(array_unique(array_merge(
            array_map('intval', $this->modalProfesorMarcados),
            $ids
        )));
    }

    public function modalProfesorQuitarVisibles(): void
    {
        $vis = array_flip(array_map(fn (array $r) => (int) $r['id'], $this->modalProfesorLista));
        $this->modalProfesorMarcados = array_values(array_filter(
            array_map('intval', $this->modalProfesorMarcados),
            fn (int $id) => ! isset($vis[$id])
        ));
    }

    public function removeProfesor(int $id): void
    {
        $this->profesoresSeleccionados = array_values(
            array_filter($this->profesoresSeleccionados, fn (array $d) => (int) $d['id'] !== $id)
        );
        $this->pdfTurnoClase = null;
    }

    public function quitarTodosProfesores(): void
    {
        $this->profesoresSeleccionados = [];
        $this->pdfTurnoClase = null;
    }

    public function pdfUrl(): ?string
    {
        $extra = [];
        if ($this->pdfTurnoClase !== null && $this->pdfTurnoClase > 0) {
            $extra['turno'] = $this->pdfTurnoClase;
        }

        if ($this->modo === 'curso' && $this->cursosSeleccionados !== []) {
            $ids = implode(',', array_map(fn (array $c) => (int) $c['id'], $this->cursosSeleccionados));

            return route('horarios.pdf.curso', array_merge(['cursos' => $ids], $extra));
        }
        if ($this->modo === 'profesor' && $this->profesoresSeleccionados !== []) {
            $ids = implode(',', array_map(fn (array $d) => (int) $d['id'], $this->profesoresSeleccionados));

            return route('horarios.pdf.profesor', array_merge(['profesores' => $ids], $extra));
        }

        return null;
    }

    /**
     * @return Collection<int, Curso>
     */
    public function cursos(): Collection
    {
        $ctx = schoolCtx();

        return Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'c', 's', 'idTurnoClase']);
    }

    /**
     * @return Collection<int, object{id:int, label:string}>
     */
    public function profesores(): Collection
    {
        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);

        return DB::table('profesores as p')
            ->where(function ($w) {
                $w->whereNull('p.IdTipoProf')->orWhere('p.IdTipoProf', '<>', 1);
            })
            ->whereExists(function ($q) use ($idNivel, $ctx) {
                $q->selectRaw('1')
                    ->from('ppc')
                    ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
                    ->whereColumn('ppc.idProfesor', 'p.id')
                    ->where('m.idNivel', $idNivel)
                    ->where('m.idTerlec', (int) $ctx->idTerlec);
            })
            ->orderBy('p.apellido')
            ->orderBy('p.nombre')
            ->get(['p.id', 'p.apellido', 'p.nombre'])
            ->map(fn ($r) => (object) [
                'id' => (int) $r->id,
                'label' => trim(((string) $r->apellido).', '.((string) $r->nombre)),
            ]);
    }

    private function recargarModalCursoLista(): void
    {
        $lista = $this->cursos()->map(fn (Curso $c) => [
            'id' => (int) $c->Id,
            'label' => $c->nombreParaListado(),
        ])->values()->all();

        $f = mb_strtolower(trim($this->modalCursoFiltro));
        if ($f !== '') {
            $lista = array_values(array_filter(
                $lista,
                fn (array $c) => str_contains(mb_strtolower((string) ($c['label'] ?? '')), $f)
            ));
        }

        $this->modalCursoLista = $lista;
    }

    private function recargarModalProfesorLista(): void
    {
        $lista = $this->profesores()->map(fn ($p) => [
            'id' => (int) $p->id,
            'label' => (string) $p->label,
        ])->values()->all();

        $f = mb_strtolower(trim($this->modalProfesorFiltro));
        if ($f !== '') {
            $lista = array_values(array_filter(
                $lista,
                fn (array $p) => str_contains(mb_strtolower((string) ($p['label'] ?? '')), $f)
            ));
        }

        $this->modalProfesorLista = $lista;
    }

    public function render()
    {
        $consultaSqlImpresionCurso = '';
        // if ($this->modo === 'curso' && count($this->cursosSeleccionados) > 0) {
        //     $consultaSqlImpresionCurso = HorariosProfesores::textoDepuracionSqlImpresionHorarioCurso((int) $this->cursosSeleccionados[0]['id']);
        // }

        return view('livewire.horarios.horarios-impresion-index', [
            'cursos' => $this->cursos(),
            'profesores' => $this->profesores(),
            'pdfUrl' => $this->pdfUrl(),
            'turnosPdf' => HorariosProfesores::turnosActivos(),
            'consultaSqlImpresionCurso' => $consultaSqlImpresionCurso,
            'cantidadCursosSeleccionados' => count($this->cursosSeleccionados),
            'cantidadProfesoresSeleccionados' => count($this->profesoresSeleccionados),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Impresión de horarios']);
    }
}
