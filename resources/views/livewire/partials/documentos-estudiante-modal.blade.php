            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="vista-doc-titulo"
                 wire:key="vista-documento-estudiante"
                 x-data="{
                    esPdf: @js((bool) $vistaDocumentoEsPdf),
                    zoom: 1,
                    minZoom: 1,
                    maxZoom: 4,
                    x: 0,
                    y: 0,
                    dragging: false,
                    ox: 0,
                    oy: 0,
                    pellizco: 0,
                    acercar() {
                        this.zoom = Math.min(this.maxZoom, Math.round((this.zoom + 0.25) * 100) / 100);
                    },
                    alejar() {
                        this.zoom = Math.max(this.minZoom, Math.round((this.zoom - 0.25) * 100) / 100);
                        if (this.zoom <= 1) {
                            this.x = 0;
                            this.y = 0;
                        }
                    },
                    ajustar() {
                        this.zoom = 1;
                        this.x = 0;
                        this.y = 0;
                    },
                    rueda(e) {
                        if (this.esPdf) return;
                        if (e.deltaY < 0) this.acercar();
                        else this.alejar();
                    },
                    empezar(e) {
                        if (this.esPdf || this.zoom <= 1) return;
                        this.dragging = true;
                        this.ox = e.clientX;
                        this.oy = e.clientY;
                    },
                    mover(e) {
                        if (!this.dragging) return;
                        this.x += e.clientX - this.ox;
                        this.y += e.clientY - this.oy;
                        this.ox = e.clientX;
                        this.oy = e.clientY;
                    },
                    soltar() {
                        this.dragging = false;
                    },
                    distancia(touches) {
                        const a = touches[0];
                        const b = touches[1];
                        return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
                    },
                    toqueInicio(e) {
                        if (e.touches.length === 2) {
                            this.dragging = false;
                            this.pellizco = this.distancia(e.touches);
                        }
                    },
                    toqueMover(e) {
                        if (e.touches.length !== 2 || !this.pellizco) return;
                        const ahora = this.distancia(e.touches);
                        const factor = ahora / this.pellizco;
                        this.zoom = Math.min(this.maxZoom, Math.max(this.minZoom, Math.round(this.zoom * factor * 100) / 100));
                        this.pellizco = ahora;
                        if (this.zoom <= 1) {
                            this.x = 0;
                            this.y = 0;
                        }
                    },
                 }"
                 x-on:keydown.escape.window="$wire.cerrarVistaDocumento()"
                 x-on:documento-estudiante-rotado.window="ajustar()"
                 x-on:keydown.window="if (esPdf) return; if ($event.key === '+' || $event.key === '=') acercar(); if ($event.key === '-' || $event.key === '_') alejar();">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm"
                     wire:click="cerrarVistaDocumento"
                     aria-hidden="true"></div>
                <div class="relative z-10 my-auto flex w-full max-w-3xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5"
                     @click.stop
                     x-init="$nextTick(() => $el.querySelector('[data-modal-focus]')?.focus())">
                    <div class="h-1.5 shrink-0 bg-primary-600" aria-hidden="true"></div>
                    <div class="flex shrink-0 items-start justify-between gap-3 border-b border-accent-200 px-4 py-3 sm:px-5">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-primary-700">Documentación del estudiante</p>
                            <h3 id="vista-doc-titulo" class="truncate text-base font-semibold text-neutral-900">{{ $vistaDocumentoTitulo }}</h3>
                            <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1.5 sm:grid-cols-4">
                                <div class="min-w-0">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Apellido</dt>
                                    <dd class="truncate text-sm font-semibold text-neutral-900">{{ $apellido !== '' ? $apellido : '—' }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Nombre</dt>
                                    <dd class="truncate text-sm font-semibold text-neutral-900">{{ $nombre !== '' ? $nombre : '—' }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Curso</dt>
                                    <dd class="truncate text-sm font-semibold text-neutral-900">{{ $cursoEstudiante !== '' ? $cursoEstudiante : '—' }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">DNI</dt>
                                    <dd class="truncate text-sm font-semibold text-neutral-900">{{ ($dniDocumento ?? '') !== '' ? $dniDocumento : ($dni !== '' ? $dni : '—') }}</dd>
                                </div>
                            </dl>
                            @if ($vistaDocumentoFecha !== '')
                                <p class="mt-0.5 text-xs text-neutral-500">Recibido el {{ $vistaDocumentoFecha }}</p>
                            @endif
                        </div>
                        <button type="button"
                                data-modal-focus
                                wire:click="cerrarVistaDocumento"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-accent-200 text-neutral-600 hover:bg-accent-50 hover:text-neutral-900"
                                aria-label="Cerrar">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="relative h-[min(62dvh,36rem)] shrink-0 overflow-hidden bg-[#F4F8F9]"
                         @if (! $vistaDocumentoEsPdf)
                             x-on:wheel.prevent="rueda($event)"
                             x-on:pointerdown="empezar($event)"
                             x-on:pointermove="mover($event)"
                             x-on:pointerup="soltar()"
                             x-on:pointercancel="soltar()"
                             x-on:pointerleave="soltar()"
                             x-on:touchstart="toqueInicio($event)"
                             x-on:touchmove.prevent="toqueMover($event)"
                             x-on:touchend="pellizco = 0"
                             style="touch-action: none;"
                         @endif>
                        @if ($vistaDocumentoEsPdf)
                            <iframe src="{{ $vistaDocumentoSrc }}"
                                    title="{{ $vistaDocumentoTitulo }}"
                                    class="h-full w-full bg-white"></iframe>
                        @else
                            <div class="flex h-full w-full items-center justify-center p-3 sm:p-5">
                                <img src="{{ $vistaDocumentoSrc }}"
                                     wire:key="vista-doc-img-{{ $vistaDocumentoRevision }}"
                                     alt="{{ $vistaDocumentoTitulo }}"
                                     draggable="false"
                                     x-on:dblclick.stop="zoom > 1 ? ajustar() : zoom = 2"
                                     :class="zoom > 1 ? (dragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-zoom-in'"
                                     :style="`transform: translate(${x}px, ${y}px) scale(${zoom}); transform-origin: center center;`"
                                     class="max-h-full max-w-full select-none rounded-xl border border-accent-200 bg-white object-contain shadow-sm">
                            </div>
                            <div wire:loading.flex
                                 wire:target="rotarVistaDocumento"
                                 class="absolute inset-0 z-20 items-center justify-center bg-[#F4F8F9] text-sm font-semibold text-primary-700">
                                Rotando…
                            </div>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-accent-200 bg-white px-4 py-3 sm:px-5">
                        <div class="flex flex-wrap items-center gap-1" x-show="!esPdf" x-cloak>
                            <button type="button"
                                    x-on:click="alejar()"
                                    :disabled="zoom <= minZoom"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-accent-200 text-lg font-semibold text-primary-700 hover:bg-accent-50 disabled:opacity-40"
                                    aria-label="Alejar">
                                −
                            </button>
                            <span class="min-w-[3.25rem] text-center text-xs font-semibold tabular-nums text-neutral-700"
                                  x-text="Math.round(zoom * 100) + '%'"></span>
                            <button type="button"
                                    x-on:click="acercar()"
                                    :disabled="zoom >= maxZoom"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-accent-200 text-lg font-semibold text-primary-700 hover:bg-accent-50 disabled:opacity-40"
                                    aria-label="Acercar">
                                +
                            </button>
                            <button type="button"
                                    x-on:click="ajustar()"
                                    class="ml-1 inline-flex items-center justify-center rounded-xl border border-accent-200 px-3 py-2 text-xs font-semibold text-primary-700 hover:bg-accent-50">
                                Ajustar
                            </button>
                            @if ($vistaDocumentoPuedeRotar)
                                <button type="button"
                                        wire:click="rotarVistaDocumento"
                                        wire:loading.attr="disabled"
                                        wire:target="rotarVistaDocumento"
                                        class="ml-1 inline-flex items-center justify-center gap-1.5 rounded-xl border border-accent-200 px-3 py-2 text-xs font-semibold text-primary-700 hover:bg-accent-50 disabled:opacity-40"
                                        aria-label="Rotar 90 grados a la derecha y guardar">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    <span wire:loading.remove wire:target="rotarVistaDocumento">Rotar</span>
                                    <span wire:loading wire:target="rotarVistaDocumento">Rotando…</span>
                                </button>
                            @endif
                        </div>
                        @if ($vistaDocumentoRotacionAviso !== '')
                            <p class="text-xs font-medium text-primary-700">{{ $vistaDocumentoRotacionAviso }}</p>
                        @endif
                        <button type="button"
                                wire:click="cerrarVistaDocumento"
                                class="ml-auto inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm hover:bg-accent-50">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
