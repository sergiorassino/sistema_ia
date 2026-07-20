<?php

namespace App\Livewire\Examenes;

use App\Livewire\Examenes\Concerns\RequiresPermisoExamenes;
use App\Support\Examenes\ActaVolantePrevios;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Impresión de actas volantes de examen (previas): listado con checkboxes y PDF por acta seleccionada.
 */
class ActaVolantePreviosIndex extends Component
{
    use RequiresPermisoExamenes;

    /** Claves de acta (`idMatPlan:condAdeuda`) marcadas para imprimir. */
    public array $actasSeleccionadas = [];

    public int $prepTick = 0;

    public function mount(): void
    {
        $this->actasSeleccionadas = [];
    }

    public function updatedActasSeleccionadas(): void
    {
        $this->normalizarActasSeleccionadas();
    }

    public function seleccionarTodasActas(): void
    {
        $this->actasSeleccionadas = $this->actasPendientes()
            ->pluck('clave')
            ->map(fn ($clave) => (string) $clave)
            ->all();
    }

    public function quitarTodasActas(): void
    {
        $this->actasSeleccionadas = [];
    }

    protected function normalizarActasSeleccionadas(): void
    {
        $allowed = $this->actasPendientes()->pluck('clave')->map(fn ($c) => (string) $c)->all();

        $this->actasSeleccionadas = collect($this->actasSeleccionadas)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($clave) => $clave !== '' && in_array($clave, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    public function puedeGenerarPdf(): bool
    {
        return collect($this->actasSeleccionadas)
            ->filter(fn ($v) => trim((string) $v) !== '')
            ->isNotEmpty();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function actasPendientes()
    {
        $ctx = schoolCtx();

        return ActaVolantePrevios::actasPendientes((int) $ctx->idNivel);
    }

    public function render()
    {
        $ctx = schoolCtx();
        $preparacionLista = $ctx->isValid()
            && MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion(MateriasAdeudadasPreparacion::MODULO_ACTA_VOLANTE);

        $actas = collect();
        $cantidadSeleccionadas = 0;
        $pdfUrl = null;
        $modalidadCursoSeccion = ActaVolantePrevios::esModalidadCursoSeccion();

        if ($preparacionLista) {
            $actas = $this->actasPendientes();
            $cantidadSeleccionadas = collect($this->actasSeleccionadas)
                ->filter(fn ($v) => trim((string) $v) !== '')
                ->count();

            if ($this->puedeGenerarPdf()) {
                $claves = collect($this->actasSeleccionadas)
                    ->map(fn ($v) => trim((string) $v))
                    ->filter()
                    ->unique()
                    ->values();

                $pdfUrl = route('examenes.acta-volante-previos.pdf', [
                    'actas' => $claves->implode(','),
                ]);
            }
        }

        return view('livewire.examenes.acta-volante-previos', compact(
            'actas',
            'cantidadSeleccionadas',
            'pdfUrl',
            'preparacionLista',
            'modalidadCursoSeccion',
        ))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Actas volantes de examen']);
    }

    #[On('materias-adeudadas-preparacion-confirmada')]
    public function onPreparacionConfirmada(string $modulo): void
    {
        if ($modulo === MateriasAdeudadasPreparacion::MODULO_ACTA_VOLANTE) {
            $this->prepTick++;
        }
    }
}
