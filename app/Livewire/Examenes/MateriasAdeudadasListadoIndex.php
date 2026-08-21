<?php

namespace App\Livewire\Examenes;

use App\Livewire\Examenes\Concerns\RequiresPermisoExamenes;
use App\Support\Examenes\MateriasAdeudadasExporter;
use App\Support\Examenes\MateriasAdeudadasFiltros;
use App\Support\Examenes\MateriasAdeudadasPorCurso;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use App\Support\Security\OpaqueRouteToken;
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

    public bool $modalAdeudadasCursoAbierto = false;

    public string $modalAdeudadasCursoFiltro = '';

    /** @var list<int|string> */
    public array $cursosMarcadosModal = [];

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

    public function abrirModalAdeudadasCurso(): void
    {
        $this->cursosMarcadosModal = [];
        $this->modalAdeudadasCursoFiltro = '';
        $this->modalAdeudadasCursoAbierto = true;
        $this->resetErrorBag('cursosMarcadosModal');
    }

    public function cerrarModalAdeudadasCurso(): void
    {
        $this->modalAdeudadasCursoAbierto = false;
        $this->cursosMarcadosModal = [];
        $this->modalAdeudadasCursoFiltro = '';
        $this->resetErrorBag('cursosMarcadosModal');
    }

    public function modalAdeudadasCursoSeleccionarTodosVisibles(): void
    {
        $idsVisibles = $this->idsCursosModalVisibles();
        $this->cursosMarcadosModal = array_values(array_unique(array_merge(
            array_map('intval', $this->cursosMarcadosModal),
            $idsVisibles,
        )));
    }

    public function modalAdeudadasCursoDesmarcarVisibles(): void
    {
        $vis = array_flip($this->idsCursosModalVisibles());
        $this->cursosMarcadosModal = array_values(array_filter(
            array_map('intval', $this->cursosMarcadosModal),
            static fn (int $id) => ! isset($vis[$id]),
        ));
    }

    public function render()
    {
        $ctx = schoolCtx();
        $preparacionLista = $ctx->isValid()
            && MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion(MateriasAdeudadasPreparacion::MODULO_LISTADO);

        $filas = [];
        $bloques = [];
        $ambitoAlumnos = MateriasAdeudadasFiltros::normalizeAlumnos($this->filtroAlumnos);
        $cursosModal = collect();
        $cursosModalLista = [];
        $pdfPorCursoUrl = null;
        $cantidadCursosMarcados = 0;

        if ($preparacionLista) {
            $filas = MateriasAdeudadasExporter::filas(
                (int) $ctx->idNivel,
                $this->filtroCondicion !== '' ? $this->filtroCondicion : null,
                $this->filtroInscri !== '' ? $this->filtroInscri : null,
                $ambitoAlumnos,
                (int) $ctx->idTerlec,
            );
            $bloques = MateriasAdeudadasExporter::agrupar($filas, $this->agrupar);

            if ($this->modalAdeudadasCursoAbierto) {
                $cursosModal = MateriasAdeudadasPorCurso::cursosDelContexto(
                    (int) $ctx->idNivel,
                    (int) $ctx->idTerlec,
                );
                $cursosModalLista = $this->filtrarCursosModalLista($cursosModal);

                $marcadosSet = array_flip(array_values(array_unique(array_filter(
                    array_map('intval', $this->cursosMarcadosModal),
                    static fn (int $id) => $id > 0,
                ))));
                $idsOk = $cursosModal
                    ->pluck('Id')
                    ->map(static fn ($id) => (int) $id)
                    ->filter(static fn (int $id) => isset($marcadosSet[$id]))
                    ->values()
                    ->all();
                $cantidadCursosMarcados = count($idsOk);
                if ($idsOk !== []) {
                    $pdfPorCursoUrl = route('examenes.materias-adeudadas.por-curso.pdf', [
                        'ref' => OpaqueRouteToken::forMateriasAdeudadasPorCurso($idsOk),
                    ]);
                }
            }
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
            'cursosModalLista' => $cursosModalLista,
            'pdfPorCursoUrl' => $pdfPorCursoUrl,
            'cantidadCursosMarcados' => $cantidadCursosMarcados,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Listado de materias adeudadas']);
    }

    #[On('materias-adeudadas-preparacion-confirmada')]
    public function onPreparacionConfirmada(string $modulo): void
    {
        if ($modulo === MateriasAdeudadasPreparacion::MODULO_LISTADO) {
            $this->prepTick++;
        }
    }

    /**
     * @return list<int>
     */
    private function idsCursosModalVisibles(): array
    {
        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return [];
        }

        $cursos = MateriasAdeudadasPorCurso::cursosDelContexto((int) $ctx->idNivel, (int) $ctx->idTerlec);

        return array_map(
            static fn (array $r) => (int) $r['id'],
            $this->filtrarCursosModalLista($cursos),
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Curso>  $cursos
     * @return list<array{id: int, label: string}>
     */
    private function filtrarCursosModalLista($cursos): array
    {
        $f = mb_strtolower(trim($this->modalAdeudadasCursoFiltro));
        $out = [];
        foreach ($cursos as $curso) {
            $label = $curso->nombreParaListado();
            if ($f !== '' && ! str_contains(mb_strtolower($label), $f)) {
                continue;
            }
            $out[] = [
                'id' => (int) $curso->Id,
                'label' => $label,
            ];
        }

        return $out;
    }
}
