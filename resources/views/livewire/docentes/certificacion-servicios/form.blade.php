<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Docentes</p>
                    <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Certificación de servicios</h2>
                    <p class="truncate text-sm text-white/80">{{ $profesorEtiqueta }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button"
                            wire:click="abrirModalImprimir"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-white px-3.5 py-2 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                        Imprimir
                    </button>
                    <a href="{{ route('docentes.certificacion-servicios') }}"
                       class="inline-flex items-center justify-center gap-1 rounded-xl border border-white/25 bg-white/10 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/20">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Volver
                    </a>
                </div>
            </div>
        </section>

        @php
            $sub = $resumen['subtotal'];
            $ant = $resumen['antiguedad'];
        @endphp
        <div class="flex flex-wrap items-center gap-2 text-[11px]">
            <span class="se-pill">Subtotal (sin solapes): {{ $sub['anios'] }}a {{ $sub['meses'] }}m {{ $sub['dias'] }}d</span>
            @if ($ant['ok'])
                <span class="se-pill">Antigüedad (− lic. no parciales): {{ $ant['anios'] }}a {{ $ant['meses'] }}m {{ $ant['dias'] }}d</span>
            @else
                <span class="rounded-full bg-red-100 px-2.5 py-0.5 font-semibold text-red-700">Licencias superan el subtotal de servicios</span>
            @endif
        </div>

        {{-- Servicios --}}
        <div class="space-y-2">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-sm font-bold uppercase tracking-wide text-neutral-800">Servicios prestados</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <input type="search" wire:model.live.debounce.300ms="buscarServicios" placeholder="Búsqueda rápida…"
                           class="gf-input !w-auto min-w-[12rem] border border-gray-400 bg-white sm:max-w-xs" aria-label="Buscar servicios">
                    <button type="button"
                            wire:click="insertarServicio"
                            wire:loading.attr="disabled"
                            wire:target="insertarServicio,eliminarServicio,guardarServicioFila"
                            class="btn-primary btn-sm disabled:opacity-50">Insertar</button>
                </div>
            </div>

            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <div class="gf min-w-[62rem]" wire:key="serv-grid-{{ $serviciosGridKey }}">
                        <div class="gf-head">
                            <div class="gf-th w-10 shrink-0" aria-hidden="true"></div>
                            <div class="gf-th w-28 shrink-0">Cargo</div>
                            <div class="gf-th w-28 shrink-0">Titular / Supl.</div>
                            <div class="gf-th w-40 shrink-0">Nro. resolución</div>
                            <div class="gf-th w-32 shrink-0">Fecha alta</div>
                            <div class="gf-th w-36 shrink-0">Fecha baja</div>
                            <div class="gf-th w-16 shrink-0 text-center">Hs cát.</div>
                            <div class="gf-th w-12 shrink-0 text-center">Años</div>
                            <div class="gf-th w-12 shrink-0 text-center">Meses</div>
                            <div class="gf-th w-12 shrink-0 text-center">Días</div>
                        </div>

                        @forelse ($serviciosVisibles as $id => $fila)
                            @php
                                $id = (int) $id;
                                $dur = $this->duracionServicio($id);
                                $sinBaja = ($fila['fechaBaja'] ?? '') === '';
                            @endphp
                            <div class="gf-row gf-row-hover" wire:key="serv-row-{{ $id }}">
                                <div class="gf-td-actions w-10 shrink-0 !justify-center">
                                    <button type="button"
                                            data-id="{{ $id }}"
                                            class="inline-flex h-7 w-7 items-center justify-center text-red-600 hover:bg-red-50"
                                            title="Eliminar"
                                            wire:loading.attr="disabled"
                                            wire:target="eliminarServicio"
                                            x-on:click="
                                                const id = Number($event.currentTarget.getAttribute('data-id') || 0);
                                                if (!id) return;
                                                seSwalConfirmar('¿Eliminar este servicio?', 'Confirmar').then(ok => ok && $wire.eliminarServicio(id));
                                            ">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="gf-cell w-28 shrink-0">
                                    <input type="text" wire:model.blur="servicios.{{ $id }}.cargo" wire:blur="guardarServicioFila({{ $id }})" class="gf-input">
                                </div>
                                <div class="gf-cell w-28 shrink-0">
                                    <select wire:model="servicios.{{ $id }}.titularSuplente" wire:change="guardarServicioFila({{ $id }})" class="gf-select">
                                        <option value="">—</option>
                                        <option value="TITULAR">TITULAR</option>
                                        <option value="SUPLENTE">SUPLENTE</option>
                                    </select>
                                </div>
                                <div class="gf-cell w-40 shrink-0">
                                    <input type="text" wire:model.blur="servicios.{{ $id }}.nroResolucion" wire:blur="guardarServicioFila({{ $id }})" class="gf-input">
                                </div>
                                <div class="gf-cell w-32 shrink-0">
                                    <input type="date" wire:model="servicios.{{ $id }}.fechaAlta" wire:change="guardarServicioFila({{ $id }})" class="gf-input">
                                </div>
                                <div class="gf-cell w-36 shrink-0 !flex-col !items-stretch !justify-center !py-0">
                                    <input type="date" wire:model="servicios.{{ $id }}.fechaBaja" wire:change="guardarServicioFila({{ $id }})"
                                           class="gf-input" title="Vacío = Continúa (sigue en el cargo)">
                                    @if ($sinBaja)
                                        <span class="px-2 pb-1 text-[9px] font-semibold uppercase tracking-wide text-primary-700">Continúa</span>
                                    @endif
                                </div>
                                <div class="gf-cell w-16 shrink-0">
                                    <input type="text" wire:model.blur="servicios.{{ $id }}.hsCatedra" wire:blur="guardarServicioFila({{ $id }})" class="gf-input text-center">
                                </div>
                                <div class="gf-td w-12 shrink-0 text-center tabular-nums">{{ $dur['anios'] }}</div>
                                <div class="gf-td w-12 shrink-0 text-center tabular-nums">{{ $dur['meses'] }}</div>
                                <div class="gf-td w-12 shrink-0 text-center tabular-nums">{{ $dur['dias'] }}</div>
                            </div>
                        @empty
                            <div class="gf-empty">Sin servicios cargados.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Licencias --}}
        <div class="space-y-2">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-sm font-bold uppercase tracking-wide text-neutral-800">Licencias</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <input type="search" wire:model.live.debounce.300ms="buscarLicencias" placeholder="Búsqueda rápida…"
                           class="gf-input !w-auto min-w-[12rem] border border-gray-400 bg-white sm:max-w-xs" aria-label="Buscar licencias">
                    <button type="button"
                            wire:click="insertarLicencia"
                            wire:loading.attr="disabled"
                            wire:target="insertarLicencia,eliminarLicencia,guardarLicenciaFila"
                            class="btn-primary btn-sm disabled:opacity-50">Insertar</button>
                </div>
            </div>

            <div class="w-full overflow-x-auto se-grid-angosta-wrap">
                <div class="gf min-w-[40rem]" wire:key="lic-grid-{{ $licenciasGridKey }}">
                    <div class="gf-head">
                        <div class="gf-th w-10 shrink-0" aria-hidden="true"></div>
                        <div class="gf-th w-32 shrink-0">Fecha inicio</div>
                        <div class="gf-th w-32 shrink-0">Fecha fin</div>
                        <div class="gf-th w-24 shrink-0">Parcial</div>
                        <div class="gf-th w-12 shrink-0 text-center">Años</div>
                        <div class="gf-th w-12 shrink-0 text-center">Meses</div>
                        <div class="gf-th w-12 shrink-0 text-center">Días</div>
                    </div>

                    @forelse ($licenciasVisibles as $id => $fila)
                        @php
                            $id = (int) $id;
                            $dur = $this->duracionLicencia($id);
                        @endphp
                        <div class="gf-row gf-row-hover" wire:key="lic-row-{{ $id }}">
                            <div class="gf-td-actions w-10 shrink-0 !justify-center">
                                <button type="button"
                                        data-id="{{ $id }}"
                                        class="inline-flex h-7 w-7 items-center justify-center text-red-600 hover:bg-red-50"
                                        title="Eliminar"
                                        wire:loading.attr="disabled"
                                        wire:target="eliminarLicencia"
                                        x-on:click="
                                            const id = Number($event.currentTarget.getAttribute('data-id') || 0);
                                            if (!id) return;
                                            seSwalConfirmar('¿Eliminar esta licencia?', 'Confirmar').then(ok => ok && $wire.eliminarLicencia(id));
                                        ">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="gf-cell w-32 shrink-0">
                                <input type="date" wire:model="licencias.{{ $id }}.fechaInicio" wire:change="guardarLicenciaFila({{ $id }})" class="gf-input">
                            </div>
                            <div class="gf-cell w-32 shrink-0">
                                <input type="date" wire:model="licencias.{{ $id }}.fechaFin" wire:change="guardarLicenciaFila({{ $id }})" class="gf-input">
                            </div>
                            <div class="gf-cell w-24 shrink-0">
                                <select wire:model="licencias.{{ $id }}.parcial" wire:change="guardarLicenciaFila({{ $id }})" class="gf-select">
                                    <option value="">—</option>
                                    <option value="0">No</option>
                                    <option value="1">Sí</option>
                                </select>
                            </div>
                            <div class="gf-td w-12 shrink-0 text-center tabular-nums">{{ $dur['anios'] }}</div>
                            <div class="gf-td w-12 shrink-0 text-center tabular-nums">{{ $dur['meses'] }}</div>
                            <div class="gf-td w-12 shrink-0 text-center tabular-nums">{{ $dur['dias'] }}</div>
                        </div>
                    @empty
                        <div class="gf-empty">Sin licencias cargadas.</div>
                    @endforelse
                </div>
            </div>
            <p class="text-[11px] text-neutral-500">Si la licencia es parcial (algunos cursos sí y otros no), no se descuenta del tiempo total de servicios.</p>
        </div>
    </div>

    @teleport('body')
        <div>
            @if ($modalImprimir)
                <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                     role="dialog" aria-modal="true" aria-labelledby="cert-serv-imprimir-title">
                    <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalImprimir"></div>
                    <div class="relative z-10 my-auto flex w-full max-w-md max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                        <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                            <h3 id="cert-serv-imprimir-title" class="text-base font-bold text-neutral-900">Imprimir certificación</h3>
                            <p class="mt-1 text-xs text-neutral-500">Estos datos no se guardan; se mantienen mientras no cambie de docente.</p>
                        </div>
                        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                            <div class="gf w-full">
                                <div class="gf-row @error('fechaEmision') gf-cell-err @enderror">
                                    <div class="gf-label gf-label-req w-40">Fecha de emisión</div>
                                    <div class="gf-cell @error('fechaEmision') gf-cell-err @enderror">
                                        <input id="cert-fecha-emision" type="date" wire:model="fechaEmision"
                                               class="gf-input @error('fechaEmision') gf-input-err @enderror">
                                    </div>
                                </div>
                                @error('fechaEmision')
                                    <div class="gf-error-row">
                                        <div class="gf-error-spacer w-40"></div>
                                        <div class="gf-error-msg">{{ $message }}</div>
                                    </div>
                                @enderror
                                <div class="gf-row @error('paraPresentar') gf-cell-err @enderror">
                                    <div class="gf-label w-40">Presentar ante</div>
                                    <div class="gf-cell @error('paraPresentar') gf-cell-err @enderror">
                                        <input id="cert-para-presentar" type="text" wire:model="paraPresentar" maxlength="300"
                                               placeholder="Organismo o destinatario…"
                                               class="gf-input @error('paraPresentar') gf-input-err @enderror">
                                    </div>
                                </div>
                                @error('paraPresentar')
                                    <div class="gf-error-row">
                                        <div class="gf-error-spacer w-40"></div>
                                        <div class="gf-error-msg">{{ $message }}</div>
                                    </div>
                                @enderror
                            </div>
                        </div>
                        <div class="flex shrink-0 justify-end gap-2 border-t border-accent-200 bg-accent-50 px-5 py-3">
                            <button type="button" wire:click="cerrarModalImprimir" class="btn-secondary btn-sm">Cancelar</button>
                            <button type="button" wire:click="emitirPdf" class="btn-primary btn-sm">Emitir PDF</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endteleport

    @script
    <script>
        $wire.on('se-swal-exito', (e) => { window.seSwalExito?.(e.mensaje || e[0]?.mensaje || ''); });
        $wire.on('se-swal-error', (e) => { window.seSwalError?.(e.mensaje || e[0]?.mensaje || 'Error'); });
    </script>
    @endscript
</div>
