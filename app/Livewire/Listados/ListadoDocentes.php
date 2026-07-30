<?php

namespace App\Livewire\Listados;

use App\Models\CampoProfesor;
use App\Support\Listados\ListadoDocentesConsulta;
use App\Support\Listados\ListadoDocentesExportParams;
use App\Support\Listados\ListadoDocentesPdfFieldCatalog;
use Illuminate\Support\Collection;
use Livewire\Component;

class ListadoDocentes extends Component
{
    /** @var list<string> */
    public array $rolesElegidos = [];

    public string $filtroRoles = '';

    /** @var list<string> */
    public array $camposSeleccionados = ListadoDocentesPdfFieldCatalog::DEFAULT_KEYS;

    public string $subtituloListado = '';

    public function mount(): void
    {
        abort_unless(puedeConsultarLegajosDocentes(), 403);
        $this->camposSeleccionados = CampoProfesor::aplicarVisibilidadListadoPdf($this->camposSeleccionados);
        $this->camposSeleccionados = ListadoDocentesPdfFieldCatalog::restringirPorPermisoDatosPersonales(
            $this->camposSeleccionados
        );
    }

    public function updatedCamposSeleccionados(): void
    {
        $this->camposSeleccionados = ListadoDocentesPdfFieldCatalog::restringirPorPermisoDatosPersonales(
            ListadoDocentesPdfFieldCatalog::normalizeSelection($this->camposSeleccionados)
        );
    }

    public function quitarRol(int $idRol): void
    {
        $key = (string) $idRol;
        $this->rolesElegidos = array_values(array_filter(
            $this->rolesElegidos,
            fn (string $id) => $id !== $key,
        ));
    }

    public function seleccionarTodosRoles(): void
    {
        $this->rolesElegidos = $this->idsRolesPermitidosComoString()->keys()->all();
    }

    public function quitarTodosRoles(): void
    {
        $this->rolesElegidos = [];
    }

    public function render()
    {
        $roles = ListadoDocentesConsulta::rolesDisponibles();

        $filtro = mb_strtolower(trim($this->filtroRoles));
        $seleccionadosFlip = array_flip($this->rolesElegidos);
        $cantidadSeleccionados = count($this->rolesElegidos);

        $rolesFiltrados = [];
        foreach ($roles as $rol) {
            $etiqueta = trim((string) $rol->tipo);
            if ($filtro !== '' && ! str_contains(mb_strtolower($etiqueta), $filtro)) {
                continue;
            }

            $idRolStr = (string) (int) $rol->id;
            $rolesFiltrados[] = [
                'id' => (int) $rol->id,
                'etiqueta' => $etiqueta,
                'marcado' => isset($seleccionadosFlip[$idRolStr]),
            ];
        }

        $etiquetasPorId = $roles->mapWithKeys(fn ($r) => [
            (string) (int) $r->id => trim((string) $r->tipo),
        ]);

        $rolesSeleccionadosResumen = collect($this->rolesElegidos)
            ->map(fn (string $id) => [
                'id' => (int) $id,
                'label' => (string) ($etiquetasPorId[$id] ?? ''),
            ])
            ->filter(fn (array $r) => $r['label'] !== '')
            ->values()
            ->all();

        $camposPorGrupo = ListadoDocentesPdfFieldCatalog::groupedForUiPorSolapasSegunPermiso();
        $puedeVerDatosPersonales = puedeModificarLegajosDocentes();

        return view('listados::livewire.listados.docentes', [
            'roles' => $roles,
            'rolesFiltrados' => $rolesFiltrados,
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'rolesSeleccionadosResumen' => $rolesSeleccionadosResumen,
            'camposPorGrupo' => $camposPorGrupo,
            'puedeVerDatosPersonales' => $puedeVerDatosPersonales,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Listado de docentes']);
    }

    public function getPdfUrlProperty(): string
    {
        return $this->armarUrlPdf(ListadoDocentesExportParams::normalizarSubtitulo($this->subtituloListado));
    }

    public function getPdfUrlBaseProperty(): string
    {
        return $this->armarUrlPdf('');
    }

    private function armarUrlPdf(string $subtitulo): string
    {
        if (! $this->puedeGenerarExport()) {
            return '#';
        }

        $campos = ListadoDocentesPdfFieldCatalog::normalizeSelection($this->camposSeleccionados);

        $ids = collect($this->rolesElegidos)
            ->filter(fn ($v) => $v !== '' && $v !== null)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        $params = [
            'roles' => $ids->implode(','),
            'campos' => implode(',', $campos),
        ];

        if ($subtitulo !== '') {
            $params['subtitulo'] = $subtitulo;
        }

        return route('listados.docentes.pdf', $params);
    }

    public function puedeGenerarExport(): bool
    {
        return collect($this->rolesElegidos)
            ->filter(fn ($v) => $v !== '' && $v !== null)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->isNotEmpty();
    }

    public function getExcelUrlCompletoProperty(): string
    {
        return route('listados.docentes.excel');
    }

    public function getExcelUrlSeleccionProperty(): string
    {
        if (! $this->puedeGenerarExport()) {
            return '#';
        }

        $campos = ListadoDocentesPdfFieldCatalog::normalizeSelection($this->camposSeleccionados);

        $ids = collect($this->rolesElegidos)
            ->filter(fn ($v) => $v !== '' && $v !== null)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        return route('listados.docentes.excel', [
            'roles' => $ids->implode(','),
            'campos' => implode(',', $campos),
        ]);
    }

    public function puedeExportarExcelCompleto(): bool
    {
        return ListadoDocentesConsulta::rolesDisponibles()->isNotEmpty();
    }

    public function seleccionarSoloDefecto(): void
    {
        $this->camposSeleccionados = CampoProfesor::aplicarVisibilidadListadoPdf(ListadoDocentesPdfFieldCatalog::DEFAULT_KEYS);
    }

    public function seleccionarTodos(): void
    {
        if (! puedeModificarLegajosDocentes()) {
            $this->seleccionarSoloDefecto();

            return;
        }

        $soloLegajos = CampoProfesor::columnasProfesoresVisiblesParaUi();
        $this->camposSeleccionados = collect(ListadoDocentesPdfFieldCatalog::allowedKeys())
            ->filter(function (string $k) use ($soloLegajos) {
                if (! str_starts_with($k, 'profesores.')) {
                    return false;
                }
                if ($soloLegajos === null) {
                    return true;
                }

                return in_array(substr($k, strlen('profesores.')), $soloLegajos, true);
            })
            ->values()
            ->all();
    }

    /** @return Collection<string, int> */
    private function idsRolesPermitidosComoString(): Collection
    {
        return ListadoDocentesConsulta::rolesDisponibles()
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(string) (int) $id => (int) $id]);
    }
}
