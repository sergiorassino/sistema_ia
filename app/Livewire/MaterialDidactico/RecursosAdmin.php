<?php

namespace App\Livewire\MaterialDidactico;

use App\Models\RrdGrupo;
use App\Models\RrdRecurso;
use App\Models\RrdRecursoDisponibilidad;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class RecursosAdmin extends Component
{
    // ---------------------------------------------------------------
    // Panel activo: 'grupos' | 'recursos' | 'disponibilidad'
    // ---------------------------------------------------------------
    public string $panel = 'grupos';

    // ---------------------------------------------------------------
    // Grupo
    // ---------------------------------------------------------------
    public bool   $mostrarFormGrupo = false;
    public ?int   $grupoEditId  = null;
    public string $grupoNombre  = '';
    public int    $grupoOrden   = 0;
    public bool   $grupoActivo  = true;

    // ---------------------------------------------------------------
    // Recurso
    // ---------------------------------------------------------------
    public ?int   $filtroGrupoId  = null;
    public bool   $mostrarFormRecurso = false;
    public ?int   $recursoEditId  = null;
    public string $recursoNombre  = '';
    public int    $recursoGrupoId = 0;
    public int    $recursoAntelacion = 0;
    public int    $recursoOrden   = 0;
    public bool   $recursoActivo  = true;
    public bool   $recursoSiempreDisponible = false;

    // ---------------------------------------------------------------
    // Disponibilidad
    // ---------------------------------------------------------------
    public ?int   $dispRecursoId  = null;
    public bool   $mostrarFormDisp = false;
    public ?int   $dispEditId     = null;
    public int    $dispDia        = 1;
    public string $dispHoraInicio = '';
    public string $dispHoraFin    = '';

    public function mount(): void
    {
        abort_unless(rrdRol() === 'admin', 403);
    }

    // ---------------------------------------------------------------
    // GRUPOS
    // ---------------------------------------------------------------

    public function abrirFormGrupo(?int $id = null): void
    {
        $this->resetValidation();
        $this->grupoEditId = $id;
        if ($id) {
            $g = RrdGrupo::enContexto()->findOrFail($id);
            $this->grupoNombre = $g->nombre;
            $this->grupoOrden  = $g->orden;
            $this->grupoActivo = $g->activo;
        } else {
            $this->grupoNombre = '';
            $this->grupoOrden  = 0;
            $this->grupoActivo = true;
        }
        $this->mostrarFormGrupo = true;
    }

    public function cerrarFormGrupo(): void
    {
        $this->mostrarFormGrupo = false;
        $this->grupoEditId      = null;
    }

    public function guardarGrupo(): void
    {
        $this->validate([
            'grupoNombre' => 'required|string|max:120',
            'grupoOrden'  => 'integer|min:0',
            'grupoActivo' => 'boolean',
        ], ['grupoNombre.required' => 'El nombre es obligatorio.']);

        $ctx = schoolCtx();
        $data = [
            'nombre' => trim($this->grupoNombre),
            'orden'  => $this->grupoOrden,
            'activo' => $this->grupoActivo,
        ];

        if ($this->grupoEditId) {
            RrdGrupo::enContexto()->findOrFail($this->grupoEditId)->update($data);
        } else {
            $data['id_nivel'] = (int) ($ctx->idNivel ?? 0);
            RrdGrupo::create($data);
        }

        $this->cerrarFormGrupo();
    }

    public function eliminarGrupo(int $id): void
    {
        $grupo = RrdGrupo::enContexto()->findOrFail($id);

        if ($grupo->recursos()->exists()) {
            $this->dispatch('se-swal-error', mensaje: 'No se puede eliminar un grupo con recursos asociados. Primero elimine o mueva los recursos.');
            return;
        }

        $grupo->delete();
        $this->dispatch('se-swal-exito', mensaje: 'Grupo eliminado.');
    }

    // ---------------------------------------------------------------
    // RECURSOS
    // ---------------------------------------------------------------

    public function verRecursosDeGrupo(?int $grupoId): void
    {
        $this->filtroGrupoId = $grupoId;
        $this->panel         = 'recursos';
    }

    public function abrirFormRecurso(?int $id = null): void
    {
        $this->resetValidation();
        $this->recursoEditId = $id;
        if ($id) {
            $r = RrdRecurso::enContexto()->findOrFail($id);
            $this->recursoNombre              = $r->nombre;
            $this->recursoGrupoId             = $r->id_grupo;
            $this->recursoAntelacion          = $r->antelacion_min_horas;
            $this->recursoOrden               = $r->orden;
            $this->recursoActivo              = $r->activo;
            $this->recursoSiempreDisponible   = (bool) ($r->siempre_disponible ?? false);
        } else {
            $this->recursoNombre              = '';
            $this->recursoGrupoId             = (int) ($this->filtroGrupoId ?? 0);
            $this->recursoAntelacion          = 0;
            $this->recursoOrden               = 0;
            $this->recursoActivo              = true;
            $this->recursoSiempreDisponible   = false;
        }
        $this->mostrarFormRecurso = true;
    }

    public function cerrarFormRecurso(): void
    {
        $this->mostrarFormRecurso = false;
        $this->recursoEditId      = null;
    }

    public function guardarRecurso(): void
    {
        $this->validate([
            'recursoNombre'           => 'required|string|max:120',
            'recursoGrupoId'          => 'required|integer|min:1',
            'recursoAntelacion'       => 'integer|min:0',
            'recursoOrden'            => 'integer|min:0',
            'recursoActivo'           => 'boolean',
            'recursoSiempreDisponible' => 'boolean',
        ], [
            'recursoNombre.required'  => 'El nombre es obligatorio.',
            'recursoGrupoId.required' => 'Seleccione el grupo.',
        ]);

        $ctx = schoolCtx();

        // Catálogo compartido: cualquier grupo activo del colegio es válido
        RrdGrupo::enContexto()->findOrFail($this->recursoGrupoId);

        $data = [
            'id_grupo'             => $this->recursoGrupoId,
            'nombre'               => trim($this->recursoNombre),
            'antelacion_min_horas' => $this->recursoAntelacion,
            'orden'                => $this->recursoOrden,
            'activo'               => $this->recursoActivo,
            'siempre_disponible'   => $this->recursoSiempreDisponible,
        ];

        if ($this->recursoEditId) {
            RrdRecurso::enContexto()->findOrFail($this->recursoEditId)->update($data);
        } else {
            $data['id_nivel'] = (int) ($ctx->idNivel ?? 0);
            RrdRecurso::create($data);
        }

        $this->cerrarFormRecurso();
    }

    public function eliminarRecurso(int $id): void
    {
        $recurso = RrdRecurso::enContexto()->findOrFail($id);

        if ($recurso->reservas()->whereNotIn('estado', ['cancelado', 'devuelto'])->exists()) {
            $this->dispatch('se-swal-error', mensaje: 'No se puede eliminar un recurso con reservas activas.');
            return;
        }

        $recurso->delete();
        $this->dispatch('se-swal-exito', mensaje: 'Recurso eliminado.');
    }

    // ---------------------------------------------------------------
    // DISPONIBILIDAD
    // ---------------------------------------------------------------

    public function verDisponibilidad(int $recursoId): void
    {
        $this->dispRecursoId = $recursoId;
        $this->panel         = 'disponibilidad';
    }

    public function abrirFormDisp(?int $id = null): void
    {
        $this->resetValidation();
        $this->dispEditId = $id;
        if ($id) {
            $d = RrdRecursoDisponibilidad::whereHas('recurso', function ($q) {
                $q->enContexto();
            })->findOrFail($id);
            $this->dispDia        = $d->dia_semana;
            $this->dispHoraInicio = substr($d->hora_inicio, 0, 5);
            $this->dispHoraFin    = substr($d->hora_fin, 0, 5);
        } else {
            $this->dispDia        = 1;
            $this->dispHoraInicio = '';
            $this->dispHoraFin    = '';
        }
        $this->mostrarFormDisp = true;
    }

    public function cerrarFormDisp(): void
    {
        $this->mostrarFormDisp = false;
        $this->dispEditId      = null;
    }

    public function guardarDisp(): void
    {
        $this->validate([
            'dispDia'        => 'required|integer|min:1|max:7',
            'dispHoraInicio' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'dispHoraFin'    => ['required', 'regex:/^\d{2}:\d{2}$/', 'after:dispHoraInicio'],
        ], [
            'dispDia.required'        => 'Seleccione el día.',
            'dispHoraInicio.required' => 'Ingrese la hora de inicio.',
            'dispHoraFin.required'    => 'Ingrese la hora de fin.',
            'dispHoraFin.after'       => 'La hora de fin debe ser posterior a la de inicio.',
        ]);

        RrdRecurso::enContexto()->findOrFail($this->dispRecursoId);

        $data = [
            'id_recurso'  => $this->dispRecursoId,
            'dia_semana'  => $this->dispDia,
            'hora_inicio' => $this->dispHoraInicio,
            'hora_fin'    => $this->dispHoraFin,
        ];

        if ($this->dispEditId) {
            RrdRecursoDisponibilidad::findOrFail($this->dispEditId)->update($data);
        } else {
            RrdRecursoDisponibilidad::create($data);
        }

        $this->cerrarFormDisp();
    }

    public function eliminarDisp(int $id): void
    {
        RrdRecursoDisponibilidad::whereHas('recurso', function ($q) {
            $q->enContexto();
        })->findOrFail($id)->delete();
        $this->dispatch('se-swal-exito', mensaje: 'Ventana eliminada.');
    }

    // ---------------------------------------------------------------
    // Render
    // ---------------------------------------------------------------

    public function render()
    {
        $grupos = RrdGrupo::query()
            ->enContexto()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->withCount('recursos')
            ->get();

        $recursos = collect();
        if ($this->panel === 'recursos') {
            $q = RrdRecurso::query()->enContexto()
                ->with('grupo')
                ->orderBy('orden')
                ->orderBy('nombre');
            if ($this->filtroGrupoId) {
                $q->where('id_grupo', $this->filtroGrupoId);
            }
            $recursos = $q->get();
        }

        $disponibilidades = collect();
        $recursoActual    = null;
        if ($this->panel === 'disponibilidad' && $this->dispRecursoId) {
            $recursoActual    = RrdRecurso::enContexto()->find($this->dispRecursoId);
            $disponibilidades = RrdRecursoDisponibilidad::where('id_recurso', $this->dispRecursoId)
                ->orderBy('dia_semana')
                ->orderBy('hora_inicio')
                ->get();
        }

        $gruposFiltro = RrdGrupo::query()->enContexto()->activos()
            ->orderBy('nombre')->get(['id', 'nombre']);

        return view('livewire.material-didactico.recursos-admin', [
            'grupos'           => $grupos,
            'recursos'         => $recursos,
            'disponibilidades' => $disponibilidades,
            'recursoActual'    => $recursoActual,
            'gruposFiltro'     => $gruposFiltro,
            'dias'             => \App\Models\RrdRecursoDisponibilidad::DIAS,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Material Didáctico — Gestión de recursos']);
    }
}
