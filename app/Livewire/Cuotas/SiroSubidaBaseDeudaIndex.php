<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\Siro\SiroSubidaBaseDeudaConsulta;
use App\Support\Cuotas\Siro\SiroSubidaBaseDeudaFiltros;
use App\Support\Cuotas\Siro\SiroSubidaBaseDeudaRegistro;
use App\Support\PermisosCuotas;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Subida de base de deuda a SIRO — filtros, grilla previa y generación de archivo.
 */
class SiroSubidaBaseDeudaIndex extends Component
{
    /** 1 = filtros, 2 = grilla */
    public int $paso = 1;

    public bool $chkCuotas = false;

    /** @var list<int> */
    public array $idsCuotas = [];

    /** @var list<int> */
    public array $marcadasCuotasIzq = [];

    /** @var list<int> */
    public array $marcadasCuotasDer = [];

    public bool $chkCursos = false;

    /** @var list<int> */
    public array $idsCursos = [];

    /** @var list<int> */
    public array $marcadasCursosIzq = [];

    /** @var list<int> */
    public array $marcadasCursosDer = [];

    public bool $chkExcluirAlumnos = false;

    /** @var list<int> */
    public array $idsExcluirAlumnos = [];

    /** @var list<int> */
    public array $marcadasExcluirIzq = [];

    /** @var list<int> */
    public array $marcadasExcluirDer = [];

    public bool $chkIncluirAlumnos = false;

    /** @var list<int> */
    public array $idsIncluirAlumnos = [];

    /** @var list<int> */
    public array $marcadasIncluirIzq = [];

    /** @var list<int> */
    public array $marcadasIncluirDer = [];

    /** @var list<array<string, mixed>> */
    public array $filasGrilla = [];

    public int $cantidadSubeSiro = 0;

    public int $cantidadNoSubeSiro = 0;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeSiroSubidaBaseDeuda(), 403);
    }

    public function updatedChkIncluirAlumnos(bool $valor): void
    {
        if ($valor) {
            $this->chkExcluirAlumnos = false;
            $this->idsExcluirAlumnos = [];
            $this->marcadasExcluirIzq = [];
        }
    }

    public function updatedChkExcluirAlumnos(bool $valor): void
    {
        if ($valor) {
            $this->chkIncluirAlumnos = false;
            $this->idsIncluirAlumnos = [];
            $this->marcadasIncluirIzq = [];
        }
    }

    public function aceptarFiltros(): void
    {
        abort_unless(PermisosCuotas::puedeSiroSubidaBaseDeuda(), 403);

        try {
            $filtros = SiroSubidaBaseDeudaFiltros::normalizarDesdeLivewire($this->filtrosCrudos());
        } catch (ValidationException $e) {
            $this->addErrorBag($e->validator->getMessageBag());
            $this->dispatch('se-swal-error', mensaje: collect($e->errors())->flatten()->first() ?? 'Revise los filtros.');

            return;
        }

        $registros = SiroSubidaBaseDeudaConsulta::cuotasAdeudadas($filtros);
        $filas = [];
        $sube = 0;
        $noSube = 0;

        foreach ($registros as $registro) {
            $fila = SiroSubidaBaseDeudaRegistro::filaGrilla($registro);
            $filas[] = $fila;
            if ($fila['subeSiro']) {
                $sube++;
            } else {
                $noSube++;
            }
        }

        $this->filasGrilla = $filas;
        $this->cantidadSubeSiro = $sube;
        $this->cantidadNoSubeSiro = $noSube;
        $this->paso = 2;
        $this->resetErrorBag();

        session([
            'siro_subida_filtros' => $filtros,
            'siro_subida_ids' => collect($filas)->where('subeSiro', true)->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ]);
    }

    public function volverAFiltros(): void
    {
        $this->paso = 1;
        $this->filasGrilla = [];
        $this->cantidadSubeSiro = 0;
        $this->cantidadNoSubeSiro = 0;
        session()->forget(['siro_subida_filtros', 'siro_subida_ids']);
    }

    public function prepararDescarga(): void
    {
        abort_unless(PermisosCuotas::puedeSiroSubidaBaseDeuda(), 403);

        if ($this->cantidadSubeSiro < 1) {
            $this->dispatch('se-swal-error', mensaje: 'No hay registros elegibles para subir a SIRO.');

            return;
        }

        $ids = session('siro_subida_ids', []);
        if ($ids === []) {
            $this->dispatch('se-swal-error', mensaje: 'La selección expiró. Vuelva a aplicar los filtros.');

            return;
        }

        $key = 'siro-subida-archivo:'.(auth()->id() ?? request()->ip());
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiadas solicitudes. Intente nuevamente en breve.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->redirect(route('cuotas.siro-subida.archivo'));
    }

    public function moverSeleccion(string $tipo, string $direccion): void
    {
        [$marcadasIzqProp, $marcadasDerProp, $idsProp] = match ($tipo) {
            'cuotas' => ['marcadasCuotasIzq', 'marcadasCuotasDer', 'idsCuotas'],
            'cursos' => ['marcadasCursosIzq', 'marcadasCursosDer', 'idsCursos'],
            'excluir' => ['marcadasExcluirIzq', 'marcadasExcluirDer', 'idsExcluirAlumnos'],
            'incluir' => ['marcadasIncluirIzq', 'marcadasIncluirDer', 'idsIncluirAlumnos'],
            default => [null, null, null],
        };

        if ($marcadasIzqProp === null) {
            return;
        }

        $seleccionados = array_map('intval', $this->{$idsProp});

        if ($direccion === 'agregar-todos') {
            $disponibles = $this->idsDisponiblesShuttle($tipo, $seleccionados);
            $this->{$idsProp} = array_values(array_unique(array_merge($seleccionados, $disponibles)));
            $this->{$marcadasIzqProp} = [];
            $this->{$marcadasDerProp} = [];

            return;
        }

        if ($direccion === 'quitar-todos') {
            $this->{$idsProp} = [];
            $this->{$marcadasIzqProp} = [];
            $this->{$marcadasDerProp} = [];

            return;
        }

        if ($direccion === 'agregar') {
            $marcadas = array_map('intval', $this->{$marcadasIzqProp});
            if ($marcadas === []) {
                return;
            }
            $this->{$idsProp} = array_values(array_unique(array_merge($seleccionados, $marcadas)));
            $this->{$marcadasIzqProp} = [];

            return;
        }

        if ($direccion === 'quitar') {
            $marcadas = array_map('intval', $this->{$marcadasDerProp});
            if ($marcadas === []) {
                return;
            }
            $marcadasFlip = array_flip($marcadas);
            $this->{$idsProp} = array_values(array_filter(
                $seleccionados,
                fn (int $id) => ! isset($marcadasFlip[$id]),
            ));
            $this->{$marcadasDerProp} = [];
        }
    }

    /**
     * @param  list<int>  $seleccionados
     * @return list<int>
     */
    private function idsDisponiblesShuttle(string $tipo, array $seleccionados): array
    {
        $todos = match ($tipo) {
            'cuotas' => SiroSubidaBaseDeudaFiltros::cuotasParaSelector()->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'cursos' => SiroSubidaBaseDeudaFiltros::cursosParaSelector()->pluck('Id')->map(fn ($id) => (int) $id)->all(),
            'excluir', 'incluir' => SiroSubidaBaseDeudaFiltros::alumnosParaSelector()->pluck('id')->map(fn ($id) => (int) $id)->all(),
            default => [],
        };

        $sel = array_flip($seleccionados);

        return array_values(array_filter($todos, fn (int $id) => ! isset($sel[$id])));
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosCrudos(): array
    {
        return [
            'chkCuotas' => $this->chkCuotas,
            'idsCuotas' => $this->idsCuotas,
            'chkCursos' => $this->chkCursos,
            'idsCursos' => $this->idsCursos,
            'chkExcluirAlumnos' => $this->chkExcluirAlumnos,
            'idsExcluirAlumnos' => $this->idsExcluirAlumnos,
            'chkIncluirAlumnos' => $this->chkIncluirAlumnos,
            'idsIncluirAlumnos' => $this->idsIncluirAlumnos,
        ];
    }

    public function render()
    {
        $ano = (int) schoolCtx()->terlecAno();

        $cuotasCatalogo = SiroSubidaBaseDeudaFiltros::cuotasParaSelector();
        $cursosCatalogo = SiroSubidaBaseDeudaFiltros::cursosParaSelector();
        $alumnosCatalogo = SiroSubidaBaseDeudaFiltros::alumnosParaSelector();

        return view('livewire.cuotas.siro-subida-base-deuda', [
            'ano' => $ano,
            'cuotasCatalogo' => $cuotasCatalogo,
            'cursosCatalogo' => $cursosCatalogo,
            'alumnosCatalogo' => $alumnosCatalogo,
            'cuotasDisponibles' => $this->itemsShuttleDisponibles($cuotasCatalogo, $this->idsCuotas, 'cuota'),
            'cursosDisponibles' => $this->itemsShuttleDisponibles($cursosCatalogo, $this->idsCursos, 'curso'),
            'alumnosExcluirDisponibles' => $this->itemsShuttleDisponibles($alumnosCatalogo, $this->idsExcluirAlumnos, 'alumno'),
            'alumnosIncluirDisponibles' => $this->itemsShuttleDisponibles($alumnosCatalogo, $this->idsIncluirAlumnos, 'alumno'),
            'cuotasSeleccionados' => $this->itemsShuttleSeleccionados($cuotasCatalogo, $this->idsCuotas, 'cuota'),
            'cursosSeleccionados' => $this->itemsShuttleSeleccionados($cursosCatalogo, $this->idsCursos, 'curso'),
            'alumnosExcluirSeleccionados' => $this->itemsShuttleSeleccionados($alumnosCatalogo, $this->idsExcluirAlumnos, 'alumno'),
            'alumnosIncluirSeleccionados' => $this->itemsShuttleSeleccionados($alumnosCatalogo, $this->idsIncluirAlumnos, 'alumno'),
            'etiquetaCurso' => fn ($curso) => \App\Support\Cuotas\GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($curso),
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Subida base de deuda SIRO — {$ano}"]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $catalogo
     * @param  list<int>  $seleccionados
     * @return list<array{id: int, label: string}>
     */
    private function itemsShuttleDisponibles($catalogo, array $seleccionados, string $tipo): array
    {
        $sel = array_flip(array_map('intval', $seleccionados));
        $items = [];

        foreach ($catalogo as $item) {
            $id = (int) ($tipo === 'curso' ? $item->Id : $item->id);
            if (isset($sel[$id])) {
                continue;
            }
            $items[] = ['id' => $id, 'label' => $this->etiquetaShuttle($item, $tipo)];
        }

        return $items;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $catalogo
     * @param  list<int>  $seleccionados
     * @return list<array{id: int, label: string}>
     */
    private function itemsShuttleSeleccionados($catalogo, array $seleccionados, string $tipo): array
    {
        $sel = array_flip(array_map('intval', $seleccionados));

        if ($tipo === 'cuota') {
            $items = [];
            foreach ($catalogo as $item) {
                $id = (int) $item->id;
                if (! isset($sel[$id])) {
                    continue;
                }
                $items[] = ['id' => $id, 'label' => $this->etiquetaShuttle($item, $tipo)];
            }

            return $items;
        }

        $porId = [];
        foreach ($catalogo as $item) {
            $id = (int) ($tipo === 'curso' ? $item->Id : $item->id);
            $porId[$id] = $this->etiquetaShuttle($item, $tipo);
        }

        $items = [];
        foreach (array_map('intval', $seleccionados) as $id) {
            if (! isset($porId[$id])) {
                continue;
            }
            $items[] = ['id' => $id, 'label' => $porId[$id]];
        }

        return $items;
    }

    private function etiquetaShuttle(mixed $item, string $tipo): string
    {
        return match ($tipo) {
            'cuota' => (function () use ($item) {
                $ano = (int) ($item->terlec_ano ?? 0);

                return ($ano > 0 ? $ano.' — ' : '').trim((string) ($item->nombre ?? ''));
            })(),
            'curso' => \App\Support\Cuotas\GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($item),
            'alumno' => mb_strtoupper(trim((string) ($item->apellido ?? '').' '.(string) ($item->nombre ?? ''))),
            default => '',
        };
    }
}
