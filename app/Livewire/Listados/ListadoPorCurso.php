<?php

namespace App\Livewire\Listados;

use App\Models\CampoLegajo;
use App\Models\ListadoPlantilla;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\Listados\ListadoCursoConsulta;
use App\Support\Listados\ListadoCursoExportParams;
use App\Support\Listados\ListadoCursoPdfFieldCatalog;
use App\Support\PortalDocente\ListadoPorCursoPortalDocente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class ListadoPorCurso extends Component
{
    /** @var list<string> */
    public array $cursosElegidos = [];

    public string $filtroCursos = '';

    /** @see ListadoCursoCondicionFiltro */
    public string $filtroCondicion = ListadoCursoCondicionFiltro::REGULARES;

    /** @var list<string> */
    public array $camposSeleccionados = ListadoCursoPdfFieldCatalog::DEFAULT_KEYS;

    /** Plantilla aplicada actualmente (null = ninguna). */
    public ?int $plantillaSeleccionada = null;

    /** Nombre para guardar una plantilla nueva. */
    public string $nombrePlantilla = '';

    /** Subtítulo opcional del PDF; solo en memoria Livewire (no se persiste en BD). */
    public string $subtituloListado = '';

    public function mount(): void
    {
        $this->camposSeleccionados = CampoLegajo::aplicarVisibilidadListadoPdf($this->camposSeleccionados);
    }

    public function updatedFiltroCondicion(mixed $value): void
    {
        $this->filtroCondicion = ListadoCursoCondicionFiltro::normalize(is_string($value) ? $value : null);
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

        $camposPorGrupo = ListadoCursoPdfFieldCatalog::groupedForUiPorSolapas();

        $plantillas = ListadoPlantilla::paraNivel($this->idNivelContexto())
            ->map(function (ListadoPlantilla $p) {
                $camposEtiquetas = $p->etiquetasCampos();

                return [
                    'id' => (int) $p->id,
                    'nombre' => (string) $p->nombre,
                    'condicionEtiqueta' => $p->etiquetaCondicionUi(),
                    'camposEtiquetas' => $camposEtiquetas,
                    'camposCantidad' => count($camposEtiquetas),
                ];
            })
            ->all();

        return view('listados::livewire.listados.por-curso', [
            'cursos' => $cursos,
            'cursosPorNivel' => array_values($cursosPorNivel),
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'cursosSeleccionadosResumen' => $cursosSeleccionadosResumen,
            'camposPorGrupo' => $camposPorGrupo,
            'plantillas' => $plantillas,
        ])->layout(ListadoPorCursoPortalDocente::layout(), ['pageTitle' => 'Listados de Estudiantes por Curso']);
    }

    /** Al elegir un radio de plantilla, aplica columnas y condición automáticamente. */
    public function updatedPlantillaSeleccionada(mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $this->aplicarPlantilla((int) $value);
    }

    /** Elimina la plantilla marcada en la grilla. */
    public function eliminarPlantillaSeleccionada(): void
    {
        if ($this->plantillaSeleccionada === null) {
            $this->dispatch('se-swal-error', mensaje: 'Seleccione una plantilla de la lista.');

            return;
        }

        $this->eliminarPlantilla($this->plantillaSeleccionada);
    }

    /** Carga columnas y condición de una plantilla del nivel actual. */
    public function aplicarPlantilla(int $id): void
    {
        $plantilla = $this->buscarPlantillaDelNivel($id);
        if ($plantilla === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró la plantilla seleccionada.');

            return;
        }

        $campos = ListadoCursoPdfFieldCatalog::normalizeSelection(
            is_array($plantilla->campos) ? $plantilla->campos : [],
        );
        $this->camposSeleccionados = CampoLegajo::aplicarVisibilidadListadoPdf($campos);
        $this->filtroCondicion = ListadoCursoCondicionFiltro::normalize($plantilla->condicion);
        $this->nombrePlantilla = (string) $plantilla->nombre;
    }

    /** Guarda la selección actual como una plantilla nueva del nivel. */
    public function guardarComoPlantilla(): void
    {
        if (! $this->rateLimitOk('listados-plantilla:guardar:', 30)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        $idNivel = $this->idNivelContexto();
        if ($idNivel < 1) {
            $this->dispatch('se-swal-error', mensaje: 'No hay un nivel activo en el contexto.');

            return;
        }

        $this->validate([
            'nombrePlantilla' => ['required', 'string', 'max:120'],
        ], [
            'nombrePlantilla.required' => 'Escriba un nombre para la plantilla.',
            'nombrePlantilla.max' => 'El nombre no puede superar los 120 caracteres.',
        ]);

        $nombre = trim($this->nombrePlantilla);

        $existe = ListadoPlantilla::query()
            ->where('idNivel', $idNivel)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->exists();
        if ($existe) {
            $this->addError('nombrePlantilla', 'Ya existe una plantilla con ese nombre en este nivel.');

            return;
        }

        $campos = ListadoCursoPdfFieldCatalog::normalizeSelection($this->camposSeleccionados);

        $proximoOrden = (int) ListadoPlantilla::query()->where('idNivel', $idNivel)->max('orden');

        $plantilla = ListadoPlantilla::create([
            'idNivel' => $idNivel,
            'nombre' => $nombre,
            'campos' => $campos,
            'condicion' => ListadoCursoCondicionFiltro::normalize($this->filtroCondicion),
            'orden' => $proximoOrden + 1,
        ]);

        $this->plantillaSeleccionada = (int) $plantilla->id;
        $this->nombrePlantilla = $nombre;

        $this->dispatch('se-swal-exito', mensaje: 'Plantilla «'.$nombre.'» guardada.');
    }

    /** Sobrescribe la plantilla aplicada con la selección actual. */
    public function actualizarPlantilla(): void
    {
        if ($this->plantillaSeleccionada === null) {
            return;
        }

        if (! $this->rateLimitOk('listados-plantilla:actualizar:', 30)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        $plantilla = $this->buscarPlantillaDelNivel($this->plantillaSeleccionada);
        if ($plantilla === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró la plantilla a actualizar.');

            return;
        }

        $plantilla->update([
            'campos' => ListadoCursoPdfFieldCatalog::normalizeSelection($this->camposSeleccionados),
            'condicion' => ListadoCursoCondicionFiltro::normalize($this->filtroCondicion),
        ]);

        $this->dispatch('se-swal-exito', mensaje: 'Plantilla «'.$plantilla->nombre.'» actualizada.');
    }

    public function eliminarPlantilla(int $id): void
    {
        if (! $this->rateLimitOk('listados-plantilla:eliminar:', 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        $plantilla = $this->buscarPlantillaDelNivel($id);
        if ($plantilla === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró la plantilla a eliminar.');

            return;
        }

        $nombre = (string) $plantilla->nombre;
        $plantilla->delete();

        if ($this->plantillaSeleccionada === $id) {
            $this->plantillaSeleccionada = null;
            $this->nombrePlantilla = '';
        }

        $this->dispatch('se-swal-exito', mensaje: 'Plantilla «'.$nombre.'» eliminada.');
    }

    private function idNivelContexto(): int
    {
        return (int) (schoolCtx()->idNivel ?? 0);
    }

    private function buscarPlantillaDelNivel(int $id): ?ListadoPlantilla
    {
        if ($id < 1) {
            return null;
        }

        return ListadoPlantilla::query()
            ->where('idNivel', $this->idNivelContexto())
            ->find($id);
    }

    private function rateLimitOk(string $prefijo, int $maxIntentos): bool
    {
        $key = $prefijo.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, $maxIntentos)) {
            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }

    public function getPdfUrlProperty(): string
    {
        return $this->armarUrlPdf(ListadoCursoExportParams::normalizarSubtitulo($this->subtituloListado));
    }

    /** URL del PDF sin subtítulo (el subtítulo se agrega en el navegador al abrir). */
    public function getPdfUrlBaseProperty(): string
    {
        return $this->armarUrlPdf('');
    }

    private function armarUrlPdf(string $subtitulo): string
    {
        if (! $this->puedeGenerarPdf()) {
            return '#';
        }

        $campos = ListadoCursoPdfFieldCatalog::normalizeSelection($this->camposSeleccionados);
        $filtro = ListadoCursoCondicionFiltro::normalize($this->filtroCondicion);

        $ids = collect($this->cursosElegidos)
            ->filter(fn ($v) => $v !== '' && $v !== null)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        $params = [
            'cursos' => $ids->implode(','),
            'campos' => implode(',', $campos),
            'condicion' => $filtro,
        ];

        if ($subtitulo !== '') {
            $params['subtitulo'] = $subtitulo;
        }

        return ListadoPorCursoPortalDocente::routePdf($params);
    }

    public function puedeGenerarPdf(): bool
    {
        return collect($this->cursosElegidos)
            ->filter(fn ($v) => $v !== '' && $v !== null)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->isNotEmpty();
    }

    /** Exportación completa: todos los cursos y columnas de solapas del legajo. */
    public function getExcelUrlCompletoProperty(): string
    {
        return ListadoPorCursoPortalDocente::routeExcel();
    }

    /** Misma selección que el PDF: cursos elegidos, columnas marcadas y condición. */
    public function getExcelUrlSeleccionProperty(): string
    {
        if (! $this->puedeGenerarPdf()) {
            return '#';
        }

        $campos = ListadoCursoPdfFieldCatalog::normalizeSelection($this->camposSeleccionados);
        $filtro = ListadoCursoCondicionFiltro::normalize($this->filtroCondicion);

        $ids = collect($this->cursosElegidos)
            ->filter(fn ($v) => $v !== '' && $v !== null)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        return ListadoPorCursoPortalDocente::routeExcel([
            'cursos' => $ids->implode(','),
            'campos' => implode(',', $campos),
            'condicion' => $filtro,
        ]);
    }

    public function puedeExportarExcelCompleto(): bool
    {
        return ListadoCursoConsulta::cursosPermitidosEnContexto()->isNotEmpty();
    }

    public function seleccionarSoloDefecto(): void
    {
        $this->camposSeleccionados = CampoLegajo::aplicarVisibilidadListadoPdf(ListadoCursoPdfFieldCatalog::DEFAULT_KEYS);
    }

    public function seleccionarTodos(): void
    {
        $soloLegajos = CampoLegajo::columnasLegajosVisiblesParaUi();
        $this->camposSeleccionados = collect(ListadoCursoPdfFieldCatalog::allowedKeys())
            ->filter(function (string $k) use ($soloLegajos) {
                if (! str_starts_with($k, 'legajos.')) {
                    return false;
                }
                if ($soloLegajos === null) {
                    return true;
                }

                return in_array(substr($k, strlen('legajos.')), $soloLegajos, true);
            })
            ->values()
            ->all();
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
