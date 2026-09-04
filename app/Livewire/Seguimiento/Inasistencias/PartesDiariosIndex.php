<?php

namespace App\Livewire\Seguimiento\Inasistencias;

use App\Models\Curso;
use App\Support\HorariosProfesores;
use App\Support\PermisosIaCatalog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class PartesDiariosIndex extends Component
{
    /** @var list<array{id:int, label:string}> */
    public array $cursosSeleccionados = [];

    /** ID de turnos_clase; vacío = todos los turnos de cada curso. */
    public ?int $turnoElegido = null;

    /** Fecha del impreso y base para el día de la semana del horario (Y-m-d) */
    public string $fecha = '';

    public bool $modalCursoAbierto = false;

    public string $modalCursoFiltro = '';

    /** @var list<array{id:int, label:string}> */
    public array $modalCursoLista = [];

    /** @var list<int|string> */
    public array $modalCursoMarcados = [];

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::PARTE_DIARIO_PRECEPTOR), 403, 'Sin permiso para el parte diario del preceptor.');

        $this->fecha = now()->format('Y-m-d');
        $this->cursosSeleccionados = [];
    }

    public function updatedModalCursoFiltro(): void
    {
        if ($this->modalCursoAbierto) {
            $this->recargarModalCursoLista();
        }
    }

    public function updatedTurnoElegido(mixed $value): void
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            $this->turnoElegido = null;
        } else {
            $this->turnoElegido = (int) $value;
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

    /** Etiqueta del día de la semana según {@see $fecha} (ISO, mismo criterio que el PDF). */
    private function etiquetaDiaDesdeFecha(): ?string
    {
        if ($this->fecha === '') {
            return null;
        }

        try {
            $d = (int) Carbon::createFromFormat('Y-m-d', $this->fecha)->startOfDay()->dayOfWeekIso;

            return HorariosProfesores::DIAS[$d] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function fechaValidaParaPdf(): bool
    {
        if ($this->fecha === '') {
            return false;
        }

        try {
            Carbon::createFromFormat('Y-m-d', $this->fecha)->startOfDay();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function puedeGenerarPdf(): bool
    {
        if ($this->cursosSeleccionados === [] || ! $this->fechaValidaParaPdf()) {
            return false;
        }

        return $this->cantidadHojasPdf() > 0;
    }

    /**
     * Hojas que saldrían con la selección actual (curso × jornada).
     */
    public function cantidadHojasPdf(): int
    {
        $turno = $this->turnoElegido !== null && $this->turnoElegido > 0 ? $this->turnoElegido : null;
        $n = 0;
        foreach ($this->cursosConTurnoParaPdf() as $curso) {
            $n += count(HorariosProfesores::turnosParaParteDiario($curso, $turno));
        }

        return $n;
    }

    /**
     * Cursos seleccionados que tienen al menos un turno a imprimir.
     *
     * @return Collection<int, Curso>
     */
    private function cursosConTurnoParaPdf(): Collection
    {
        $ids = array_flip(array_map(fn (array $c) => (int) $c['id'], $this->cursosSeleccionados));
        $turno = $this->turnoElegido !== null && $this->turnoElegido > 0 ? $this->turnoElegido : null;

        return $this->cursosDelContexto()
            ->filter(function (Curso $c) use ($ids, $turno) {
                if (! isset($ids[(int) $c->Id])) {
                    return false;
                }

                return HorariosProfesores::turnosParaParteDiario($c, $turno) !== [];
            })
            ->values();
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

    /**
     * Turnos de clase del establecimiento (selector de filtro del parte).
     *
     * @return list<int>
     */
    private function turnosParaSelector(): array
    {
        return HorariosProfesores::turnosActivos();
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
        $turnosSelector = $this->turnosParaSelector();
        $mostrarSelectorTurno = count($turnosSelector) > 1;
        $cantidadSeleccionados = count($this->cursosSeleccionados);
        $cantidadHojas = $this->cantidadHojasPdf();
        $cursosOmitidosPorTurno = 0;
        if ($this->turnoElegido !== null && $this->turnoElegido > 0 && $cantidadSeleccionados > 0) {
            $cursosOmitidosPorTurno = $cantidadSeleccionados - $this->cursosConTurnoParaPdf()->count();
        }

        $pdfUrl = null;
        if ($this->puedeGenerarPdf()) {
            $ids = collect($this->cursosSeleccionados)
                ->map(fn (array $c) => (int) $c['id'])
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            $pdfUrl = route('seguimiento.partes-diarios.pdf', array_filter([
                'cursos' => $ids->implode(','),
                'fecha' => $this->fecha !== '' ? $this->fecha : null,
                'turnoElegido' => $this->turnoElegido !== null && $this->turnoElegido > 0
                    ? $this->turnoElegido
                    : null,
            ]));
        }

        return view('livewire.seguimiento.inasistencias.partes-diarios-index', [
            'cursos' => $cursos,
            'turnosSelector' => $turnosSelector,
            'mostrarSelectorTurno' => $mostrarSelectorTurno,
            'etiquetaDiaFecha' => $this->etiquetaDiaDesdeFecha(),
            'puedeGenerarPdf' => $this->puedeGenerarPdf(),
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'cantidadHojas' => $cantidadHojas,
            'cursosOmitidosPorTurno' => $cursosOmitidosPorTurno,
            'pdfUrl' => $pdfUrl,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Parte diario del preceptor']);
    }
}
