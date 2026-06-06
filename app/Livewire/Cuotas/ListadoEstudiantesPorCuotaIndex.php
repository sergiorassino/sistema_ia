<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\FiltroComparacionNumerica;
use App\Support\Cuotas\GeneracionMasivaCuotasConsulta;
use App\Support\Cuotas\ListadoEstudiantesPorCuotaDatos;
use App\Support\PermisosCuotas;
use Livewire\Component;

/**
 * Filtros para imprimir el listado de estudiantes por cuota (PDF).
 */
class ListadoEstudiantesPorCuotaIndex extends Component
{
    public string $anoOp = '';

    /** 0 = sin año lectivo elegido */
    public int $idTerlecCuota = 0;

    /** 0 = todos los cursos del año actual */
    public int $idCurso = 0;

    /** 0 = todas las cuotas */
    public int $idCuota = 0;

    public string $importeOp = '';

    public string $importeValor = '';

    public string $pagadoOp = '';

    public string $pagadoValor = '';

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeListadoEstudiantesPorCuota(), 403);
    }

    public function getPdfUrlProperty(): string
    {
        if (! $this->puedeGenerarPdf()) {
            return '#';
        }

        return route('cuotas.listado-estudiantes-por-cuota.pdf', $this->parametrosPdf());
    }

    public function puedeGenerarPdf(): bool
    {
        try {
            ListadoEstudiantesPorCuotaDatos::normalizarFiltros($this->parametrosPdf());

            return true;
        } catch (\Illuminate\Validation\ValidationException) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parametrosPdf(): array
    {
        return [
            'ano_op' => $this->anoOp,
            'terlec' => $this->idTerlecCuota,
            'curso' => $this->idCurso,
            'cuota' => $this->idCuota,
            'importe_op' => $this->importeOp,
            'importe' => $this->importeValor,
            'pagado_op' => $this->pagadoOp,
            'pagado' => $this->pagadoValor,
        ];
    }

    public function render()
    {
        $ano = (int) schoolCtx()->terlecAno();

        return view('livewire.cuotas.listado-estudiantes-por-cuota', [
            'ano' => $ano,
            'terlecs' => ListadoEstudiantesPorCuotaDatos::terlecsParaSelector(),
            'cursos' => ListadoEstudiantesPorCuotaDatos::cursosAnoActualParaSelector(),
            'cuotas' => ListadoEstudiantesPorCuotaDatos::cuotasParaSelector(),
            'opcionesComparador' => FiltroComparacionNumerica::opcionesEtiquetas(),
            'pdfUrl' => $this->pdfUrl,
            'etiquetaCurso' => fn ($curso) => GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($curso),
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Listado de estudiantes por cuota — {$ano}"]);
    }
}
