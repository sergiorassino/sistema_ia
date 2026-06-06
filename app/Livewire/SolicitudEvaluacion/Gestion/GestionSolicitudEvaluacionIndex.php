<?php

namespace App\Livewire\SolicitudEvaluacion\Gestion;

use App\Livewire\SolicitudEvaluacion\Concerns\RequiresPermisoGestionSolicitudEvaluacion;
use App\Support\SolicitudEvaluacion\SolicitudEvaluacionConsulta;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

class GestionSolicitudEvaluacionIndex extends Component
{
    use RequiresPermisoGestionSolicitudEvaluacion;
    use WithPagination;

    private const POR_PAGINA = 25;

    public bool $mostrarHistorial = false;

    public string $filtroFecha = '';

    public int|string $filtroIdCurso = '';

    public int|string $filtroIdMateria = '';

    public bool $showDeleteConfirm = false;

    public ?int $deleteId = null;

    public string $deleteInfo = '';

    /** @var array<string, array{except?: mixed, as?: string}> */
    protected $queryString = [
        'mostrarHistorial' => ['except' => false],
        'filtroFecha' => ['except' => ''],
        'filtroIdCurso' => ['except' => '', 'as' => 'curso'],
        'filtroIdMateria' => ['except' => '', 'as' => 'materia'],
    ];

    public function alternarHistorial(): void
    {
        $this->mostrarHistorial = ! $this->mostrarHistorial;
        $this->resetPage();
    }

    public function updatedFiltroFecha(mixed $value): void
    {
        $this->filtroFecha = is_scalar($value) ? trim((string) $value) : '';
        $this->resetPage();
    }

    public function updatedFiltroIdCurso(mixed $value): void
    {
        $this->filtroIdCurso = is_scalar($value) ? (string) $value : '';
        $this->filtroIdMateria = '';
        $this->resetPage();
    }

    public function updatedFiltroIdMateria(mixed $value): void
    {
        $this->filtroIdMateria = is_scalar($value) ? (string) $value : '';
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset('filtroFecha', 'filtroIdCurso', 'filtroIdMateria');
        $this->resetPage();
    }

    /** @return array{fecha: string, idCurso: int|string, idMateria: int|string} */
    private function filtrosBusqueda(): array
    {
        return [
            'fecha' => $this->filtroFecha,
            'idCurso' => $this->filtroIdCurso,
            'idMateria' => $this->filtroIdMateria,
        ];
    }

    public function confirmDelete(int $id): void
    {
        $evaluacion = SolicitudEvaluacionConsulta::evaluacionEnContexto($id);
        abort_if($evaluacion === null, 404);

        $curso = $evaluacion->curso?->nombreParaListado() ?? 'Curso #'.$evaluacion->idCurso;
        $fecha = $evaluacion->fecheval?->format('d/m/Y') ?? '—';
        $materias = SolicitudEvaluacionConsulta::etiquetasMateriaParaEvaluaciones(collect([$evaluacion]));
        $materia = $materias[(int) $evaluacion->idMateria] ?? 'Materia #'.$evaluacion->idMateria;

        $this->deleteId = (int) $evaluacion->Id;
        $this->deleteInfo = "¿Confirma borrar la evaluación de {$materia} ({$curso}, {$fecha})?";
        $this->showDeleteConfirm = true;
    }

    public function delete(): void
    {
        $key = 'gestion-solicitud-evaluacion:delete:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('error', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showDeleteConfirm = false;
            $this->reset('deleteId', 'deleteInfo');

            return;
        }
        RateLimiter::hit($key, 60);

        if ($this->deleteId) {
            $evaluacion = SolicitudEvaluacionConsulta::evaluacionEnContexto((int) $this->deleteId);
            abort_if($evaluacion === null, 404);

            $evaluacion->delete();

            session()->flash('success', 'Evaluación borrada.');
        }

        $this->showDeleteConfirm = false;
        $this->reset('deleteId', 'deleteInfo');
    }

    public function render()
    {
        $hoy = now()->toDateString();
        $filtros = $this->filtrosBusqueda();
        $filtrosActivos = $this->filtroFecha !== ''
            || (int) $this->filtroIdCurso > 0
            || (int) $this->filtroIdMateria > 0;

        $paginadas = null;
        if ($this->mostrarHistorial) {
            $paginadas = SolicitudEvaluacionConsulta::evaluacionesHistorialPaginadas(self::POR_PAGINA, $filtros);
            $agrupadas = SolicitudEvaluacionConsulta::agruparEvaluacionesPorFecha(collect($paginadas->items()));
        } else {
            $agrupadas = SolicitudEvaluacionConsulta::evaluacionesAgrupadasPorFecha($hoy, $filtros);
        }

        $todas = $agrupadas->flatten(1);
        $etiquetasMateria = SolicitudEvaluacionConsulta::etiquetasMateriaParaEvaluaciones($todas);

        $cursos = SolicitudEvaluacionConsulta::cursosParaSelector(false);
        $idCursoFiltro = (int) $this->filtroIdCurso;
        $materiasFiltro = SolicitudEvaluacionConsulta::materiasParaSelectorGestion(
            $idCursoFiltro > 0 ? $idCursoFiltro : null,
        );

        return view('livewire.solicitud-evaluacion.gestion.index', [
            'agrupadas' => $agrupadas,
            'etiquetasMateria' => $etiquetasMateria,
            'paginadas' => $paginadas,
            'mostrarHistorial' => $this->mostrarHistorial,
            'cursos' => $cursos,
            'materiasFiltro' => $materiasFiltro,
            'filtrosActivos' => $filtrosActivos,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Gestión de Solicitudes de Evaluación']);
    }
}
