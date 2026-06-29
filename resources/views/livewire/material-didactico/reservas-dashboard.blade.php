<div>
    <div class="se-page max-w-7xl mx-auto">

        <section class="se-hero mb-6">
            <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Material Didáctico</p>
                    <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Listado de reservas</h1>
                    <p class="text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                        @if($soloConsultaPortal || $puedeGestionarReservas)
                            · Consulta de reservas
                        @elseif($rol !== 'admin')
                            · Mis reservas
                        @else
                            · Todas las reservas
                        @endif
                    </p>
                </div>
                @if($rutaNuevaReserva)
                    <a href="{{ $rutaNuevaReserva }}"
                       class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                        + {{ $soloConsultaPortal ? 'NUEVA RESERVA' : 'REGISTRAR NUEVA RESERVA' }}
                    </a>
                @endif
            </div>
        </section>

        <div class="se-toolbar mb-4 gap-3 flex-wrap">
            <div class="flex items-center gap-2 flex-wrap">
                <label for="rrd-dia-fecha" class="text-xs font-semibold uppercase tracking-wide text-neutral-500 whitespace-nowrap">Fecha</label>
                <select id="rrd-dia-modo-fecha" wire:model.live="modoFecha" class="form-input w-36">
                    <option value="dia">Día específico</option>
                    <option value="todas">Todas</option>
                </select>
                <input id="rrd-dia-fecha"
                       type="date"
                       wire:model.live="fecha"
                       wire:key="rrd-dia-fecha-{{ $modoFecha }}"
                       class="form-input w-40 @if($modoFecha === 'todas') opacity-50 @endif"
                       @disabled($modoFecha === 'todas')>
            </div>
            <div class="flex items-center gap-2">
                <label for="rrd-dia-curso" class="text-xs font-semibold uppercase tracking-wide text-neutral-500 whitespace-nowrap">Curso</label>
                <select id="rrd-dia-curso" wire:model.live="filtroCurso" class="form-input w-52 max-w-full">
                    <option value="">— Todos —</option>
                    @foreach($cursos as $curso)
                        @php
                            $labelCurso = $curso->nombreParaListado();
                            if ($nivelAbrev !== '') {
                                $labelCurso .= ' (' . $nivelAbrev . ')';
                            }
                        @endphp
                        <option value="{{ $labelCurso }}">{{ $labelCurso }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label for="rrd-dia-grupo" class="text-xs font-semibold uppercase tracking-wide text-neutral-500 whitespace-nowrap">Grupo</label>
                <select id="rrd-dia-grupo" wire:model.live="filtroGrupoId" class="form-input w-44 max-w-full">
                    <option value="">— Todos —</option>
                    @foreach($grupos as $grupo)
                        <option value="{{ $grupo->id }}">{{ $grupo->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label for="rrd-dia-recurso" class="text-xs font-semibold uppercase tracking-wide text-neutral-500 whitespace-nowrap">Recurso</label>
                <select id="rrd-dia-recurso"
                        wire:model.live="filtroRecursoId"
                        class="form-input w-44 max-w-full"
                        @disabled($filtroGrupoId === '' || $recursosFiltro->isEmpty())>
                    <option value="">— Todos —</option>
                    @foreach($recursosFiltro as $recurso)
                        <option value="{{ $recurso->id }}">{{ $recurso->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label for="rrd-dia-estado" class="text-xs font-semibold uppercase tracking-wide text-neutral-500 whitespace-nowrap">Estado</label>
                <select id="rrd-dia-estado" wire:model.live="filtroEstado" class="form-input w-40">
                    <option value="">— Todos —</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="entregado">Entregado</option>
                    <option value="devuelto">Devuelto</option>
                    <option value="cancelado">Cancelado</option>
                </select>
            </div>
            <div class="relative flex-1 min-w-[14rem]">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input id="rrd-dia-buscar"
                       wire:model.live.debounce.300ms="search"
                       type="search"
                       placeholder="Auxiliar, observaciones, sala…"
                       class="form-input w-full pl-9"
                       autocomplete="off">
            </div>
        </div>

        {{-- Listado --}}
        <div class="se-card overflow-hidden p-2 sm:p-3">
            <div class="w-full min-w-0">
                    <div @class([
                        'gf gf-vcenter gf-rrd-listado',
                        'gf-rrd-listado--admin' => $rol === 'admin',
                        'gf-rrd-listado--todas' => $modoFecha === 'todas',
                    ])>
                        <div class="gf-head">
                            @if($modoFecha === 'todas')
                                <div class="gf-th gf-rrd-fecha">Fecha</div>
                            @endif
                            <div class="gf-th gf-rrd-horario">Horario</div>
                            <div class="gf-th gf-rrd-recurso">Recurso</div>
                            <div class="gf-th gf-rrd-sala">Sala / Curso</div>
                            <div class="gf-th gf-rrd-usuario">Reservado por</div>
                            <div class="gf-th gf-rrd-observaciones">Observaciones</div>
                            <div class="gf-th gf-rrd-estado">Estado</div>
                            @if($rol === 'admin' && ! $soloConsultaPortal)
                                <div class="gf-th gf-rrd-entregado">Entregado a</div>
                                <div class="gf-th gf-rrd-devuelto">Devuelto por</div>
                            @endif
                            @if(! $soloConsultaPortal)
                                <div class="gf-th gf-rrd-acciones text-center">Acciones</div>
                            @endif
                        </div>

                        @forelse($pedidosAgrupados as $grupo)
                            @php
                                $pedido = $grupo->pedido;
                                $reservasGrupo = $grupo->reservas;
                                $idProfesorCtx = (int) (schoolCtx()->idProfesor ?? 0);
                                $esDuenoPedido = $pedido?->id_profesor === $idProfesorCtx;
                                $puedeGestionarPedido = $rol === 'admin' || ($rol === 'profesor' && $esDuenoPedido);
                                $puedeEditarCancelarPedido = $puedeGestionarPedido && $grupo->todas_pendientes_pedido;
                                $filasAcciones = max(1, $reservasGrupo->count());
                            @endphp
                            <div @class([
                                    'gf-row gf-row-hover',
                                    'gf-row-multirecurso' => $filasAcciones > 1,
                                ])
                                style="--rrd-filas: {{ $filasAcciones }}"
                                wire:key="pedido-{{ $grupo->id_pedido }}">
                                @if($modoFecha === 'todas')
                                    <div class="gf-td gf-rrd-fecha tabular-nums">
                                        {{ $grupo->fecha?->format('d/m/Y') }}
                                    </div>
                                @endif
                                <div class="gf-td gf-rrd-horario tabular-nums font-medium">
                                    {{ substr($grupo->hora_inicio, 0, 5) }}–{{ substr($grupo->hora_fin, 0, 5) }}
                                </div>
                                <div class="gf-td gf-rrd-recurso">
                                    <div class="gf-rrd-stack">
                                        @foreach($reservasGrupo as $reserva)
                                            <div class="gf-rrd-stack-item font-medium">{{ $reserva->recurso?->nombre }}</div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="gf-td gf-rrd-sala text-xs">{{ $pedido?->sala_curso_grado ?: '—' }}</div>
                                <div class="gf-td gf-rrd-usuario">
                                    @if($pedido?->profesor?->nombre_completo)
                                        @php
                                            $urlMensajeReservador = \App\Support\Comunicaciones\NuevoComunicadoDocenteDestino::urlParaProfesor(
                                                (int) ($pedido->id_profesor ?? 0)
                                            );
                                        @endphp
                                        <div class="gf-rrd-meta-line gf-rrd-reservado-por">
                                            <span>{{ $pedido->profesor->nombre_completo }}</span>
                                            @if($urlMensajeReservador)
                                                <a href="{{ $urlMensajeReservador }}"
                                                   class="gf-rrd-link-mensaje">
                                                    Enviar mensaje
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        —
                                    @endif
                                </div>
                                <div class="gf-td gf-rrd-observaciones text-xs">{{ $pedido?->observaciones ?: '—' }}</div>
                                <div class="gf-td gf-rrd-estado">
                                    <div class="gf-rrd-stack">
                                        @foreach($reservasGrupo as $reserva)
                                            @php
                                                $estadoClases = match($reserva->estado) {
                                                    'pendiente'  => 'se-pill bg-yellow-100 text-yellow-700',
                                                    'entregado'  => 'se-pill bg-blue-100 text-blue-700',
                                                    'devuelto'   => 'se-pill bg-green-100 text-green-700',
                                                    'cancelado'  => 'se-pill bg-neutral-100 text-neutral-500',
                                                    default      => 'se-pill bg-neutral-100 text-neutral-500',
                                                };
                                                $estadoLabel = match($reserva->estado) {
                                                    'pendiente' => 'Pendiente',
                                                    'entregado' => 'Entregado',
                                                    'devuelto'  => 'Devuelto',
                                                    'cancelado' => 'Cancelado',
                                                    default     => $reserva->estado,
                                                };
                                            @endphp
                                            <div class="gf-rrd-stack-item">
                                                <span class="{{ $estadoClases }}">{{ $estadoLabel }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @if($rol === 'admin' && ! $soloConsultaPortal)
                                    <div class="gf-td gf-rrd-entregado text-xs">
                                        <div class="gf-rrd-stack">
                                            @foreach($reservasGrupo as $reserva)
                                                <div class="gf-rrd-stack-item">
                                                    @if($reserva->entregado_a || $reserva->entregado_at)
                                                        <div class="gf-rrd-meta-line">
                                                            @if($reserva->entregado_a)
                                                                <span class="font-medium text-neutral-800">{{ $reserva->entregado_a }}</span>
                                                            @endif
                                                            @if($reserva->entregado_at)
                                                                <span class="text-neutral-500 tabular-nums">{{ $reserva->entregado_at->format('d/m/Y H:i') }}</span>
                                                            @endif
                                                        </div>
                                                    @else
                                                        —
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="gf-td gf-rrd-devuelto text-xs">
                                        <div class="gf-rrd-stack">
                                            @foreach($reservasGrupo as $reserva)
                                                @php
                                                    $nombreDevuelve = $reserva->nombreQuienDevuelve();
                                                @endphp
                                                <div class="gf-rrd-stack-item">
                                                    @if($nombreDevuelve !== '' || $reserva->devuelto_at)
                                                        <div class="gf-rrd-meta-line">
                                                            @if($nombreDevuelve !== '')
                                                                <span class="font-medium text-neutral-800">{{ $nombreDevuelve }}</span>
                                                            @endif
                                                            @if($reserva->devuelto_at)
                                                                <span class="text-neutral-500 tabular-nums">{{ $reserva->devuelto_at->format('d/m/Y H:i') }}</span>
                                                            @endif
                                                        </div>
                                                    @else
                                                        —
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                @if(! $soloConsultaPortal)
                                <div class="gf-td gf-td-actions gf-rrd-acciones">
                                    <div class="gf-rrd-acciones-inline">
                                        @if($rol === 'admin' && $grupo->alguna_activa_pedido)
                                            <button type="button"
                                                    wire:click="abrirEntrega({{ $grupo->id_pedido }})"
                                                    class="btn-secondary btn-sm">
                                                Entregar
                                            </button>
                                        @endif
                                        @if($rol === 'admin' && ($grupo->alguna_entregada_pedido || $grupo->alguna_devuelta_pedido))
                                            <button type="button"
                                                    wire:click="abrirDevolucion({{ $grupo->id_pedido }})"
                                                    class="btn-secondary btn-sm">
                                                Devolver
                                            </button>
                                        @endif
                                        @if($puedeEditarCancelarPedido)
                                            <a href="{{ $puedeGestionarReservas
                                                ? route('portalDocente.materialDidactico.reservar.edit', $grupo->id_pedido)
                                                : route('material-didactico.reservar.edit', $grupo->id_pedido) }}"
                                               class="btn-secondary btn-sm">
                                                Editar
                                            </a>
                                            <button type="button"
                                                    x-on:click="seSwalConfirmar(@js('¿Cancelar toda la reserva?')).then(ok => ok && $wire.cancelarPedidoReserva({{ $grupo->id_pedido }}))"
                                                    class="btn-danger btn-sm">
                                                Cancelar
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                @endif
                            </div>
                        @empty
                            <div class="gf-empty">
                                No hay reservas para los filtros seleccionados.
                            </div>
                        @endforelse
                    </div>
            </div>
        </div>

    </div>

    {{-- Modales (solo gestión en secretaría) --}}
    @if(! $soloConsultaPortal)
    @teleport('body')
    <div>
        {{-- Modal entrega --}}
        @if($mostrarModalEntrega)
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog" aria-modal="true" aria-labelledby="modal-entrega-titulo">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarEntrega"></div>
                <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[min(90dvh,32rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                    <div class="flex shrink-0 items-center justify-between border-b border-accent-200 px-5 py-4">
                        <h2 id="modal-entrega-titulo" class="text-base font-semibold text-neutral-800">Registrar entrega</h2>
                        <button type="button" wire:click="cerrarEntrega" class="text-neutral-400 hover:text-neutral-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-4">
                        <p class="text-xs text-neutral-500 leading-relaxed">
                            Complete la entrega o corrija el nombre. Use «Liberar reserva» si el recurso no se retirará (quedará disponible para otros pedidos). Si borra un campo entregado, el recurso vuelve a pendiente. Los devueltos no se editan aquí.
                        </p>
                        @foreach($modalEntregaReservas as $reserva)
                            @php
                                $estadoEntregaLabel = match($reserva->estado) {
                                    'pendiente' => 'Pendiente',
                                    'entregado' => 'Entregado',
                                    'devuelto'  => 'Devuelto',
                                    default     => $reserva->estado,
                                };
                                $estadoEntregaClases = match($reserva->estado) {
                                    'pendiente' => 'se-pill bg-yellow-100 text-yellow-700',
                                    'entregado' => 'se-pill bg-blue-100 text-blue-700',
                                    'devuelto'  => 'se-pill bg-green-100 text-green-700',
                                    default     => 'se-pill bg-neutral-100 text-neutral-500',
                                };
                                $campoEntregaDeshabilitado = $reserva->esDevuelto();
                            @endphp
                            <div @class([
                                'rounded-xl border px-4 py-3',
                                'border-accent-200 bg-accent-50/40' => ! $campoEntregaDeshabilitado,
                                'border-accent-200/70 bg-neutral-50/80' => $campoEntregaDeshabilitado,
                            ])>
                                <div class="mb-1.5 flex flex-wrap items-center gap-2">
                                    <label for="entrega-{{ $reserva->id }}" class="form-label mb-0">
                                        {{ $reserva->recurso?->nombre ?: 'Recurso' }}
                                    </label>
                                    <span class="{{ $estadoEntregaClases }}">{{ $estadoEntregaLabel }}</span>
                                </div>
                                <div @class([
                                    'flex flex-col gap-2 sm:flex-row sm:items-start',
                                    'sm:gap-3' => $reserva->esPendiente(),
                                ])>
                                    <input id="entrega-{{ $reserva->id }}"
                                           type="text"
                                           wire:model="entregasPedido.{{ $reserva->id }}"
                                           maxlength="100"
                                           placeholder="{{ $campoEntregaDeshabilitado ? 'Ya devuelto' : 'Quién retira este recurso…' }}"
                                           class="form-input min-w-0 flex-1"
                                           @disabled($campoEntregaDeshabilitado)
                                           @if($loop->first && ! $campoEntregaDeshabilitado) autofocus @endif>
                                    @if($reserva->esPendiente())
                                        <button type="button"
                                                x-on:click="seSwalConfirmar(@js('¿Liberar esta reserva? El recurso quedará disponible para otros pedidos en ese horario.')).then(ok => ok && $wire.liberarReservaEntrega({{ $reserva->id }}))"
                                                class="btn-secondary btn-sm shrink-0 whitespace-nowrap">
                                            Liberar reserva
                                        </button>
                                    @endif
                                </div>
                                @error('entregasPedido.'.$reserva->id)
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                        @error('entregasPedido')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex shrink-0 justify-end gap-3 border-t border-accent-200 bg-accent-50 px-5 py-4">
                        <button type="button" wire:click="cerrarEntrega" class="btn-secondary">Cancelar</button>
                        <button type="button" wire:click="confirmarEntrega" class="btn-primary">Confirmar entrega</button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal devolución --}}
        @if($mostrarModalDevolucion)
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog" aria-modal="true" aria-labelledby="modal-devolucion-titulo">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarDevolucion"></div>
                <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[min(90dvh,32rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                    <div class="flex shrink-0 items-center justify-between border-b border-accent-200 px-5 py-4">
                        <h2 id="modal-devolucion-titulo" class="text-base font-semibold text-neutral-800">Registrar devolución</h2>
                        <button type="button" wire:click="cerrarDevolucion" class="text-neutral-400 hover:text-neutral-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-4">
                        <p class="text-xs text-neutral-500 leading-relaxed">
                            Registre devoluciones o corrija el nombre. Si borra un campo ya registrado, el recurso vuelve a entregado.
                        </p>
                        @foreach($modalDevolucionReservas as $reserva)
                            @php
                                $estadoDevolucionLabel = match($reserva->estado) {
                                    'pendiente' => 'Pendiente',
                                    'entregado' => 'Entregado',
                                    'devuelto'  => 'Devuelto',
                                    default     => $reserva->estado,
                                };
                                $estadoDevolucionClases = match($reserva->estado) {
                                    'pendiente' => 'se-pill bg-yellow-100 text-yellow-700',
                                    'entregado' => 'se-pill bg-blue-100 text-blue-700',
                                    'devuelto'  => 'se-pill bg-green-100 text-green-700',
                                    default     => 'se-pill bg-neutral-100 text-neutral-500',
                                };
                                $campoDevolucionDeshabilitado = $reserva->esPendiente();
                            @endphp
                            <div @class([
                                'rounded-xl border px-4 py-3',
                                'border-accent-200 bg-accent-50/40' => ! $campoDevolucionDeshabilitado,
                                'border-accent-200/70 bg-neutral-50/80' => $campoDevolucionDeshabilitado,
                            ])>
                                <div class="mb-1.5 flex flex-wrap items-center gap-2">
                                    <label for="devolucion-{{ $reserva->id }}" class="form-label mb-0">
                                        {{ $reserva->recurso?->nombre ?: 'Recurso' }}
                                    </label>
                                    <span class="{{ $estadoDevolucionClases }}">{{ $estadoDevolucionLabel }}</span>
                                </div>
                                <input id="devolucion-{{ $reserva->id }}"
                                       type="text"
                                       wire:model="devolucionesPedido.{{ $reserva->id }}"
                                       wire:keydown.enter.prevent="confirmarDevolucion"
                                       maxlength="100"
                                       placeholder="{{ $campoDevolucionDeshabilitado ? 'Aún no entregado' : 'Quién devuelve este recurso…' }}"
                                       class="form-input"
                                       @disabled($campoDevolucionDeshabilitado)
                                       @if($loop->first && ! $campoDevolucionDeshabilitado) autofocus @endif>
                                @error('devolucionesPedido.'.$reserva->id)
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                        @error('devolucionesPedido')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex shrink-0 justify-end gap-3 border-t border-accent-200 bg-accent-50 px-5 py-4">
                        <button type="button" wire:click="cerrarDevolucion" class="btn-secondary">Cancelar</button>
                        <button type="button" wire:click="confirmarDevolucion" class="btn-primary">Confirmar devolución</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
    @endteleport
    @endif

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje }) => {
            if (typeof seSwalExito === 'function') {
                seSwalExito(mensaje ?? 'Operación completada.');
            }
        });
        $wire.on('se-swal-error', ({ mensaje }) => {
            if (typeof seSwalError === 'function') {
                seSwalError(mensaje ?? 'No se pudo completar la operación.');
            }
        });
    </script>
    @endscript
</div>
