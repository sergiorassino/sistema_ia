<?php

namespace App\Livewire\Abm\LegajosProfesor;

use App\Models\Profesor;
use App\Models\ProfesorTipo;
use App\Support\Auth\ProfesorPasswordLectura;
use App\Support\PermisosIaCatalog;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class LegajosProfesorIndex extends Component
{
    use WithPagination;

    /** Valor especial del filtro: incluir todos los roles (también «Sin Rol»). */
    private const FILTRO_ROL_TODOS = 'todos';

    /** IdTipoProf que identifica «Sin Rol» en la tabla profesortipo. */
    private const ID_TIPO_SIN_ROL = 1;

    public string $search = '';

    /**
     * Filtro de rol (IdTipoProf):
     *  - ''        → activos: excluye «Sin Rol» (valor por defecto).
     *  - 'todos'   → incluye todos (también «Sin Rol»).
     *  - '<id>'    → filtra por ese IdTipoProf específico.
     */
    public string $filtroRol = '';

    public ?int $focusId = null;

    public bool $showConfirm = false;

    public ?int $deleteId = null;

    public string $deleteInfo = '';

    public bool $puedeEliminar = true;

    public bool $showPasswordModal = false;

    public string $passwordModalDocente = '';

    public string $passwordModalTexto = '';

    public bool $passwordModalEncriptada = false;

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::LEGAJOS_DOCENTES), 403, 'Sin permiso para legajos de docentes.');

        $focus = request()->integer('focus');
        $this->focusId = $focus > 0 ? $focus : null;

        if ($this->focusId) {
            $this->setPage(self::paginaParaProfesor($this->focusId, 25, $this->filtroRol, $this->search));
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroRol(): void
    {
        $this->resetPage();
    }

    protected function scopedProfesorOrFail(int $id): Profesor
    {
        return Profesor::query()
            ->delNivel(SchoolAlcancePedagogico::idNivelLegajosDocente())
            ->whereKey($id)
            ->firstOrFail();
    }

    public function confirmDelete(int $id): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::LEGAJOS_DOCENTES), 403, 'Sin permiso para eliminar legajos de docentes.');

        $p = $this->scopedProfesorOrFail($id);
        $deps = $this->dependenciasParaBorrar($id);

        if ($deps !== []) {
            $modulos = collect($deps)
                ->map(fn (int $cant, string $modulo) => "{$modulo} ({$cant})")
                ->implode(', ');
            $this->puedeEliminar = false;
            $this->deleteId = null;
            $this->deleteInfo = "No se puede eliminar el legajo de {$p->apellido}, {$p->nombre} porque tiene: {$modulos}.";
        } else {
            $this->puedeEliminar = true;
            $this->deleteId = $id;
            $this->deleteInfo = "¿Confirma eliminar el legajo de {$p->apellido}, {$p->nombre} en este nivel?";
        }

        $this->showConfirm = true;
    }

    public function verPassword(int $id): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::LEGAJOS_DOCENTES_VER_CONTRASEÑA), 403, 'Sin permiso para ver contraseñas de docentes.');

        $key = 'legajos-profesor:ver-pwrd:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $p = $this->scopedProfesorOrFail($id);
        $lectura = ProfesorPasswordLectura::paraMostrar($p);

        $this->passwordModalDocente = "{$p->apellido}, {$p->nombre}";
        $this->passwordModalTexto = $lectura['texto'];
        $this->passwordModalEncriptada = $lectura['encriptada'];
        $this->showPasswordModal = true;
    }

    public function cerrarPasswordModal(): void
    {
        $this->showPasswordModal = false;
        $this->reset('passwordModalDocente', 'passwordModalTexto', 'passwordModalEncriptada');
    }

    public function delete(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::LEGAJOS_DOCENTES), 403, 'Sin permiso para eliminar legajos de docentes.');

        $key = 'legajos-profesor:delete:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('success', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showConfirm = false;
            $this->reset('deleteId', 'deleteInfo', 'puedeEliminar');

            return;
        }
        RateLimiter::hit($key, 60);

        if ($this->deleteId && $this->puedeEliminar) {
            $deps = $this->dependenciasParaBorrar($this->deleteId);
            if ($deps !== []) {
                $this->puedeEliminar = false;
                $this->showConfirm = true;

                return;
            }

            $p = $this->scopedProfesorOrFail($this->deleteId);
            $nombre = "{$p->apellido}, {$p->nombre}";
            $p->delete();
            session()->flash('success', "Legajo de {$nombre} eliminado.");
        }

        $this->showConfirm = false;
        $this->reset('deleteId', 'deleteInfo', 'puedeEliminar');
        $this->puedeEliminar = true;
    }

    /**
     * @return array<string, int>
     */
    private function dependenciasParaBorrar(int $id): array
    {
        $checks = [
            'ppc' => ['col' => 'idProfesor', 'label' => 'Asignación por materia'],
        ];

        if (Schema::hasTable('licencias')) {
            $checks['licencias'] = ['col' => 'idPersonal', 'label' => 'Licencias'];
        }

        $deps = [];
        foreach ($checks as $tabla => $meta) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            $cant = (int) DB::table($tabla)->where($meta['col'], $id)->count();
            if ($cant > 0) {
                $deps[$meta['label']] = $cant;
            }
        }

        return $deps;
    }

    public static function paginaParaProfesor(
        int $id,
        int $perPage = 25,
        string $filtroRol = '',
        string $search = '',
    ): int {
        $query = self::queryListado($filtroRol, $search);

        $profesor = (clone $query)->whereKey($id)->first(['id', 'apellido', 'nombre']);
        if ($profesor === null) {
            return 1;
        }

        $countBefore = (clone $query)->where(function ($q) use ($profesor) {
            $q->where('apellido', '<', $profesor->apellido)
                ->orWhere(function ($q2) use ($profesor) {
                    $q2->where('apellido', $profesor->apellido)
                        ->where('nombre', '<', $profesor->nombre);
                });
        })->count();

        return max(1, (int) floor($countBefore / $perPage) + 1);
    }

    public function render()
    {
        $profesores = self::queryListado($this->filtroRol, $this->search)
            ->with('tipo')
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->paginate(25);

        $roles = ProfesorTipo::query()
            ->orderBy('tipo')
            ->get(['id', 'tipo']);

        return view('livewire.abm.legajos-profesor.index', compact('profesores', 'roles'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Legajos del docente']);
    }

    /**
     * Query base del listado (nivel, búsqueda y filtro de rol).
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Profesor>
     */
    private static function queryListado(string $filtroRol = '', string $search = '')
    {
        $query = Profesor::query()
            ->delNivel(SchoolAlcancePedagogico::idNivelLegajosDocente());

        if ($search !== '') {
            $query->buscar($search);
        }

        self::aplicarFiltroRolEnQuery($query, $filtroRol);

        return $query;
    }

    /**
     * Aplica el filtro por rol al query. Por defecto excluye «Sin Rol» (IdTipoProf = 1):
     * son docentes que ya no están en la escuela y no deben aparecer salvo selección explícita.
     */
    private static function aplicarFiltroRolEnQuery($query, string $filtroRol): void
    {
        $valor = trim($filtroRol);

        if ($valor === self::FILTRO_ROL_TODOS) {
            return;
        }

        if ($valor === '') {
            $query->where(function ($w) {
                $w->whereNull('IdTipoProf')
                    ->orWhere('IdTipoProf', '<>', self::ID_TIPO_SIN_ROL);
            });

            return;
        }

        if (ctype_digit($valor)) {
            $query->where('IdTipoProf', (int) $valor);
        }
    }
}
