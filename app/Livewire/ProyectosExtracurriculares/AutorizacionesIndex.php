<?php

namespace App\Livewire\ProyectosExtracurriculares;

use App\Models\ExtActividad;
use App\Support\Navegacion\MenuSecretariaPerfil;
use App\Support\PermisosIaCatalog;
use App\Support\ProyectosExtracurriculares\ExtActividadesService;
use Livewire\Component;
use Livewire\WithPagination;

class AutorizacionesIndex extends Component
{
    use WithPagination;

    public const POR_PAGINA = 50;

    public string $buscar = '';

    public function mount(): void
    {
        $this->autorizarAcceso();
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->autorizarAcceso();

        $tablasOk = ExtActividadesService::tablasDisponibles();
        $registros = null;

        if ($tablasOk) {
            $q = ExtActividadesService::scopedQuery()
                ->with(['fechas', 'cursos.curso'])
                ->withCount('alumnos')
                ->where('estado', ExtActividad::ESTADO_APROBADO)
                ->orderByDesc('id');

            $termino = trim($this->buscar);
            if ($termino !== '') {
                $like = '%'.addcslashes($termino, '%_\\').'%';
                $q->where(function ($w) use ($like) {
                    $w->where('nombre', 'like', $like)
                        ->orWhere('lugar', 'like', $like);
                });
            }

            $registros = $q->paginate(self::POR_PAGINA);
        }

        return view('livewire.proyectos-extracurriculares.autorizaciones-index', [
            'tablasOk' => $tablasOk,
            'mensajeTabla' => $tablasOk ? '' : ExtActividadesService::mensajeTablasFaltantes(),
            'registros' => $registros,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Autorizaciones y notificaciones']);
    }

    private function autorizarAcceso(): void
    {
        abort_unless(MenuSecretariaPerfil::muestraProyectosExtracurriculares(), 403);
        abort_unless(
            tienePermiso(PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_DOCUMENTOS),
            403,
            'Sin permiso para autorizaciones de actividades extracurriculares.',
        );

        $ctx = schoolCtx();
        abort_unless($ctx->idNivel > 0 && $ctx->idTerlec > 0, 403, 'Seleccione nivel y ciclo lectivo en el contexto activo.');
    }
}
