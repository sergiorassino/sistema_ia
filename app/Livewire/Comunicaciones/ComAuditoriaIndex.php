<?php

namespace App\Livewire\Comunicaciones;

use App\Comunicaciones\ComunicacionesRepository;
use App\Models\ComAuditoria;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class ComAuditoriaIndex extends Component
{
    use WithPagination;

    /** todos|borrar|leido|no_leido */
    public string $filtroAccion = 'todos';

    /** todos|estudiante|profesor|personal */
    public string $filtroCategoria = 'todos';

    public string $periodo = 'actual';

    public ?int $idProfesorObjetivo = null;

    public ?int $idLegajoObjetivo = null;

    public ?string $usuarioObjetivoLabel = null;

    public string $usuarioSearch = '';

    /** @var list<array{tipo:string,categoria:string,id:int,label:string,dni:?string}> */
    public array $usuarioResults = [];

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::COM_AUDITORIA), 403, 'Sin permiso para auditoría de comunicaciones.');
    }

    public function limpiarUsuario(): void
    {
        $this->idProfesorObjetivo   = null;
        $this->idLegajoObjetivo     = null;
        $this->usuarioObjetivoLabel = null;
        $this->usuarioSearch        = '';
        $this->usuarioResults       = [];
        $this->resetPage();
    }

    public function updatedUsuarioSearch(): void
    {
        $ctx = schoolCtx();
        $this->usuarioResults = ComunicacionesRepository::buscarUsuariosAuditoria(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            $this->usuarioSearch,
            $this->filtroCategoria === 'todos' ? 'todos' : $this->filtroCategoria,
            15
        );
    }

    public function updatedFiltroCategoria(): void
    {
        $this->usuarioResults = [];
        $this->resetPage();
    }

    public function updatedFiltroAccion(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodo(): void
    {
        $this->resetPage();
    }

    public function selectUsuario(string $tipo, int $id, string $label): void
    {
        $this->idProfesorObjetivo   = null;
        $this->idLegajoObjetivo     = null;

        if ($tipo === 'estudiante') {
            $this->idLegajoObjetivo = $id;
        } else {
            $this->idProfesorObjetivo = $id;
        }

        $this->usuarioObjetivoLabel = trim($label);
        $this->usuarioSearch        = '';
        $this->usuarioResults       = [];
        $this->resetPage();
    }

    public function render()
    {
        $ctx = schoolCtx();

        $registros = collect();
        if (Schema::hasTable('com_auditoria')) {
            $query = ComAuditoria::query()
                ->where('id_nivel', (int) $ctx->idNivel);

            if ($this->periodo === 'actual') {
                $query->where('id_terlec', (int) $ctx->idTerlec);
            }

            if ($this->filtroAccion === 'borrar') {
                $query->whereIn('accion', [
                    ComAuditoria::ACCION_BORRAR_MENSAJE,
                    ComAuditoria::ACCION_BORRAR_HILO,
                ]);
            } elseif ($this->filtroAccion === 'leido') {
                $query->where('accion', ComAuditoria::ACCION_MARCAR_LEIDO);
            } elseif ($this->filtroAccion === 'no_leido') {
                $query->where('accion', ComAuditoria::ACCION_MARCAR_NO_LEIDO);
            }

            if ($this->filtroCategoria !== 'todos') {
                $query->where('actor_categoria', $this->filtroCategoria);
            }

            if ($this->idLegajoObjetivo) {
                $query->where('tipo_actor', 'familia')
                    ->where('id_legajo_actor', $this->idLegajoObjetivo);
            } elseif ($this->idProfesorObjetivo) {
                $query->where('tipo_actor', 'profesor')
                    ->where('id_profesor_actor', $this->idProfesorObjetivo);
            }

            $registros = $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(25);
        }

        return view('comunicaciones::livewire.comunicaciones.com-auditoria-index', [
            'registros' => $registros,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Auditoría comunicación institucional']);
    }
}
