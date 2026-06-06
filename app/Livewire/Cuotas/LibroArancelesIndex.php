<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\GeneracionMasivaCuotasConsulta;
use App\Support\PermisosCuotas;
use Livewire\Component;

/**
 * Selección de cursos y página inicial para imprimir el Libro de aranceles (PDF).
 */
class LibroArancelesIndex extends Component
{
    /** @var list<string> */
    public array $cursosSeleccionados = [];

    public string $filtroCursos = '';

    public int $paginaInicial = 1;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeLibroAranceles(), 403);
    }

    public function quitarCurso(int $idCurso): void
    {
        $key = (string) $idCurso;
        $this->cursosSeleccionados = array_values(array_filter(
            $this->cursosSeleccionados,
            fn (string $id) => $id !== $key,
        ));
    }

    public function seleccionarTodosCursos(): void
    {
        $this->cursosSeleccionados = $this->idsCursosPermitidosComoString()->keys()->all();
        $this->resetErrorBag('cursosSeleccionados');
    }

    public function quitarTodosCursos(): void
    {
        $this->cursosSeleccionados = [];
    }

    public function marcarNivel(int $idNivel): void
    {
        $ids = $this->idsCursosDelNivel($idNivel);
        $this->cursosSeleccionados = array_values(array_unique(array_merge(
            $this->cursosSeleccionados,
            $ids,
        )));
        $this->resetErrorBag('cursosSeleccionados');
    }

    public function quitarNivel(int $idNivel): void
    {
        $quitar = array_flip($this->idsCursosDelNivel($idNivel));
        $this->cursosSeleccionados = array_values(array_filter(
            $this->cursosSeleccionados,
            fn (string $id) => ! isset($quitar[$id]),
        ));
    }

    public function puedeGenerarPdf(): bool
    {
        return $this->idsCursosValidados() !== [];
    }

    public function getPdfUrlProperty(): string
    {
        if (! $this->puedeGenerarPdf()) {
            return '#';
        }

        $ids = collect($this->idsCursosValidados());

        return route('cuotas.libro-aranceles.pdf', [
            'cursos' => $ids->implode(','),
            'pagina' => max(1, $this->paginaInicial),
        ]);
    }

    /** @return \Illuminate\Support\Collection<string, int> */
    private function idsCursosPermitidosComoString(): \Illuminate\Support\Collection
    {
        return GeneracionMasivaCuotasConsulta::cursosEnContexto()
            ->pluck('Id')
            ->mapWithKeys(fn ($id) => [(string) (int) $id => (int) $id]);
    }

    /** @return list<string> */
    private function idsCursosDelNivel(int $idNivel): array
    {
        if ($idNivel < 1) {
            return [];
        }

        return GeneracionMasivaCuotasConsulta::cursosEnContexto()
            ->filter(fn ($c) => (int) ($c->idNivel ?? 0) === $idNivel)
            ->map(fn ($c) => (string) (int) $c->Id)
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function idsCursosValidados(): array
    {
        $permitidos = $this->idsCursosPermitidosComoString();

        return collect($this->cursosSeleccionados)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $permitidos->has((string) $id))
            ->unique()
            ->values()
            ->all();
    }

    public function render()
    {
        $ano = (int) schoolCtx()->terlecAno();
        $cursos = GeneracionMasivaCuotasConsulta::cursosEnContexto();

        $filtro = mb_strtolower(trim($this->filtroCursos));
        $seleccionadosFlip = array_flip($this->cursosSeleccionados);
        $cantidadSeleccionados = count($this->cursosSeleccionados);

        $cursosPorNivel = [];
        foreach ($cursos as $curso) {
            $etiqueta = GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($curso);
            if ($filtro !== '' && ! str_contains(mb_strtolower($etiqueta), $filtro)) {
                continue;
            }

            $idNivel = (int) ($curso->idNivel ?? 0);
            $key = (string) $idNivel;
            if (! isset($cursosPorNivel[$key])) {
                $cursosPorNivel[$key] = [
                    'idNivel' => $idNivel,
                    'nivelNombre' => trim((string) ($curso->nivel?->nivel ?? 'Sin nivel')),
                    'cursos' => [],
                    'total' => 0,
                    'seleccionados' => 0,
                ];
            }

            $idCursoStr = (string) (int) $curso->Id;
            $marcado = isset($seleccionadosFlip[$idCursoStr]);
            $cursosPorNivel[$key]['cursos'][] = [
                'id' => (int) $curso->Id,
                'etiqueta' => $etiqueta,
                'seleccionado' => $marcado,
            ];
            $cursosPorNivel[$key]['total']++;
            if ($marcado) {
                $cursosPorNivel[$key]['seleccionados']++;
            }
        }

        $etiquetasPorId = $cursos->mapWithKeys(fn ($c) => [
            (string) (int) $c->Id => GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($c),
        ]);

        $cursosSeleccionadosResumen = collect($this->cursosSeleccionados)
            ->map(fn (string $id) => [
                'id' => (int) $id,
                'label' => (string) ($etiquetasPorId[$id] ?? ''),
            ])
            ->filter(fn (array $r) => $r['label'] !== '')
            ->values()
            ->all();

        return view('livewire.cuotas.libro-aranceles', [
            'cursos' => $cursos,
            'cursosPorNivel' => array_values($cursosPorNivel),
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'cursosSeleccionadosResumen' => $cursosSeleccionadosResumen,
            'pdfUrl' => $this->pdfUrl,
            'ano' => $ano,
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Libro de aranceles — {$ano}"]);
    }
}
