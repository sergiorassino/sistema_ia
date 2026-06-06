<?php

namespace App\Livewire\Abm\Legajos\Concerns;

use App\Models\Curso;
use App\Models\Familia;
use App\Models\Legajo;
use App\Models\Matricula;
use App\Models\Sexo;
use App\Support\Legajos\LegajoCargaPorCursoCatalog;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\PermisosIaCatalog;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

trait LegajoCargaPorCursoPanel
{
    public bool $cargaPorCursoGrillaVisible = false;

    public ?int $cargaPorCursoId = null;

    /** @see ListadoCursoCondicionFiltro */
    public string $cargaPorCursoFiltroCondicion = ListadoCursoCondicionFiltro::REGULARES;

    /** @var list<string> */
    public array $cargaPorCursoCampos = [];

    /** @var array<int, array<string, mixed>> */
    public array $cargaPorCursoRows = [];

    /** @var list<array{column: string, label: string, type: string}> */
    public array $cargaPorCursoColumnasMeta = [];

    /** @var list<array{id: int, label: string}> opciones del select (estado Livewire). */
    public array $cargaPorCursoCursosOpciones = [];

    public function updatedCargaPorCursoId(mixed $value): void
    {
        $this->cargaPorCursoId = ((int) $value) > 0 ? (int) $value : null;
        $this->resetCargaPorCursoGrilla();
    }

    public function updatedCargaPorCursoFiltroCondicion(mixed $value): void
    {
        $this->cargaPorCursoFiltroCondicion = ListadoCursoCondicionFiltro::normalize(is_string($value) ? $value : null);
        $this->resetCargaPorCursoGrilla();
    }

    public function updatedCargaPorCursoCampos(): void
    {
        $this->resetCargaPorCursoGrilla();
    }

    public function cargarGrillaCargaPorCurso(): void
    {
        abort_unless(puedeModificarLegajosEstudiantes(), 403);

        $this->validate([
            'cargaPorCursoId' => ['required', 'integer', 'min:1'],
            'cargaPorCursoCampos' => ['required', 'array', 'min:1'],
        ], [
            'cargaPorCursoId.required' => 'Seleccione un curso.',
            'cargaPorCursoCampos.min' => 'Seleccione al menos un campo a cargar.',
        ]);

        $this->ensureCursoCargaPorCursoOr404((int) $this->cargaPorCursoId);

        $columnas = LegajoCargaPorCursoCatalog::columnasDesdeKeys($this->cargaPorCursoCampos);
        if ($columnas === []) {
            $this->addError('cargaPorCursoCampos', 'Ninguno de los campos seleccionados es editable.');

            return;
        }

        $this->cargaPorCursoColumnasMeta = collect($columnas)->map(fn (string $col) => [
            'column' => $col,
            'label' => LegajoCargaPorCursoCatalog::etiquetaColumna($col),
            'type' => LegajoCargaPorCursoCatalog::tipoInput($col),
        ])->values()->all();

        $ctx = schoolCtx();
        $idCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery($this->cargaPorCursoFiltroCondicion);

        $select = array_merge(
            ['legajos.id', 'legajos.apellido', 'legajos.nombre', 'legajos.dni'],
            collect($columnas)->map(fn (string $c) => 'legajos.'.$c)->all(),
        );

        $alumnos = Legajo::query()
            ->join('matricula', 'matricula.idLegajos', '=', 'legajos.id')
            ->where('matricula.idCursos', (int) $this->cargaPorCursoId)
            ->where('matricula.idTerlec', (int) $ctx->idTerlec)
            ->where(function ($q) {
                SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($q, 'matricula.idNivel');
            })
            ->whereIn('matricula.idCondiciones', $idCondiciones)
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre')
            ->get($select);

        $this->cargaPorCursoRows = [];
        foreach ($alumnos as $legajo) {
            $id = (int) $legajo->id;
            $row = [
                'id' => $id,
                'apellido' => (string) ($legajo->apellido ?? ''),
                'nombre' => (string) ($legajo->nombre ?? ''),
                'dni' => (string) ($legajo->dni ?? ''),
            ];
            foreach ($columnas as $col) {
                $row[$col] = LegajoCargaPorCursoCatalog::valorParaInput($col, $legajo->{$col} ?? null);
            }
            $this->cargaPorCursoRows[$id] = $row;
        }

        $this->cargaPorCursoGrillaVisible = true;
        $this->resetValidation();
    }

    public function saveCargaPorCursoCell(int $idLegajo, string $columna, mixed $value): void
    {
        abort_unless(puedeModificarLegajosEstudiantes(), 403);

        $rateKey = 'legajos:carga-curso:cell:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 180)) {
            return;
        }
        RateLimiter::hit($rateKey, 60);

        if (! $this->cargaPorCursoGrillaVisible || ! $this->cargaPorCursoId) {
            return;
        }

        $columna = trim($columna);
        if (! LegajoCargaPorCursoCatalog::esColumnaPermitida($columna)) {
            abort(400);
        }

        if ($columna === 'idFamilias') {
            abort_unless(tienePermiso(PermisosIaCatalog::LEGAJOS_FAMILIAS_GESTION), 403, 'Sin permiso para modificar familias.');
        }

        $columnasActivas = collect($this->cargaPorCursoColumnasMeta)->pluck('column')->all();
        if (! in_array($columna, $columnasActivas, true)) {
            abort(400);
        }

        $this->ensureCursoCargaPorCursoOr404((int) $this->cargaPorCursoId);
        $this->ensureLegajoEnCursoCargaOr404($idLegajo, (int) $this->cargaPorCursoId);

        Validator::make(
            ['value' => $value],
            ['value' => LegajoCargaPorCursoCatalog::reglasValidacion($columna)],
            [],
            ['value' => LegajoCargaPorCursoCatalog::etiquetaColumna($columna)],
        )->validate();

        $normalizado = LegajoCargaPorCursoCatalog::normalizarValor($columna, $value);

        Legajo::query()->whereKey($idLegajo)->update([$columna => $normalizado]);

        if (isset($this->cargaPorCursoRows[$idLegajo])) {
            $this->cargaPorCursoRows[$idLegajo][$columna] = LegajoCargaPorCursoCatalog::valorParaInput(
                $columna,
                Legajo::query()->whereKey($idLegajo)->value($columna),
            );
        }

        $this->resetErrorBag('cargaCell.'.$idLegajo.'.'.$columna);
    }

    protected function refrescarCargaPorCursoCursosOpciones(): void
    {
        $this->cargaPorCursoCursosOpciones = $this->cursosParaCargaPorCurso()
            ->map(fn (Curso $c) => [
                'id' => (int) $c->Id,
                'label' => $c->nombreParaListado(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Curso>
     */
    protected function cursosParaCargaPorCurso(): Collection
    {
        $ctx = schoolCtx();
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        $columnas = ['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's', 'idNivel', 'idTerlec'];

        $query = Curso::query()
            ->with(['curplan', 'turnoClase'])
            ->when($idTerlec > 0, fn ($q) => $q->where('idTerlec', $idTerlec));

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNivel');

        $query->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id');

        $cursos = $query->get($columnas);
        if ($cursos->isNotEmpty()) {
            return $cursos;
        }

        if ($idTerlec < 1) {
            return $cursos;
        }

        $matriculaQuery = Matricula::query()->where('idTerlec', $idTerlec);
        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($matriculaQuery, 'idNivel');

        $idsConMatricula = $matriculaQuery
            ->distinct()
            ->pluck('idCursos')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($idsConMatricula === []) {
            return $cursos;
        }

        return Curso::query()
            ->with(['curplan', 'turnoClase'])
            ->whereIn('Id', $idsConMatricula)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get($columnas);
    }

    /**
     * @return array{
     *     bloquesCamposCargaPorCurso: list<array{titulo: string, items: list<array{key: string, label: string, column: string}>}>,
     *     sexosCargaPorCurso: mixed,
     *     familiasCargaPorCurso: mixed,
     *     cursoCargaPorCursoLabel: ?string
     * }
     */
    protected function datosPanelCargaPorCurso(): array
    {
        $this->refrescarCargaPorCursoCursosOpciones();
        $cursoLabel = null;
        if ($this->cargaPorCursoId) {
            foreach ($this->cargaPorCursoCursosOpciones as $opt) {
                if ((int) $opt['id'] === (int) $this->cargaPorCursoId) {
                    $cursoLabel = (string) $opt['label'];
                    break;
                }
            }
        }

        return [
            'bloquesCamposCargaPorCurso' => LegajoCargaPorCursoCatalog::bloquesParaSelector(),
            'sexosCargaPorCurso' => Sexo::opcionesParaSelect(),
            'familiasCargaPorCurso' => Familia::orderBy('id')->orderBy('apellido')->get(['id', 'apellido', 'responsable']),
            'cursoCargaPorCursoLabel' => $cursoLabel,
            'puedeGestionarFamilias' => tienePermiso(PermisosIaCatalog::LEGAJOS_FAMILIAS_GESTION),
        ];
    }

    private function resetCargaPorCursoGrilla(): void
    {
        $this->cargaPorCursoGrillaVisible = false;
        $this->cargaPorCursoRows = [];
        $this->cargaPorCursoColumnasMeta = [];
    }

    private function ensureCursoCargaPorCursoOr404(int $idCurso): Curso
    {
        $permitidos = collect($this->cargaPorCursoCursosOpciones)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        if ($permitidos->has($idCurso)) {
            return Curso::query()->whereKey($idCurso)->firstOrFail();
        }

        abort(404);
    }

    private function ensureLegajoEnCursoCargaOr404(int $idLegajo, int $idCurso): void
    {
        $ctx = schoolCtx();
        $idCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery($this->cargaPorCursoFiltroCondicion);

        $existsQuery = Matricula::query()
            ->where('idLegajos', $idLegajo)
            ->where('idCursos', $idCurso)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->whereIn('idCondiciones', $idCondiciones);

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($existsQuery, 'idNivel');

        $exists = $existsQuery->exists();

        if (! $exists) {
            abort(404);
        }
    }
}
