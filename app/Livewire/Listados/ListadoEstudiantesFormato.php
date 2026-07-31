<?php

namespace App\Livewire\Listados;

use App\Support\Listados\ListadoCursoConsulta;
use App\Support\Listados\ListadoEstudiantesFormatoCatalog;
use App\Support\Listados\ListadoEstudiantesFormatoMes;
use App\Support\PortalDocente\ListadoEstudiantesFormatoPortalDocente;
use Illuminate\Support\Collection;
use Livewire\Component;

class ListadoEstudiantesFormato extends Component
{
    /** @var list<string> */
    public array $cursosElegidos = [];

    public string $filtroCursos = '';

    public string $modelo = ListadoEstudiantesFormatoCatalog::MODELO_CUADRICULADO;

    public int $mes = 0;

    public function mount(): void
    {
        $this->mes = (int) now()->month;
    }

    public function updatedModelo(mixed $value): void
    {
        $this->modelo = ListadoEstudiantesFormatoCatalog::normalize(is_string($value) ? $value : null);
    }

    public function quitarCurso(int $idCurso): void
    {
        $key = (string) $idCurso;
        $this->cursosElegidos = array_values(array_filter(
            $this->cursosElegidos,
            fn (string $id) => $id !== $key,
        ));
    }

    public function seleccionarTodosCursos(): void
    {
        $this->cursosElegidos = $this->idsCursosPermitidosComoString()->keys()->all();
    }

    public function quitarTodosCursos(): void
    {
        $this->cursosElegidos = [];
    }

    public function marcarNivel(int $idNivel): void
    {
        $ids = $this->idsCursosDelNivel($idNivel);
        $this->cursosElegidos = array_values(array_unique(array_merge(
            $this->cursosElegidos,
            $ids,
        )));
    }

    public function quitarNivel(int $idNivel): void
    {
        $quitar = array_flip($this->idsCursosDelNivel($idNivel));
        $this->cursosElegidos = array_values(array_filter(
            $this->cursosElegidos,
            fn (string $id) => ! isset($quitar[$id]),
        ));
    }

    public function puedeGenerarPdf(): bool
    {
        $tieneCursos = collect($this->cursosElegidos)
            ->filter(fn ($v) => $v !== '' && $v !== null)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->isNotEmpty();

        if (! $tieneCursos) {
            return false;
        }

        if (ListadoEstudiantesFormatoCatalog::requiereMes($this->modelo)) {
            return ListadoEstudiantesFormatoMes::normalizarMes($this->mes) > 0;
        }

        return true;
    }

    public function getPdfUrlProperty(): string
    {
        if (! $this->puedeGenerarPdf()) {
            return '#';
        }

        $ids = collect($this->cursosElegidos)
            ->filter(fn ($v) => $v !== '' && $v !== null)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        $params = [
            'cursos' => $ids->implode(','),
            'modelo' => ListadoEstudiantesFormatoCatalog::normalize($this->modelo),
        ];

        if (ListadoEstudiantesFormatoCatalog::requiereMes($this->modelo)) {
            $params['mes'] = ListadoEstudiantesFormatoMes::normalizarMes($this->mes);
            $ano = (int) (schoolCtx()->terlecAno() ?? 0);
            if ($ano > 0) {
                $params['ano'] = $ano;
            }
        }

        return ListadoEstudiantesFormatoPortalDocente::routePdf($params);
    }

    public function render()
    {
        $cursos = ListadoCursoConsulta::cursosPermitidosEnContexto();

        $filtro = mb_strtolower(trim($this->filtroCursos));
        $seleccionadosFlip = array_flip($this->cursosElegidos);
        $cantidadSeleccionados = count($this->cursosElegidos);

        $cursosPorNivel = [];
        foreach ($cursos as $curso) {
            $etiqueta = ListadoCursoConsulta::etiquetaCursoConNivel($curso);
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
                'etiquetaCorta' => $curso->nombreParaListado(),
            ];
            $cursosPorNivel[$key]['total']++;
            if ($marcado) {
                $cursosPorNivel[$key]['seleccionados']++;
            }
        }

        $etiquetasPorId = $cursos->mapWithKeys(fn ($c) => [
            (string) (int) $c->Id => ListadoCursoConsulta::etiquetaCursoConNivel($c),
        ]);

        $cursosSeleccionadosResumen = collect($this->cursosElegidos)
            ->map(fn (string $id) => [
                'id' => (int) $id,
                'label' => (string) ($etiquetasPorId[$id] ?? ''),
            ])
            ->filter(fn (array $r) => $r['label'] !== '')
            ->values()
            ->all();

        return view('listados::livewire.listados.estudiantes-formato', [
            'cursos' => $cursos,
            'cursosPorNivel' => array_values($cursosPorNivel),
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'cursosSeleccionadosResumen' => $cursosSeleccionadosResumen,
            'modelos' => ListadoEstudiantesFormatoCatalog::paraUi(),
            'meses' => ListadoEstudiantesFormatoMes::opcionesSelector(),
        ])->layout(ListadoEstudiantesFormatoPortalDocente::layout(), ['pageTitle' => 'Listados de Estudiantes con Formato']);
    }

    /** @return Collection<string, int> */
    private function idsCursosPermitidosComoString(): Collection
    {
        return ListadoCursoConsulta::cursosPermitidosEnContexto()
            ->pluck('Id')
            ->mapWithKeys(fn ($id) => [(string) (int) $id => (int) $id]);
    }

    /** @return list<string> */
    private function idsCursosDelNivel(int $idNivel): array
    {
        if ($idNivel < 1) {
            return [];
        }

        return ListadoCursoConsulta::cursosPermitidosEnContexto()
            ->filter(fn ($c) => (int) ($c->idNivel ?? 0) === $idNivel)
            ->map(fn ($c) => (string) (int) $c->Id)
            ->values()
            ->all();
    }
}
