<?php

namespace App\Livewire\MaterialDidactico;

use App\Models\Curso;
use App\Models\Nivel;
use App\Models\RrdGrupo;
use App\Models\RrdRecurso;
use App\Models\RrdReserva;
use App\Support\MaterialDidactico\RrdReservaException;
use App\Support\MaterialDidactico\RrdReservaService;
use Livewire\Component;

class ReservasDashboard extends Component
{
    public string $fecha            = '';
    /** @var 'dia'|'todas' */
    public string $modoFecha        = 'dia';
    public string $filtroCurso      = '';
    public string $filtroGrupoId    = '';
    public string $filtroRecursoId  = '';
    public string $search           = '';
    public string $filtroEstado     = '';

    // Modal entrega
    public bool $mostrarModalEntrega = false;
    public ?int $reservaEntregaId = null;
    public string $entregadoA = '';

    // Modal devolución (confirm simple)
    public bool $mostrarModalDevolucion = false;
    public ?int $reservaDevolucionId = null;

    public function mount(): void
    {
        abort_unless(rrdRol() !== null, 403);

        $this->fecha = now()->format('Y-m-d');
    }

    public function updatedFiltroGrupoId(): void
    {
        $this->filtroRecursoId = '';
    }

    public function updatedModoFecha(): void
    {
        if ($this->modoFecha === 'dia' && $this->fecha === '') {
            $this->fecha = now()->format('Y-m-d');
        }
    }

    // ---------------------------------------------------------------
    // Entrega
    // ---------------------------------------------------------------

    public function abrirEntrega(int $id): void
    {
        abort_unless(rrdRol() === 'admin', 403);

        $this->reservaEntregaId = $id;
        $this->entregadoA       = '';
        $this->mostrarModalEntrega = true;
    }

    public function cerrarEntrega(): void
    {
        $this->mostrarModalEntrega = false;
        $this->reservaEntregaId   = null;
        $this->entregadoA         = '';
    }

    public function confirmarEntrega(): void
    {
        abort_unless(rrdRol() === 'admin', 403);

        $this->validate(['entregadoA' => 'required|string|max:100'], [
            'entregadoA.required' => 'Indique quién retira el material.',
            'entregadoA.max'      => 'Máximo 100 caracteres.',
        ]);

        $reserva = RrdReserva::queryEnContexto()->find($this->reservaEntregaId);
        if (! $reserva) {
            $this->cerrarEntrega();
            return;
        }

        try {
            RrdReservaService::registrarEntrega(
                $reserva,
                $this->entregadoA,
                (int) (schoolCtx()->idProfesor ?? 0)
            );
            $this->cerrarEntrega();
        } catch (RrdReservaException $e) {
            $this->dispatch('se-swal-error', mensaje: $e->getMessage());
        }
    }

    // ---------------------------------------------------------------
    // Devolución
    // ---------------------------------------------------------------

    public function abrirDevolucion(int $id): void
    {
        abort_unless(rrdRol() === 'admin', 403);

        $this->reservaDevolucionId    = $id;
        $this->mostrarModalDevolucion = true;
    }

    public function cerrarDevolucion(): void
    {
        $this->mostrarModalDevolucion  = false;
        $this->reservaDevolucionId     = null;
    }

    public function confirmarDevolucion(): void
    {
        abort_unless(rrdRol() === 'admin', 403);

        $reserva = RrdReserva::queryEnContexto()->find($this->reservaDevolucionId);
        if (! $reserva) {
            $this->cerrarDevolucion();
            return;
        }

        try {
            RrdReservaService::registrarDevolucion(
                $reserva,
                (int) (schoolCtx()->idProfesor ?? 0)
            );
            $this->cerrarDevolucion();
        } catch (RrdReservaException $e) {
            $this->dispatch('se-swal-error', mensaje: $e->getMessage());
        }
    }

    // ---------------------------------------------------------------
    // Cancelación
    // ---------------------------------------------------------------

    public function cancelarItemReserva(int $reservaId): void
    {
        $rol = rrdRol();
        abort_unless($rol === 'admin' || $rol === 'profesor', 403);

        $reserva = RrdReserva::queryEnContexto()->with('pedido')->findOrFail($reservaId);

        if ($rol === 'profesor') {
            abort_unless(
                $reserva->pedido?->id_profesor === (int) (schoolCtx()->idProfesor ?? 0),
                403
            );
        }

        try {
            RrdReservaService::cancelarReserva($reserva);
            $this->dispatch('se-swal-exito', mensaje: 'Reserva cancelada.');
        } catch (RrdReservaException $e) {
            $this->dispatch('se-swal-error', mensaje: $e->getMessage());
        }
    }

    // ---------------------------------------------------------------
    // Render
    // ---------------------------------------------------------------

    public function render()
    {
        $ctx = schoolCtx();
        $rol = rrdRol();

        $query = RrdReserva::queryEnContexto()
            ->with(['recurso.grupo', 'pedido.profesor', 'pedido.reservas']);

        if ($rol !== 'admin') {
            $idProfesor = (int) ($ctx->idProfesor ?? 0);
            $query->whereHas('pedido', fn ($q) => $q->where('id_profesor', $idProfesor));
        }

        if ($this->modoFecha === 'todas') {
            $query->orderByDesc('fecha')->orderBy('hora_inicio')->orderBy('id');
        } else {
            $fecha = $this->fecha ?: now()->format('Y-m-d');
            $query->where('fecha', $fecha)
                ->orderBy('hora_inicio')
                ->orderBy('id');
        }

        if ($this->filtroRecursoId !== '') {
            $query->where('id_recurso', (int) $this->filtroRecursoId);
        } elseif ($this->filtroGrupoId !== '') {
            $idsRecursos = RrdRecurso::paraGrupo((int) $this->filtroGrupoId)->pluck('id');
            if ($idsRecursos->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id_recurso', $idsRecursos);
            }
        }

        if ($this->filtroEstado !== '') {
            $query->where('estado', $this->filtroEstado);
        }

        if ($this->filtroCurso !== '') {
            $query->whereHas('pedido', fn ($q) => $q->where('sala_curso_grado', $this->filtroCurso));
        }

        if (trim($this->search) !== '') {
            $termino = '%'.trim($this->search).'%';
            $query->whereHas('pedido', function ($q) use ($termino) {
                $q->where('auxiliar', 'like', $termino)
                    ->orWhere('observaciones', 'like', $termino)
                    ->orWhere('sala_curso_grado', 'like', $termino);
            });
        }

        $reservas = $query->get();

        $pedidosAgrupados = $reservas
            ->groupBy('id_pedido')
            ->map(function ($reservasGrupo) {
                $primera = $reservasGrupo->first();

                $pedido = $primera->pedido;
                $todasPendientesPedido = $pedido?->reservas
                    ->every(fn (RrdReserva $r) => $r->esPendiente()) ?? false;

                return (object) [
                    'id_pedido'             => (int) $primera->id_pedido,
                    'pedido'                => $pedido,
                    'fecha'                 => $primera->fecha,
                    'hora_inicio'           => $primera->hora_inicio,
                    'hora_fin'              => $primera->hora_fin,
                    'reservas'              => $reservasGrupo->values(),
                    'todas_pendientes_pedido' => $todasPendientesPedido,
                ];
            })
            ->values();

        $cursos = Curso::query()
            ->where('idNivel', (int) ($ctx->idNivel ?? 0))
            ->where('idTerlec', (int) ($ctx->idTerlec ?? 0))
            ->with(['curplan', 'turnoClase', 'nivel'])
            ->orderBy('orden')
            ->get();

        $nivelAbrev = $cursos->first()?->nivel?->abrev
            ?? Nivel::find((int) ($ctx->idNivel ?? 0))?->abrev
            ?? '';

        $grupos = RrdGrupo::paraSelector();

        $recursosFiltro = collect();
        if ($this->filtroGrupoId !== '' && (int) $this->filtroGrupoId > 0) {
            $recursosFiltro = RrdRecurso::paraGrupo((int) $this->filtroGrupoId);
        }

        return view('livewire.material-didactico.reservas-dashboard', [
            'pedidosAgrupados' => $pedidosAgrupados,
            'rol'              => $rol,
            'cursos'         => $cursos,
            'nivelAbrev'     => $nivelAbrev,
            'grupos'         => $grupos,
            'recursosFiltro' => $recursosFiltro,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Material Didáctico — Listado de reservas']);
    }
}
