<div>
    <div class="se-page max-w-7xl mx-auto">

        <section class="se-hero mb-6">
            <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Material Didáctico</p>
                    <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Listado de reservas</h1>
                    <p class="text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                        @if($rol !== 'admin') · Mis reservas @else · Todas las reservas @endif
                    </p>
                </div>
                @if($rol === 'admin' || $rol === 'profesor')
                    <a href="{{ route('material-didactico.reservar') }}"
                       class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                        + REGISTRAR NUEVA RESERVA
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
                            <div class="gf-th gf-rrd-grupo">Grupo</div>
                            <div class="gf-th gf-rrd-sala">Sala / Curso</div>
                            <div class="gf-th gf-rrd-auxiliar">Auxiliar</div>
                            <div class="gf-th gf-rrd-usuario">Reservado por</div>
                            <div class="gf-th gf-rrd-estado">Estado</div>
                            @if($rol === 'admin')
                                <div class="gf-th gf-rrd-entregado">Entregado a</div>
                            @endif
                            <div class="gf-th gf-rrd-acciones text-center">Acciones</div>
                        </div>

                        @forelse($pedidosAgrupados as $grupo)
                            @php
                                $pedido = $grupo->pedido;
                                $reservasGrupo = $grupo->reservas;
                                $idProfesorCtx = (int) (schoolCtx()->idProfesor ?? 0);
                                $esDuenoPedido = $pedido?->id_profesor === $idProfesorCtx;
                                $puedeGestionarPedido = $rol === 'admin' || ($rol === 'profesor' && $esDuenoPedido);
                            @endphp
                            <div class="gf-row gf-row-hover" wire:key="pedido-{{ $grupo->id_pedido }}">
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
                                <div class="gf-td gf-rrd-grupo text-neutral-500 text-xs">
                                    <div class="gf-rrd-stack">
                                        @foreach($reservasGrupo as $reserva)
                                            <div class="gf-rrd-stack-item">{{ $reserva->recurso?->grupo?->nombre ?: '—' }}</div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="gf-td gf-rrd-sala text-xs">{{ $pedido?->sala_curso_grado ?: '—' }}</div>
                                <div class="gf-td gf-rrd-auxiliar text-xs">{{ $pedido?->auxiliar ?: '—' }}</div>
                                <div class="gf-td gf-rrd-usuario text-xs">{{ $pedido?->profesor?->nombre_completo ?: '—' }}</div>
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
                                @if($rol === 'admin')
                                    <div class="gf-td gf-rrd-entregado text-xs">
                                        <div class="gf-rrd-stack">
                                            @foreach($reservasGrupo as $reserva)
                                                <div class="gf-rrd-stack-item">{{ $reserva->entregado_a ?: '—' }}</div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                <div class="gf-td-actions gf-rrd-acciones">
                                    <div class="gf-rrd-stack gf-rrd-stack--acciones">
                                        @foreach($reservasGrupo as $reserva)
                                            <div class="gf-rrd-stack-item gf-rrd-stack-item--acciones">
                                                @if($rol === 'admin' && $reserva->esPendiente())
                                                    <button type="button"
                                                            wire:click="abrirEntrega({{ $reserva->id }})"
                                                            class="btn-secondary btn-sm">
                                                        Entregar
                                                    </button>
                                                @endif
                                                @if($rol === 'admin' && $reserva->esEntregado())
                                                    <button type="button"
                                                            wire:click="abrirDevolucion({{ $reserva->id }})"
                                                            class="btn-secondary btn-sm">
                                                        Devolver
                                                    </button>
                                                @endif
                                                @if($puedeGestionarPedido && $reserva->esPendiente())
                                                    <button type="button"
                                                            x-on:click="seSwalConfirmar(@js('¿Cancelar la reserva de este recurso?')).then(ok => ok && $wire.cancelarItemReserva({{ $reserva->id }}))"
                                                            class="btn-danger btn-sm">
                                                        Cancelar
                                                    </button>
                                                @endif
                                            </div>
                                        @endforeach
                                        @if($puedeGestionarPedido && $grupo->todas_pendientes_pedido)
                                            <div class="gf-rrd-stack-item gf-rrd-stack-item--pedido">
                                                <a href="{{ route('material-didactico.reservar.edit', $grupo->id_pedido) }}"
                                                   class="btn-secondary btn-sm">
                                                    Editar pedido
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
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

    {{-- Modales --}}
    @teleport('body')
    <div>
        {{-- Modal entrega --}}
        @if($mostrarModalEntrega)
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog" aria-modal="true" aria-labelledby="modal-entrega-titulo">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarEntrega"></div>
                <div class="relative z-10 my-auto flex w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                    <div class="flex items-center justify-between border-b border-accent-200 px-5 py-4">
                        <h2 id="modal-entrega-titulo" class="text-base font-semibold text-neutral-800">Registrar entrega</h2>
                        <button type="button" wire:click="cerrarEntrega" class="text-neutral-400 hover:text-neutral-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="px-5 py-4 space-y-4">
                        <div>
                            <label for="entregadoA" class="form-label">Quién retira el material <span class="text-red-500">*</span></label>
                            <input id="entregadoA"
                                   type="text"
                                   wire:model="entregadoA"
                                   maxlength="100"
                                   placeholder="Nombre del docente, alumno u otra persona…"
                                   class="form-input mt-1.5"
                                   autofocus>
                            @error('entregadoA') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50 px-5 py-4">
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
                <div class="relative z-10 my-auto flex w-full max-w-sm flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                    <div class="flex items-center justify-between border-b border-accent-200 px-5 py-4">
                        <h2 id="modal-devolucion-titulo" class="text-base font-semibold text-neutral-800">Registrar devolución</h2>
                        <button type="button" wire:click="cerrarDevolucion" class="text-neutral-400 hover:text-neutral-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-sm text-neutral-700">¿Confirma la devolución de este recurso?</p>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50 px-5 py-4">
                        <button type="button" wire:click="cerrarDevolucion" class="btn-secondary">Cancelar</button>
                        <button type="button" wire:click="confirmarDevolucion" class="btn-primary">Confirmar devolución</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
    @endteleport

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
