<?php

namespace App\Livewire\Horarios;

use App\Models\Curso;
use App\Support\Horarios\ProfesoresPresentesConsulta;
use App\Support\HorariosProfesores;
use Illuminate\Support\Collection;
use Livewire\Component;

class ProfesoresPresentesIndex extends Component
{
    public int $dia = 1;

    public string $horaInicio = '08:00';

    public string $horaFin = '13:00';

    /** @var list<array{id:int, label:string}> */
    public array $cursosSeleccionados = [];

    public bool $emitido = false;

    public bool $modalCursoAbierto = false;

    public string $modalCursoFiltro = '';

    /** @var list<array{id:int, label:string}> */
    public array $modalCursoLista = [];

    /** @var list<int|string> */
    public array $modalCursoMarcados = [];

    public function mount(): void
    {
        $activos = HorariosProfesores::diasActivos();
        $hoy = (int) now()->dayOfWeekIso;
        if (in_array($hoy, $activos, true)) {
            $this->dia = $hoy;
        } elseif ($activos !== []) {
            $this->dia = (int) $activos[0];
        }
        $this->cursosSeleccionados = [];
    }

    public function updatedDia(): void
    {
        $this->emitido = false;
    }

    public function updatedHoraInicio(): void
    {
        $this->emitido = false;
    }

    public function updatedHoraFin(): void
    {
        $this->emitido = false;
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
        $this->emitido = false;
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
        $this->emitido = false;
    }

    public function quitarTodosCursos(): void
    {
        $this->cursosSeleccionados = [];
        $this->emitido = false;
    }

    public function emitirListado(): void
    {
        $error = $this->mensajeValidacion();
        if ($error !== null) {
            $this->emitido = false;
            $this->dispatch('se-swal-error', mensaje: $error);

            return;
        }

        $this->emitido = true;
    }

    public function puedeEmitir(): bool
    {
        return $this->mensajeValidacion() === null;
    }

    private function mensajeValidacion(): ?string
    {
        $dias = HorariosProfesores::diasActivos();
        if (! in_array($this->dia, $dias, true) && ! isset(HorariosProfesores::DIAS[$this->dia])) {
            return 'Elija un día de la semana.';
        }
        $inicio = ProfesoresPresentesConsulta::minutosDesdeHora($this->horaInicio);
        $fin = ProfesoresPresentesConsulta::minutosDesdeHora($this->horaFin);
        if ($inicio === null || $fin === null) {
            return 'Indique horario de inicio y de fin.';
        }
        if ($fin <= $inicio) {
            return 'El horario de fin debe ser posterior al de inicio.';
        }
        if ($this->cursosSeleccionados === []) {
            return 'Elija al menos un curso o sección.';
        }

        return null;
    }

    /** @return Collection<int, Curso> */
    private function cursosDelContexto(): Collection
    {
        return Curso::query()
            ->with(['curplan', 'turnoClase'])
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
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

    /**
     * @return array<string, mixed>
     */
    private function resultado(): array
    {
        if (! $this->emitido || $this->mensajeValidacion() !== null) {
            return [
                'ok' => false,
                'error' => null,
                'filas' => [],
                'cantidadDocentes' => 0,
                'cursosResumen' => '',
            ];
        }

        $ids = array_map(fn (array $c) => (int) $c['id'], $this->cursosSeleccionados);

        return ProfesoresPresentesConsulta::consultar($this->dia, $this->horaInicio, $this->horaFin, $ids);
    }

    public function render()
    {
        $cursos = $this->cursosDelContexto();
        $resultado = $this->resultado();
        $pdfUrl = null;
        if ($this->emitido && ($resultado['ok'] ?? false)) {
            $ids = implode(',', array_map(fn (array $c) => (int) $c['id'], $this->cursosSeleccionados));
            $pdfUrl = route('horarios.profesores-presentes.pdf', [
                'dia' => $this->dia,
                'horaInicio' => $this->horaInicio,
                'horaFin' => $this->horaFin,
                'cursos' => $ids,
            ]);
        }

        $dias = [];
        foreach (HorariosProfesores::diasActivos() as $num) {
            $dias[$num] = HorariosProfesores::DIAS[$num] ?? ('Día '.$num);
        }
        if ($dias === []) {
            $dias = HorariosProfesores::DIAS;
        }

        return view('livewire.horarios.profesores-presentes-index', [
            'cursos' => $cursos,
            'dias' => $dias,
            'resultado' => $resultado,
            'pdfUrl' => $pdfUrl,
            'cantidadSeleccionados' => count($this->cursosSeleccionados),
            'puedeEmitir' => $this->puedeEmitir(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Profesores presentes']);
    }
}
