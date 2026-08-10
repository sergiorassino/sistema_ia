<?php

namespace App\Livewire\Abm\Legajos;

use App\Models\Legajo;
use App\Support\Abm\LegajoDependenciasEliminacion;
use App\Support\Auth\LegajoPasswordLectura;
use App\Support\PermisosIaCatalog;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

class LegajosIndex extends Component
{
    use WithPagination;

    public const SESSION_SEARCH = 'legajos_index_search';

    public const SESSION_SOLO_MATRICULA = 'legajos_index_solo_matricula';

    public const SESSION_SOLO_MI_NIVEL = 'legajos_index_solo_mi_nivel';

    // List state
    public string $search        = '';
    public bool   $soloMatricula = false;
    public bool   $soloMiNivel    = false;
    public ?int   $focusId       = null;

    public bool   $showConfirm  = false;
    public ?int   $deleteId     = null;
    public string $deleteInfo   = '';

    public bool $showPasswordModal = false;

    public string $passwordModalEstudiante = '';

    public string $passwordModalTexto = '';

    public bool $passwordModalEncriptada = false;

    /** @var array<string, array{except?: mixed, as?: string}> */
    protected $queryString = [
        'search' => ['except' => '', 'as' => 'buscar'],
        'soloMatricula' => ['except' => false],
        'soloMiNivel' => ['except' => false],
    ];

    public function mount(): void
    {
        $focus = (int) session()->pull('legajo_listado_focus', 0);
        if ($focus <= 0) {
            $focus = request()->integer('focus');
        }
        $this->focusId = $focus > 0 ? $focus : null;
        $this->persistirFiltrosEnSesion();
    }

    /**
     * URL del listado con los filtros guardados en sesión (p. ej. al volver del formulario).
     *
     * @param  array<string, mixed>  $extra
     */
    public static function urlIndiceConFiltrosGuardados(array $extra = []): string
    {
        return route('abm.legajos', array_merge(self::parametrosFiltrosGuardados(), $extra));
    }

    /**
     * @return array{search: string, soloMatricula: bool, soloMiNivel: bool}
     */
    public static function sessionFiltros(): array
    {
        return [
            'search' => trim((string) session(self::SESSION_SEARCH, '')),
            'soloMatricula' => (bool) session(self::SESSION_SOLO_MATRICULA, false),
            'soloMiNivel' => (bool) session(self::SESSION_SOLO_MI_NIVEL, false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function parametrosFiltrosGuardados(): array
    {
        $f = self::sessionFiltros();
        $params = [];

        if ($f['search'] !== '') {
            $params['buscar'] = $f['search'];
        }
        if ($f['soloMatricula']) {
            $params['soloMatricula'] = 1;
        }
        if ($f['soloMiNivel']) {
            $params['soloMiNivel'] = 1;
        }

        return $params;
    }

    protected function scopedLegajoOrFail(int $id): Legajo
    {
        return Legajo::query()
            ->whereKey($id)
            ->firstOrFail();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->persistirFiltrosEnSesion();
    }

    public function updatedSoloMatricula(): void
    {
        $this->resetPage();
        $this->persistirFiltrosEnSesion();
    }

    public function updatedSoloMiNivel(): void
    {
        $this->resetPage();
        $this->persistirFiltrosEnSesion();
    }

    private function persistirFiltrosEnSesion(): void
    {
        session([
            self::SESSION_SEARCH => $this->search,
            self::SESSION_SOLO_MATRICULA => $this->soloMatricula,
            self::SESSION_SOLO_MI_NIVEL => $this->soloMiNivel,
        ]);
    }

    public function verPassword(int $id): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::LEGAJOS_ESTUDIANTES_VER_CONTRASEÑA), 403, 'Sin permiso para ver contraseñas de estudiantes.');

        $key = 'legajos:ver-pwrd:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $l = $this->scopedLegajoOrFail($id);
        $lectura = LegajoPasswordLectura::paraMostrar($l);

        $this->passwordModalEstudiante = "{$l->apellido}, {$l->nombre}";
        $this->passwordModalTexto = $lectura['texto'];
        $this->passwordModalEncriptada = $lectura['encriptada'];
        $this->showPasswordModal = true;
    }

    public function cerrarPasswordModal(): void
    {
        $this->showPasswordModal = false;
        $this->reset('passwordModalEstudiante', 'passwordModalTexto', 'passwordModalEncriptada');
    }

    public function confirmDelete(int $id): void
    {
        abort_unless(puedeModificarLegajosEstudiantes(), 403, 'Sin permiso para eliminar legajos de estudiantes.');

        $l = $this->scopedLegajoOrFail($id);
        $deps = LegajoDependenciasEliminacion::paraLegajo($id);

        if ($deps !== []) {
            $detail = LegajoDependenciasEliminacion::resumen($deps);
            $this->deleteInfo = "No se puede eliminar el legajo de {$l->apellido}, {$l->nombre} porque tiene: {$detail}.";
            $this->deleteId = null;
        } else {
            $this->deleteId = $id;
            $this->deleteInfo = "¿Confirma eliminar el legajo de {$l->apellido}, {$l->nombre}?";
        }

        $this->showConfirm = true;
    }

    public function delete(): void
    {
        abort_unless(puedeModificarLegajosEstudiantes(), 403, 'Sin permiso para eliminar legajos de estudiantes.');

        $key = 'legajos:delete:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('success', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showConfirm = false;
            $this->reset('deleteId', 'deleteInfo');
            return;
        }
        RateLimiter::hit($key, 60);

        if ($this->deleteId) {
            $deps = LegajoDependenciasEliminacion::paraLegajo((int) $this->deleteId);
            if ($deps !== []) {
                $l = $this->scopedLegajoOrFail((int) $this->deleteId);
                $detail = LegajoDependenciasEliminacion::resumen($deps);
                $this->deleteId = null;
                $this->deleteInfo = "No se puede eliminar el legajo de {$l->apellido}, {$l->nombre} porque tiene: {$detail}.";
                $this->showConfirm = true;
                $this->dispatch('se-swal-error', mensaje: $this->deleteInfo);

                return;
            }

            $l = $this->scopedLegajoOrFail($this->deleteId);
            $nombre = "{$l->apellido}, {$l->nombre}";

            try {
                $l->delete();
            } catch (QueryException $e) {
                report($e);
                $msg = LegajoDependenciasEliminacion::mensajeDesdeQueryException($e, "el legajo de {$nombre}")
                    ?? "No se puede eliminar el legajo de {$nombre} porque tiene registros relacionados en otros módulos.";
                $this->deleteId = null;
                $this->deleteInfo = $msg;
                $this->showConfirm = true;
                $this->dispatch('se-swal-error', mensaje: $msg);

                return;
            }

            session()->flash('success', "Legajo de {$nombre} eliminado.");
        }

        $this->showConfirm = false;
        $this->reset('deleteId', 'deleteInfo');
    }

    public function render()
    {
        $idTerlec = schoolCtx()->idTerlec;

        $query = Legajo::with([
            'familia',
            'matriculas' => function ($q) {
                $q->with(['terlec', 'curso', 'condicion', 'nivel'])
                    ->leftJoin('terlec', 'terlec.id', '=', 'matricula.idTerlec')
                    ->orderBy('terlec.ano')
                    ->orderBy('matricula.id')
                    ->select('matricula.*');
            },
        ]);

        if ($this->search !== '') {
            $query->buscar($this->search);
        }

        if ($this->soloMatricula) {
            $query->whereHas('matriculas', fn ($q) => $q
                ->where('idTerlec', $idTerlec));
        }

        if ($this->soloMiNivel) {
            $query->whereHas('matriculas', function ($q) use ($idTerlec) {
                $q->where('idTerlec', $idTerlec);
                SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($q, 'idNivel');
            });
        }

        $legajos  = $query->orderBy('apellido')->orderBy('nombre')->paginate(25);

        return view('livewire.abm.legajos.index2', compact('legajos'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Legajos de Estudiantes']);
    }
}
