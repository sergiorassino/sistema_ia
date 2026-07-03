<?php

namespace App\Livewire\MaterialDidactico;

use App\Models\Curso;
use App\Models\Nivel;
use App\Models\RrdGrupo;
use App\Models\RrdPedido;
use App\Models\RrdRecurso;
use App\Models\RrdReserva;
use App\Support\MaterialDidactico\RrdReservaException;
use App\Support\MaterialDidactico\RrdReservaService;
use App\Support\PortalDocente\PortalDocenteContext;
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

    // Modal entrega (por pedido: un campo por recurso activo)
    public bool $mostrarModalEntrega = false;
    public ?int $pedidoEntregaId = null;
    /** @var array<int|string, string> */
    public array $entregasPedido = [];

    // Modal devolución (por pedido: un campo por recurso activo)
    public bool $mostrarModalDevolucion = false;
    public ?int $pedidoDevolucionId = null;
    /** @var array<int|string, string> */
    public array $devolucionesPedido = [];

    public function mount(): void
    {
        if ($this->esPortalDocente()) {
            abort_unless(tenantPortalDocenteRecursosDidacticosListado(), 404);
        } else {
            abort_unless(rrdRol() !== null, 403);
        }

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

    public function abrirEntrega(int $pedidoId): void
    {
        abort_if($this->esPortalDocente(), 403);
        abort_unless(rrdRol() === 'admin', 403);

        RrdPedido::queryEnContexto()->findOrFail($pedidoId);

        $reservas = $this->reservasActivasPedido($pedidoId);

        if ($reservas->isEmpty()) {
            return;
        }

        $this->pedidoEntregaId     = $pedidoId;
        $this->entregasPedido      = $reservas->mapWithKeys(function (RrdReserva $r) {
            $valor = $r->esPendiente()
                ? ''
                : trim((string) ($r->entregado_a ?? ''));

            return [$r->id => $valor];
        })->all();
        $this->mostrarModalEntrega = true;
    }

    public function cerrarEntrega(): void
    {
        $this->mostrarModalEntrega = false;
        $this->pedidoEntregaId     = null;
        $this->entregasPedido      = [];
    }

    public function confirmarEntrega(): void
    {
        abort_if($this->esPortalDocente(), 403);
        abort_unless(rrdRol() === 'admin', 403);

        if ($this->pedidoEntregaId === null) {
            $this->cerrarEntrega();

            return;
        }

        $this->validate([
            'entregasPedido'   => 'array',
            'entregasPedido.*' => 'nullable|string|max:100',
        ], [
            'entregasPedido.*.max' => 'Máximo 100 caracteres por recurso.',
        ]);

        $idOperador = (int) (schoolCtx()->idProfesor ?? 0);
        $procesadas = 0;

        foreach ($this->reservasActivasPedido($this->pedidoEntregaId) as $reserva) {
            if ($reserva->esDevuelto()) {
                continue;
            }

            if (! $reserva->esPendiente()) {
                continue;
            }

            $nombre = trim((string) ($this->entregasPedido[$reserva->id] ?? ''));

            if ($nombre === '') {
                continue;
            }

            try {
                RrdReservaService::registrarEntrega($reserva, $nombre, $idOperador);
                $procesadas++;
            } catch (RrdReservaException $e) {
                $this->dispatch('se-swal-error', mensaje: $e->getMessage());

                return;
            }
        }

        if ($procesadas === 0) {
            $this->dispatch('se-swal-error', mensaje: 'No hay cambios de entrega para guardar.');

            return;
        }

        $this->cerrarEntrega();
    }

    /**
     * Libera un ítem pendiente del pedido en curso (cancela la reserva del recurso).
     */
    public function liberarReservaEntrega(int $reservaId): void
    {
        abort_if($this->esPortalDocente(), 403);
        abort_unless(rrdRol() === 'admin', 403);

        if ($this->pedidoEntregaId === null) {
            return;
        }

        $reserva = RrdReserva::queryEnContexto()
            ->where('id_pedido', $this->pedidoEntregaId)
            ->where('id', $reservaId)
            ->firstOrFail();

        try {
            RrdReservaService::cancelarReserva($reserva);
            unset($this->entregasPedido[$reservaId]);

            if ($this->reservasActivasPedido($this->pedidoEntregaId)->isEmpty()) {
                $this->cerrarEntrega();
            }

            $this->dispatch('se-swal-exito', mensaje: 'Reserva liberada. El recurso vuelve a estar disponible.');
        } catch (RrdReservaException $e) {
            $this->dispatch('se-swal-error', mensaje: $e->getMessage());
        }
    }

    // ---------------------------------------------------------------
    // Devolución
    // ---------------------------------------------------------------

    public function abrirDevolucion(int $pedidoId): void
    {
        abort_if($this->esPortalDocente(), 403);
        abort_unless(rrdRol() === 'admin', 403);

        RrdPedido::queryEnContexto()->findOrFail($pedidoId);

        $reservas = $this->reservasActivasPedido($pedidoId);

        if ($reservas->isEmpty()) {
            return;
        }

        $this->pedidoDevolucionId = $pedidoId;
        $this->devolucionesPedido = $reservas->mapWithKeys(function (RrdReserva $r) {
            if ($r->esDevuelto()) {
                return [$r->id => $r->nombreQuienDevuelve()];
            }

            return [$r->id => ''];
        })->all();
        $this->mostrarModalDevolucion = true;
    }

    public function cerrarDevolucion(): void
    {
        $this->mostrarModalDevolucion = false;
        $this->pedidoDevolucionId     = null;
        $this->devolucionesPedido     = [];
    }

    public function confirmarDevolucion(): void
    {
        abort_if($this->esPortalDocente(), 403);
        abort_unless(rrdRol() === 'admin', 403);

        if ($this->pedidoDevolucionId === null) {
            $this->cerrarDevolucion();

            return;
        }

        $this->validate([
            'devolucionesPedido'   => 'array',
            'devolucionesPedido.*' => 'nullable|string|max:100',
        ], [
            'devolucionesPedido.*.max' => 'Máximo 100 caracteres por recurso.',
        ]);

        $idOperador = (int) (schoolCtx()->idProfesor ?? 0);
        $procesadas = 0;

        foreach ($this->reservasActivasPedido($this->pedidoDevolucionId) as $reserva) {
            if ($reserva->esPendiente()) {
                continue;
            }

            if (! $reserva->esEntregado()) {
                continue;
            }

            $nombre = trim((string) ($this->devolucionesPedido[$reserva->id] ?? ''));

            if ($nombre === '') {
                continue;
            }

            try {
                RrdReservaService::registrarDevolucion($reserva, $nombre, $idOperador);
                $procesadas++;
            } catch (RrdReservaException $e) {
                $this->dispatch('se-swal-error', mensaje: $e->getMessage());

                return;
            }
        }

        if ($procesadas === 0) {
            $this->dispatch('se-swal-error', mensaje: 'No hay cambios de devolución para guardar.');

            return;
        }

        $this->cerrarDevolucion();
    }

    // ---------------------------------------------------------------
    // Cancelación
    // ---------------------------------------------------------------

    public function cancelarPedidoReserva(int $pedidoId): void
    {
        $rol = $this->rolMaterialDidacticoListado();
        abort_unless($rol === 'admin' || $rol === 'profesor', 403);

        $pedido = RrdPedido::queryEnContexto()->findOrFail($pedidoId);

        if ($rol === 'profesor') {
            abort_unless(
                $pedido->id_profesor === (int) (schoolCtx()->idProfesor ?? 0),
                403
            );
        }

        try {
            RrdReservaService::cancelarPedido($pedido);
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
        $rol = $this->rolMaterialDidacticoListado();
        $puedeGestionarReservas = $this->puedeGestionarReservasPropias();
        $soloConsultaPortal = $this->esPortalDocente() && ! $puedeGestionarReservas;

        $query = RrdReserva::queryEnContexto()
            ->with(['recurso.grupo', 'pedido.profesor', 'pedido.reservas']);

        if (! $this->esPortalDocente() && $rol !== 'admin') {
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
                $algunaPendientePedido = $reservasGrupo->contains(
                    fn (RrdReserva $r) => $r->esPendiente()
                );
                $algunaEntregadaPedido = $reservasGrupo->contains(
                    fn (RrdReserva $r) => $r->esEntregado()
                );
                $algunaDevueltaPedido = $reservasGrupo->contains(
                    fn (RrdReserva $r) => $r->esDevuelto()
                );
                $algunaActivaPedido = $reservasGrupo->contains(
                    fn (RrdReserva $r) => ! $r->esCancelado()
                );

                return (object) [
                    'id_pedido'               => (int) $primera->id_pedido,
                    'pedido'                  => $pedido,
                    'fecha'                   => $primera->fecha,
                    'hora_inicio'             => $primera->hora_inicio,
                    'hora_fin'                => $primera->hora_fin,
                    'reservas'                => $reservasGrupo->values(),
                    'todas_pendientes_pedido' => $todasPendientesPedido,
                    'alguna_pendiente_pedido' => $algunaPendientePedido,
                    'alguna_entregada_pedido' => $algunaEntregadaPedido,
                    'alguna_devuelta_pedido'  => $algunaDevueltaPedido,
                    'alguna_activa_pedido'    => $algunaActivaPedido,
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

        $modalEntregaReservas = collect();
        if ($this->mostrarModalEntrega && $this->pedidoEntregaId) {
            $modalEntregaReservas = $this->reservasActivasPedido($this->pedidoEntregaId, true);
        }

        $modalDevolucionReservas = collect();
        if ($this->mostrarModalDevolucion && $this->pedidoDevolucionId) {
            $modalDevolucionReservas = $this->reservasActivasPedido($this->pedidoDevolucionId, true);
        }

        return view('livewire.material-didactico.reservas-dashboard', [
            'pedidosAgrupados' => $pedidosAgrupados,
            'rol'              => $rol,
            'cursos'           => $cursos,
            'nivelAbrev'       => $nivelAbrev,
            'grupos'           => $grupos,
            'recursosFiltro'   => $recursosFiltro,
            'soloConsultaPortal' => $soloConsultaPortal,
            'puedeGestionarReservas' => $puedeGestionarReservas,
            'rutaNuevaReserva' => $this->rutaNuevaReservaMaterialDidactico(),
            'modalEntregaReservas' => $modalEntregaReservas,
            'modalDevolucionReservas' => $modalDevolucionReservas,
        ])->layout($this->layoutMaterialDidactico(), ['pageTitle' => 'Material Didáctico — Listado de reservas']);
    }

    private function reservasActivasPedido(int $pedidoId, bool $conRecurso = false)
    {
        $query = RrdReserva::queryEnContexto()
            ->where('id_pedido', $pedidoId)
            ->where('estado', '!=', RrdReserva::ESTADO_CANCELADO)
            ->orderBy('id');

        if ($conRecurso) {
            $query->with('recurso');
        }

        return $query->get();
    }

    private function rolMaterialDidacticoListado(): ?string
    {
        if ($this->esPortalDocente()) {
            return $this->puedeGestionarReservasPropias() ? 'profesor' : 'lectura';
        }

        return rrdRol();
    }

    private function puedeGestionarReservasPropias(): bool
    {
        return $this->esPortalDocente()
            && tenantPortalDocenteRecursosDidacticosNuevaReserva();
    }

    private function esPortalDocente(): bool
    {
        return PortalDocenteContext::esActivo();
    }

    private function layoutMaterialDidactico(): string
    {
        return $this->esPortalDocente() ? 'layouts.docente' : layoutMenuStaff();
    }

    private function rutaNuevaReservaMaterialDidactico(): ?string
    {
        if ($this->esPortalDocente()) {
            return tenantPortalDocenteRecursosDidacticosNuevaReserva()
                ? route('portalDocente.materialDidactico.reservar')
                : null;
        }

        $rol = rrdRol();
        if ($rol === 'admin' || $rol === 'profesor') {
            return route('material-didactico.reservar');
        }

        return null;
    }
}
