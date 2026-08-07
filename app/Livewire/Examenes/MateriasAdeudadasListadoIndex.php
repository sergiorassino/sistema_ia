<?php

namespace App\Livewire\Examenes;

use App\Livewire\Examenes\Concerns\RequiresPermisoExamenes;
use App\Support\Examenes\MateriasAdeudadasExporter;
use App\Support\Examenes\MateriasAdeudadasFiltros;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use Livewire\Attributes\On;
use Livewire\Component;

class MateriasAdeudadasListadoIndex extends Component
{
    use RequiresPermisoExamenes;

    public int $prepTick = 0;

    /** @see MateriasAdeudadasFiltros */
    public string $agrupar = MateriasAdeudadasFiltros::AGRUPAR_ESTUDIANTE;

    /** regulares|todos — por defecto solo regulares del ciclo del contexto */
    public string $filtroAlumnos = MateriasAdeudadasFiltros::ALUMNOS_REGULARES_CICLO;

    /** PR|EQ|TM|'' */
    public string $filtroCondicion = '';

    /** si|no|'' */
    public string $filtroInscri = '';

    public function updatedAgrupar(mixed $value): void
    {
        $this->agrupar = MateriasAdeudadasFiltros::normalizeAgrupar(is_string($value) ? $value : null);
    }

    public function updatedFiltroAlumnos(mixed $value): void
    {
        $this->filtroAlumnos = MateriasAdeudadasFiltros::normalizeAlumnos(is_string($value) ? $value : null);
    }

    public function updatedFiltroCondicion(mixed $value): void
    {
        $norm = MateriasAdeudadasFiltros::normalizeCondicion(is_string($value) ? $value : null);
        $this->filtroCondicion = $norm ?? '';
    }

    public function updatedFiltroInscri(mixed $value): void
    {
        $norm = MateriasAdeudadasFiltros::normalizeInscri(is_string($value) ? $value : null);
        $this->filtroInscri = $norm ?? '';
    }

    public function render()
    {
        $ctx = schoolCtx();
        $preparacionLista = $ctx->isValid()
            && MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion(MateriasAdeudadasPreparacion::MODULO_LISTADO);

        $filas = [];
        $bloques = [];
        $ambitoAlumnos = MateriasAdeudadasFiltros::normalizeAlumnos($this->filtroAlumnos);

        if ($preparacionLista) {
            $filas = MateriasAdeudadasExporter::filas(
                (int) $ctx->idNivel,
                $this->filtroCondicion !== '' ? $this->filtroCondicion : null,
                $this->filtroInscri !== '' ? $this->filtroInscri : null,
                $ambitoAlumnos,
                (int) $ctx->idTerlec,
            );
            $bloques = MateriasAdeudadasExporter::agrupar($filas, $this->agrupar);
        }

        $pdfParams = array_filter([
            'agrupar' => $this->agrupar,
            'alumnos' => $ambitoAlumnos,
            'condicion' => $this->filtroCondicion !== '' ? $this->filtroCondicion : null,
            'inscri' => $this->filtroInscri !== '' ? $this->filtroInscri : null,
        ], fn ($v) => $v !== null && $v !== '');

        return view('livewire.examenes.materias-adeudadas-listado', [
            'bloques' => $bloques,
            'totalFilas' => count($filas),
            'pdfUrl' => route('examenes.materias-adeudadas.pdf', $pdfParams),
            'preparacionLista' => $preparacionLista,
            'alumnosRegulares' => MateriasAdeudadasFiltros::ALUMNOS_REGULARES_CICLO,
            'alumnosTodos' => MateriasAdeudadasFiltros::ALUMNOS_TODOS,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Listado de materias adeudadas']);
    }

    #[On('materias-adeudadas-preparacion-confirmada')]
    public function onPreparacionConfirmada(string $modulo): void
    {
        if ($modulo === MateriasAdeudadasPreparacion::MODULO_LISTADO) {
            $this->prepTick++;
        }
    }
}
