<?php

namespace App\Livewire\Examenes;

use App\Livewire\Examenes\Concerns\RequiresPermisoExamenes;
use App\Support\Examenes\MateriasAdeudadasAlumnosListado;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class MateriasAdeudadasGestionIndex extends Component
{
    use RequiresPermisoExamenes;
    use WithPagination;

    public const POR_PAGINA = MateriasAdeudadasAlumnosListado::POR_PAGINA;

    public string $buscar = '';

    /** regulares | historial — ver MateriasAdeudadasAlumnosListado::AMBITO_* */
    public string $filtroAlumnos = MateriasAdeudadasAlumnosListado::AMBITO_REGULARES_CICLO;

    /** Incrementa al confirmar el panel hijo para refrescar el listado de alumnos. */
    public int $prepTick = 0;

    /** @var array<string, array{except?: mixed, as?: string}> */
    protected $queryString = [
        'buscar' => ['except' => ''],
        'filtroAlumnos' => ['except' => MateriasAdeudadasAlumnosListado::AMBITO_REGULARES_CICLO, 'as' => 'ambito'],
    ];

    public function mount(): void
    {
        if (trim($this->buscar) === '') {
            $this->buscar = MateriasAdeudadasAlumnosListado::buscarRetornoListado();
        }

        $this->filtroAlumnos = MateriasAdeudadasAlumnosListado::normalizeAmbito(
            $this->filtroAlumnos !== MateriasAdeudadasAlumnosListado::AMBITO_REGULARES_CICLO
                ? $this->filtroAlumnos
                : MateriasAdeudadasAlumnosListado::ambitoRetornoListado()
        );

        MateriasAdeudadasAlumnosListado::persistirBuscarListado($this->buscar);
        MateriasAdeudadasAlumnosListado::persistirAmbitoListado($this->filtroAlumnos);
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
        MateriasAdeudadasAlumnosListado::persistirBuscarListado($this->buscar);
    }

    public function updatedFiltroAlumnos(string $value): void
    {
        $this->filtroAlumnos = MateriasAdeudadasAlumnosListado::normalizeAmbito($value);
        $this->resetPage();
        MateriasAdeudadasAlumnosListado::persistirAmbitoListado($this->filtroAlumnos);
    }

    public function render()
    {
        $ctx = schoolCtx();
        $esSecundario = MateriasAdeudadasAlumnosListado::esNivelSecundario($ctx);
        $preparacionLista = $ctx->isValid()
            && MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion(MateriasAdeudadasPreparacion::MODULO_GESTION);

        $ambito = MateriasAdeudadasAlumnosListado::normalizeAmbito($this->filtroAlumnos);
        $alumnos = null;
        if ($esSecundario && $preparacionLista) {
            $alumnos = MateriasAdeudadasAlumnosListado::paginarAlumnos(
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec,
                $this->buscar !== '' ? $this->buscar : null,
                self::POR_PAGINA,
                $ambito,
            );
        }

        $totalAlumnos = $alumnos?->total() ?? 0;

        return view('livewire.examenes.materias-adeudadas-gestion', [
            'alumnos' => $alumnos,
            'totalAlumnos' => $totalAlumnos,
            'esSecundario' => $esSecundario,
            'preparacionLista' => $preparacionLista,
            'minCharsBusqueda' => MateriasAdeudadasAlumnosListado::MIN_CHARS_BUSQUEDA,
            'ambitoRegulares' => MateriasAdeudadasAlumnosListado::AMBITO_REGULARES_CICLO,
            'ambitoHistorial' => MateriasAdeudadasAlumnosListado::AMBITO_HISTORIAL,
            'esAmbitoRegulares' => $ambito === MateriasAdeudadasAlumnosListado::AMBITO_REGULARES_CICLO,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Gestión de materias adeudadas']);
    }

    #[On('materias-adeudadas-preparacion-confirmada')]
    public function onPreparacionConfirmada(string $modulo): void
    {
        if ($modulo === MateriasAdeudadasPreparacion::MODULO_GESTION) {
            $this->prepTick++;
        }
    }
}
