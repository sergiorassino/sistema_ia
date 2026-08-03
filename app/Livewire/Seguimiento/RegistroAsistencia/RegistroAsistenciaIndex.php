<?php

namespace App\Livewire\Seguimiento\RegistroAsistencia;

use App\Models\Curso;
use App\Support\Listados\ListadoEstudiantesFormatoMes;
use App\Support\PermisosIaCatalog;
use App\Support\RegistroAsistencia\RegistroAsistenciaCatalog;
use Illuminate\Support\Collection;
use Livewire\Component;

class RegistroAsistenciaIndex extends Component
{
    /** @var list<array{id:int, label:string}> */
    public array $cursosSeleccionados = [];

    public int $mes = 0;

    public bool $modalCursoAbierto = false;

    public string $modalCursoFiltro = '';

    /** @var list<array{id:int, label:string}> */
    public array $modalCursoLista = [];

    /** @var list<int|string> */
    public array $modalCursoMarcados = [];

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::REGISTRO_ASISTENCIA), 403, 'Sin permiso para el Registro de Asistencia.');

        $this->mes = (int) now()->month;
        $this->cursosSeleccionados = [];
    }

    public function updatedModalCursoFiltro(): void
    {
        if ($this->modalCursoAbierto) {
            $this->recargarModalCursoLista();
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
    }

    public function quitarTodosCursos(): void
    {
        $this->cursosSeleccionados = [];
    }

    public function puedeGenerarPdf(): bool
    {
        return $this->cursosSeleccionados !== []
            && ListadoEstudiantesFormatoMes::normalizarMes($this->mes) >= 1;
    }

    /** @return Collection<int, Curso> */
    private function cursosDelContexto(): Collection
    {
        return Curso::query()
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden', 'c', 's']);
    }

    private function recargarModalCursoLista(): void
    {
        $lista = $this->cursosDelContexto()->map(fn (Curso $c) => [
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

    public function render()
    {
        $cursos = $this->cursosDelContexto();
        $cantidadSeleccionados = count($this->cursosSeleccionados);
        $implementacion = tenantRegistroAsistenciaImplementacion();
        $pdfUrl = null;

        if ($this->puedeGenerarPdf()) {
            $ids = collect($this->cursosSeleccionados)
                ->map(fn (array $c) => (int) $c['id'])
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            $pdfUrl = route('seguimiento.registro-asistencia.pdf', [
                'cursos' => $ids->implode(','),
                'mes' => ListadoEstudiantesFormatoMes::normalizarMes($this->mes),
            ]);
        }

        return view('livewire.seguimiento.registro-asistencia.index', [
            'cursos' => $cursos,
            'puedeGenerarPdf' => $this->puedeGenerarPdf(),
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'pdfUrl' => $pdfUrl,
            'meses' => ListadoEstudiantesFormatoMes::opcionesSelector(),
            'implementacionEtiqueta' => RegistroAsistenciaCatalog::etiqueta($implementacion),
            'esConDatos' => RegistroAsistenciaCatalog::esConDatos($implementacion),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Registro de Asistencia']);
    }
}
