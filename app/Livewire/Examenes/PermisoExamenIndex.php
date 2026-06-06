<?php

namespace App\Livewire\Examenes;

use App\Livewire\Examenes\Concerns\RequiresPermisoExamenes;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use App\Support\Examenes\PermisoExamen;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Permiso de examen por alumno: preparación, selección y PDF (una hoja por estudiante).
 */
class PermisoExamenIndex extends Component
{
    use RequiresPermisoExamenes;

    /** @var list<int> */
    public array $alumnosSeleccionados = [];

    /** Filtro por apellido, nombre o DNI (mín. 2 caracteres). */
    public string $buscar = '';

    public int $numeroPermisoInicio = 1;

    /** Fecha para el PDF (input type=date, Y-m-d); no se persiste en BD. */
    public string $fechaPdf = '';

    public int $prepTick = 0;

    public function mount(): void
    {
        $this->alumnosSeleccionados = [];
        $this->fechaPdf = now()->format('Y-m-d');
    }

    public function updatedAlumnosSeleccionados(): void
    {
        $this->normalizarAlumnosSeleccionados();
    }

    public function seleccionarTodosAlumnos(): void
    {
        $idsVisibles = $this->estudiantesEnTabla()
            ->pluck('idLegajos')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($this->tieneFiltroBusqueda()) {
            $this->alumnosSeleccionados = collect($this->alumnosSeleccionados)
                ->merge($idsVisibles)
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values()
                ->all();
        } else {
            $this->alumnosSeleccionados = $idsVisibles;
        }

        $this->normalizarAlumnosSeleccionados();
    }

    public function quitarTodosAlumnos(): void
    {
        $this->alumnosSeleccionados = [];
    }

    protected function normalizarAlumnosSeleccionados(): void
    {
        $allowed = $this->estudiantesPendientes()
            ->pluck('idLegajos')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->alumnosSeleccionados = collect($this->alumnosSeleccionados)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0 && in_array($id, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    public function puedeGenerarPdf(): bool
    {
        return collect($this->alumnosSeleccionados)->filter(fn ($id) => (int) $id > 0)->isNotEmpty()
            && trim($this->fechaPdf) !== '';
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function estudiantesPendientes()
    {
        $ctx = schoolCtx();

        return PermisoExamen::estudiantes((int) $ctx->idNivel);
    }

    /**
     * Listado visible en la tabla (con filtro de búsqueda si aplica).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function estudiantesEnTabla()
    {
        return PermisoExamen::filtrarEstudiantes($this->estudiantesPendientes(), $this->buscar);
    }

    protected function tieneFiltroBusqueda(): bool
    {
        return PermisoExamen::terminoBusqueda($this->buscar) !== '';
    }

    public function render()
    {
        $ctx = schoolCtx();
        $preparacionLista = $ctx->isValid()
            && MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion(
                MateriasAdeudadasPreparacion::MODULO_PERMISO_EXAMEN,
            );

        $estudiantes = collect();
        $totalEstudiantes = 0;
        $filtrandoBusqueda = false;
        $cantidadSeleccionados = 0;
        $puedeGenerarPdf = false;
        $etiquetaTurno = null;

        if ($preparacionLista) {
            $todosEstudiantes = $this->estudiantesPendientes();
            $estudiantes = PermisoExamen::filtrarEstudiantes($todosEstudiantes, $this->buscar);
            $totalEstudiantes = $todosEstudiantes->count();
            $filtrandoBusqueda = $this->tieneFiltroBusqueda();
            $cantidadSeleccionados = count($this->alumnosSeleccionados);
            $puedeGenerarPdf = $this->puedeGenerarPdf();

            $datosPrep = MateriasAdeudadasPreparacion::datosConfirmadosParaRestaurar(
                $ctx,
                MateriasAdeudadasPreparacion::MODULO_PERMISO_EXAMEN,
            );
            if ($datosPrep !== null) {
                $etiquetaTurno = PermisoExamen::etiquetaTurnoExamen(
                    $datosPrep['idTurno'],
                    MateriasAdeudadasPreparacion::anoTerlec($datosPrep['idTerlec']),
                );
            }
        }

        return view('livewire.examenes.permiso-examen', compact(
            'estudiantes',
            'totalEstudiantes',
            'filtrandoBusqueda',
            'cantidadSeleccionados',
            'puedeGenerarPdf',
            'preparacionLista',
            'etiquetaTurno',
        ))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Permiso de examen']);
    }

    #[On('materias-adeudadas-preparacion-confirmada')]
    public function onPreparacionConfirmada(string $modulo): void
    {
        if ($modulo === MateriasAdeudadasPreparacion::MODULO_PERMISO_EXAMEN) {
            $this->prepTick++;
        }
    }
}
