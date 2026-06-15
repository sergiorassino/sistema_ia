<div>
    <div class="se-page max-w-3xl mx-auto">

        <section class="se-hero mb-6">
            <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Material Didáctico</p>
                    <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">{{ $titulo }}</h1>
                    <p class="text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
                <a href="{{ route('material-didactico.index') }}"
                   class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white/20 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/30">
                    ← Volver
                </a>
            </div>
        </section>

        <div class="se-card p-5 sm:p-6 space-y-6">

            {{-- Fecha y horario --}}
            <section class="space-y-4">
                <h2 class="text-base font-semibold text-neutral-800">Fecha y horario</h2>

                <div>
                    <label class="form-label">Fecha <span class="text-red-500">*</span></label>
                    <input type="date" wire:model="fecha" class="form-input mt-1.5 max-w-xs">
                    @error('fecha') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-4 flex-wrap">
                    <div class="flex-1 min-w-[8rem]">
                        <label class="form-label">Hora inicio <span class="text-red-500">*</span></label>
                        <input type="time" wire:model="horaInicio" class="form-input mt-1.5">
                        @error('horaInicio') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex-1 min-w-[8rem]">
                        <label class="form-label">Hora fin <span class="text-red-500">*</span></label>
                        <input type="time" wire:model="horaFin" class="form-input mt-1.5">
                        @error('horaFin') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if($rol === 'admin')
                    <div class="flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
                        <input type="checkbox" id="entregaDirect" wire:model.live="esEntregaDirect"
                               class="h-4 w-4 rounded border-neutral-300 text-primary-600">
                        <label for="entregaDirect" class="text-sm text-blue-800 font-medium">
                            Préstamo espontáneo (marcar como entregado al guardar)
                        </label>
                    </div>
                    @if($esEntregaDirect)
                        <div>
                            <label class="form-label">Quién retira el material <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="entregadoA" maxlength="100"
                                   placeholder="Nombre del docente, alumno u otra persona…"
                                   class="form-input mt-1.5">
                            @error('entregadoA') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                @endif
            </section>

            <hr class="border-accent-200">

            {{-- Recursos del pedido --}}
            <section class="space-y-4">
                <div>
                    <h2 class="text-base font-semibold text-neutral-800">Recursos del pedido</h2>
                    <p class="mt-1 text-xs text-neutral-500">Elija grupo y recurso, agréguelos al pedido y repita si necesita más de uno.</p>
                </div>

                @if($grupos->isEmpty())
                    <p class="text-sm text-neutral-500">No hay grupos de recursos configurados para este nivel.</p>
                @else
                    <div class="rounded-2xl border border-accent-200 bg-accent-50/60 p-4 space-y-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="rrd-form-grupo" class="form-label">Grupo</label>
                                <select id="rrd-form-grupo" wire:model.live="grupoId" class="form-input mt-1.5">
                                    <option value="">— Seleccione —</option>
                                    @foreach($grupos as $grupo)
                                        <option value="{{ $grupo->id }}">{{ $grupo->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('grupoId') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="rrd-form-recurso" class="form-label">Recurso</label>
                                <select id="rrd-form-recurso"
                                        wire:model.live="recursoIdAgregar"
                                        class="form-input mt-1.5"
                                        @disabled($grupoId === '' || $recursosDelGrupo->isEmpty())>
                                    <option value="">— Seleccione —</option>
                                    @foreach($recursosDelGrupo as $recurso)
                                        @if(! in_array($recurso->id, $recursosSeleccionados, true))
                                            <option value="{{ $recurso->id }}">{{ $recurso->nombre }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @error('recursoIdAgregar') <p class="form-error">{{ $message }}</p> @enderror
                                @if($grupoId !== '' && $recursosDelGrupo->isEmpty())
                                    <p class="mt-1 text-xs text-neutral-500">No hay recursos en este grupo.</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="button"
                                    wire:click="agregarRecursoAlPedido"
                                    class="btn-secondary btn-sm"
                                    @disabled(! $grupoId || ! $recursoIdAgregar)>
                                + Agregar al pedido
                            </button>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-accent-200 overflow-hidden">
                        <div class="border-b border-accent-200 bg-accent-50 px-4 py-2.5">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
                                Ítems del pedido
                                @if($recursosEnPedido->isNotEmpty())
                                    <span class="text-primary-700">({{ $recursosEnPedido->count() }})</span>
                                @endif
                            </p>
                        </div>
                        @if($recursosEnPedido->isEmpty())
                            <p class="px-4 py-6 text-center text-sm text-neutral-500">
                                Todavía no agregó recursos. Use el selector de arriba.
                            </p>
                        @else
                            <ul class="divide-y divide-accent-100">
                                @foreach($recursosEnPedido as $recurso)
                                    <li class="flex items-center gap-3 px-4 py-3" wire:key="pedido-recurso-{{ $recurso->id }}">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-medium text-neutral-800">{{ $recurso->nombre }}</p>
                                            <p class="text-xs text-neutral-500">{{ $recurso->grupo?->nombre }}</p>
                                        </div>
                                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                                            @if($recurso->antelacion_min_horas > 0)
                                                <span class="se-pill bg-yellow-100 text-yellow-700 text-[10px]">
                                                    {{ $recurso->antelacion_min_horas }}h antelación
                                                </span>
                                            @endif
                                            @if($recurso->restringidoPorHorario())
                                                <div class="relative"
                                                     x-data="{
                                                        open: false,
                                                        pos: { top: 0, right: 0 },
                                                        updatePos() {
                                                            const btn = this.$refs.horarioAnchor;
                                                            if (!btn) return;
                                                            const r = btn.getBoundingClientRect();
                                                            this.pos = {
                                                                top: Math.round(r.bottom + 6),
                                                                right: Math.max(8, Math.round(window.innerWidth - r.right)),
                                                            };
                                                        },
                                                        toggle() {
                                                            if (!this.open) this.updatePos();
                                                            this.open = !this.open;
                                                        },
                                                     }"
                                                     @resize.window="if (open) updatePos()"
                                                     @scroll.window.passive="if (open) updatePos()">
                                                    <button type="button"
                                                            x-ref="horarioAnchor"
                                                            @click.stop="toggle()"
                                                            class="se-pill inline-flex items-center gap-0.5 bg-neutral-100 text-neutral-600 text-[10px] transition hover:bg-neutral-200"
                                                            :aria-expanded="open"
                                                            title="Ver horarios disponibles">
                                                        Por horario
                                                        <svg class="h-3 w-3 shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                        </svg>
                                                    </button>
                                                    <template x-teleport="body">
                                                        <div x-show="open"
                                                             x-cloak
                                                             @click.outside="open = false"
                                                             :style="`top: ${pos.top}px; right: ${pos.right}px;`"
                                                             class="fixed z-[9999] w-56 max-h-52 overflow-y-auto rounded-xl border border-accent-200 bg-white py-2 shadow-lg ring-1 ring-black/5">
                                                            <p class="px-3 pb-1.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                                                                Horarios disponibles
                                                            </p>
                                                            @forelse($recurso->disponibilidades as $disp)
                                                                <p class="px-3 py-1 text-xs text-neutral-700 tabular-nums" wire:key="disp-pedido-{{ $recurso->id }}-{{ $disp->id }}">
                                                                    {{ $disp->etiquetaHorario() }}
                                                                </p>
                                                            @empty
                                                                <p class="px-3 py-1 text-xs text-neutral-500">
                                                                    Sin ventanas configuradas para este recurso.
                                                                </p>
                                                            @endforelse
                                                        </div>
                                                    </template>
                                                </div>
                                            @endif
                                        </div>
                                        <button type="button"
                                                wire:click="quitarRecursoDelPedido({{ $recurso->id }})"
                                                class="btn-danger btn-sm shrink-0"
                                                title="Quitar del pedido">
                                            Quitar
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    @error('recursosSeleccionados') <p class="form-error">{{ $message }}</p> @enderror
                @endif
            </section>

            <hr class="border-accent-200">

            {{-- Datos adicionales --}}
            <section class="space-y-4">
                <h2 class="text-base font-semibold text-neutral-800">Datos adicionales</h2>

                <div>
                    <label class="form-label">Sala / Curso / Grado</label>
                    @if($cursos->isNotEmpty())
                        <select wire:model="salaCursoGrado" class="form-input mt-1.5">
                            <option value="">— Seleccione un curso —</option>
                            @foreach($cursos as $curso)
                                @php
                                    $label = $curso->nombreParaListado();
                                    if ($nivelAbrev !== '') {
                                        $label .= ' (' . $nivelAbrev . ')';
                                    }
                                @endphp
                                <option value="{{ $label }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" wire:model="salaCursoGrado" maxlength="120"
                               placeholder="Ej: Aula 5, 3° A Secundario…"
                               class="form-input mt-1.5">
                    @endif
                    @error('salaCursoGrado') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Auxiliar</label>
                    <input type="text" wire:model="auxiliar" maxlength="100"
                           placeholder="Nombre del auxiliar (opcional)"
                           class="form-input mt-1.5">
                    @error('auxiliar') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Observaciones</label>
                    <textarea wire:model="observaciones" rows="3"
                              placeholder="Notas adicionales (opcional)"
                              class="form-input mt-1.5 leading-relaxed"></textarea>
                    @error('observaciones') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </section>

            <div class="flex justify-between gap-3 pt-2 border-t border-accent-200">
                <a href="{{ route('material-didactico.index') }}" class="btn-secondary">Cancelar</a>
                <button type="button" wire:click="guardar" class="btn-primary">
                    {{ $pedidoId ? 'Actualizar reserva' : 'Confirmar reserva' }}
                </button>
            </div>

        </div>
    </div>

    @script
    <script>
        $wire.on('se-swal-error', ({ mensaje }) => {
            if (typeof seSwalError === 'function') {
                seSwalError(mensaje ?? 'No se pudo completar la operación.');
            }
        });
        $wire.on('se-swal-aviso', ({ mensaje }) => {
            if (typeof seSwalAviso === 'function') {
                seSwalAviso(mensaje ?? 'Revise los datos ingresados.');
            }
        });
    </script>
    @endscript
</div>
