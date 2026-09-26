<?php

namespace App\Livewire\ProyectosExtracurriculares;

use App\Models\ExtActividad;
use App\Models\Matricula;
use App\Support\Navegacion\MenuSecretariaPerfil;
use App\Support\PermisosIaCatalog;
use App\Support\ProyectosExtracurriculares\AutorizacionActividadDatos;
use App\Support\ProyectosExtracurriculares\ExtActividadesService;
use Illuminate\Support\Collection;
use Livewire\Component;

class AutorizacionAlumnos extends Component
{
    public const MAX_PDF = 200;

    public int $actividadId;

    public string $actividadNombre = '';

    public string $resumenFechas = '';

    public string $tipo = 'autorizacion';

    /** @var list<string> */
    public array $matriculasSeleccionadas = [];

    public function mount(int $id): void
    {
        $this->autorizarAcceso();
        abort_unless(ExtActividadesService::tablasDisponibles(), 404, ExtActividadesService::mensajeTablasFaltantes());

        $this->tipo = request()->routeIs('proyectosExtracurriculares.notificaciones.alumnos')
            ? 'notificacion'
            : 'autorizacion';

        $actividad = $this->actividadAprobada($id);
        $this->actividadId = (int) $actividad->id;
        $this->actividadNombre = trim((string) $actividad->nombre);
        $actividad->loadMissing('fechas');
        $this->resumenFechas = ExtActividadesService::textoResumenFechas($actividad);
        $this->matriculasSeleccionadas = $this->idsComoTexto($this->matriculasInvolucradas($actividad));
    }

    public function updatedMatriculasSeleccionadas(): void
    {
        $this->normalizarSeleccion();
    }

    public function seleccionarTodasMatriculas(): void
    {
        $this->matriculasSeleccionadas = $this->idsComoTexto($this->matriculasInvolucradas());
    }

    public function quitarTodasMatriculas(): void
    {
        $this->matriculasSeleccionadas = [];
    }

    public function toggleSeleccionTodas(): void
    {
        if ($this->todasMarcadas()) {
            $this->quitarTodasMatriculas();
        } else {
            $this->seleccionarTodasMatriculas();
        }
    }

    public function render()
    {
        $this->autorizarAcceso();

        $matriculas = $this->matriculasInvolucradas();
        $idsPdf = $this->idsSeleccionadosValidos($matriculas);
        $cantidad = count($idsPdf);

        return view('livewire.proyectos-extracurriculares.autorizacion-alumnos', [
            'matriculas' => $matriculas,
            'idsPdf' => $idsPdf,
            'cantidadSeleccionados' => $cantidad,
            'puedePdf' => $this->tipo === 'autorizacion' && $cantidad > 0 && $cantidad <= self::MAX_PDF,
            'excedeMaximo' => $cantidad > self::MAX_PDF,
            'maxPdf' => self::MAX_PDF,
            'todasMarcadas' => $this->todasMarcadas($matriculas),
            'esAutorizacion' => $this->tipo === 'autorizacion',
            'resumenFechas' => $this->resumenFechas,
        ])->layout(layoutMenuStaff(), [
            'pageTitle' => $this->tipo === 'autorizacion' ? 'Autorizaciones' : 'Notificaciones',
        ]);
    }

    private function autorizarAcceso(): void
    {
        abort_unless(MenuSecretariaPerfil::muestraProyectosExtracurriculares(), 403);
        abort_unless(tienePermiso(PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_DOCUMENTOS), 403);

        $ctx = schoolCtx();
        abort_unless($ctx->idNivel > 0 && $ctx->idTerlec > 0, 403, 'Seleccione nivel y ciclo lectivo en el contexto activo.');
    }

    private function actividadAprobada(?int $id = null): ExtActividad
    {
        $id = $id ?? $this->actividadId;
        $actividad = ExtActividadesService::scopedQuery()
            ->whereKey($id)
            ->where('estado', ExtActividad::ESTADO_APROBADO)
            ->first();
        abort_if($actividad === null, 404, 'La actividad no está aprobada en el ciclo y nivel actuales.');

        return $actividad;
    }

    /**
     * @return Collection<int, Matricula>
     */
    private function matriculasInvolucradas(?ExtActividad $actividad = null): Collection
    {
        return AutorizacionActividadDatos::matriculasInvolucradas($actividad ?? $this->actividadAprobada());
    }

    /**
     * @param  Collection<int, Matricula>|null  $matriculas
     */
    private function todasMarcadas(?Collection $matriculas = null): bool
    {
        $permitidos = $this->idsComoTexto($matriculas ?? $this->matriculasInvolucradas());
        if ($permitidos === []) {
            return false;
        }

        $marcados = collect($this->matriculasSeleccionadas)
            ->map(static fn ($id) => (string) $id)
            ->filter(static fn (string $id) => $id !== '')
            ->sort()
            ->values()
            ->all();
        sort($permitidos);

        return $marcados === $permitidos;
    }

    /**
     * @param  Collection<int, Matricula>  $matriculas
     * @return list<int>
     */
    private function idsSeleccionadosValidos(Collection $matriculas): array
    {
        $permitidos = array_fill_keys(
            $matriculas->pluck('id')->map(static fn ($id) => (int) $id)->all(),
            true
        );

        return collect($this->matriculasSeleccionadas)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0 && isset($permitidos[$id]))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function normalizarSeleccion(): void
    {
        $this->matriculasSeleccionadas = array_map(
            static fn (int $id) => (string) $id,
            $this->idsSeleccionadosValidos($this->matriculasInvolucradas())
        );
    }

    /**
     * @param  Collection<int, Matricula>  $matriculas
     * @return list<string>
     */
    private function idsComoTexto(Collection $matriculas): array
    {
        return $matriculas
            ->pluck('id')
            ->map(static fn ($id) => (string) $id)
            ->values()
            ->all();
    }
}
