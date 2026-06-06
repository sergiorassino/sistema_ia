<div>
    <div class="se-page max-w-[96rem] mx-auto">
        <section class="se-hero mb-6">
            <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Gestión masiva</p>
                    <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl uppercase">
                        Crear / Editar Cuotas — Año {{ $ano }}
                    </h1>
                </div>
                <button type="button"
                        wire:click="abrirModalAlta"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                    + Nueva cuota
                </button>
            </div>
        </section>

        <div class="se-toolbar mb-4" x-data x-init="$nextTick(() => $refs.cuotasPlantillaBuscar?.focus())">
            <div class="relative flex-1 max-w-md">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input wire:model.live.debounce.300ms="search"
                       type="search"
                       x-ref="cuotasPlantillaBuscar"
                       placeholder="Búsqueda Rápida"
                       class="form-input pl-9"
                       autocomplete="off">
            </div>
        </div>

        <div class="se-card overflow-hidden p-2 sm:p-3">
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <div class="gf min-w-[1140px] gf-cuotas-plantilla">
                        <div class="gf-head">
                            <div class="gf-th gf-th-accion w-12" title="Eliminar"></div>
                            <div class="gf-th w-24">Año</div>
                            <div class="gf-th flex-1 min-w-[12rem]">Nombre</div>
                            <div class="gf-th w-36">Id Cuotasmeses</div>
                            <div class="gf-th w-32">Id Cuotastipo</div>
                            <div class="gf-th w-32">Venc 1<br><span class="text-[9px] font-normal normal-case">dd/mm/aaaa</span></div>
                            <div class="gf-th w-32">Venc 2<br><span class="text-[9px] font-normal normal-case">dd/mm/aaaa</span></div>
                            <div class="gf-th w-32">Venc 3<br><span class="text-[9px] font-normal normal-case">dd/mm/aaaa</span></div>
                            <div class="gf-th w-40">Sin Con Beca</div>
                            <div class="gf-th w-16">Orden</div>
                        </div>

                        @forelse ($filas as $key => $row)
                            <div class="gf-row gf-row-hover" wire:key="cuota-row-{{ $key }}">
                                <div class="gf-td gf-td-accion !py-1 w-12">
                                    <button type="button"
                                            x-on:click="window.seSwalConfirmar('¿Eliminar esta plantilla de cuota?', 'Confirmar eliminación', { confirmButtonText: 'Sí, eliminar' }).then((ok) => { if (ok) $wire.deleteRow('{{ $key }}'); })"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-300 bg-white text-red-600 hover:bg-red-50"
                                            title="Eliminar">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>

                                <div class="gf-td w-24">
                                    <select wire:model.defer="draft.{{ $key }}.idTerlec"
                                            disabled
                                            class="gf-inline-select font-mono text-neutral-700 opacity-80 cursor-not-allowed"
                                            title="Solo cuotas del ciclo lectivo activo">
                                        @foreach ($terlecs as $t)
                                            <option value="{{ $t->id }}">{{ $t->ano }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="gf-td flex-1 min-w-[12rem]">
                                    <input type="text"
                                           wire:model.live.debounce.500ms="draft.{{ $key }}.nombre"
                                           maxlength="120"
                                           class="gf-inline w-full @error('draft.'.$key.'.nombre') ring-2 ring-red-400 @enderror">
                                    @error('draft.'.$key.'.nombre')
                                        <div class="text-[10px] text-red-700 mt-0.5">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="gf-td w-36">
                                    <select wire:model.live="draft.{{ $key }}.idCuotasmeses"
                                            class="gf-inline-select @error('draft.'.$key.'.idCuotasmeses') ring-2 ring-red-400 @enderror">
                                        @foreach ($meses as $m)
                                            <option value="{{ $m->id }}">{{ $m->mes }}</option>
                                        @endforeach
                                    </select>
                                    @error('draft.'.$key.'.idCuotasmeses')
                                        <div class="text-[10px] text-red-700 mt-0.5">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="gf-td w-32">
                                    <select wire:model.live="draft.{{ $key }}.idCuotastipo"
                                            class="gf-inline-select @error('draft.'.$key.'.idCuotastipo') ring-2 ring-red-400 @enderror">
                                        @foreach ($tipos as $t)
                                            <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error('draft.'.$key.'.idCuotastipo')
                                        <div class="text-[10px] text-red-700 mt-0.5">{{ $message }}</div>
                                    @enderror
                                </div>

                                @foreach (['venc1', 'venc2', 'venc3'] as $campoVenc)
                                    <div class="gf-td w-32">
                                        <input type="date"
                                               wire:model.live="draft.{{ $key }}.{{ $campoVenc }}"
                                               class="gf-inline font-mono text-xs @error('draft.'.$key.'.'.$campoVenc) ring-2 ring-red-400 @enderror">
                                        @error('draft.'.$key.'.'.$campoVenc)
                                            <div class="text-[10px] text-red-700 mt-0.5">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endforeach

                                <div class="gf-td w-40">
                                    <select wire:model.live="draft.{{ $key }}.sinConBeca"
                                            class="gf-inline-select @error('draft.'.$key.'.sinConBeca') ring-2 ring-red-400 @enderror">
                                        @foreach ($opcionesBeca as $valor => $etiqueta)
                                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                    @error('draft.'.$key.'.sinConBeca')
                                        <div class="text-[10px] text-red-700 mt-0.5">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="gf-td w-16">
                                    <input type="text"
                                           inputmode="numeric"
                                           maxlength="4"
                                           wire:model.live.debounce.400ms="draft.{{ $key }}.orden"
                                           class="gf-inline font-mono w-full @error('draft.'.$key.'.orden') ring-2 ring-red-400 @enderror">
                                    @error('draft.'.$key.'.orden')
                                        <div class="text-[10px] text-red-700 mt-0.5">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        @empty
                            <div class="gf-row">
                                <div class="gf-td col-span-full py-10 text-center text-sm text-neutral-500 w-full min-w-[1140px]">
                                    @if (trim($search) !== '')
                                        No hay cuotas que coincidan con la búsqueda.
                                    @else
                                        No hay plantillas de cuota para el año {{ $ano }}. Use «Nueva cuota» para agregar la primera.
                                    @endif
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        @script
        <script>
            (function () {
                function mensajeDeEvento(event, fallback) {
                    return event?.mensaje ?? event?.detail?.mensaje ?? fallback;
                }

                $wire.on('se-swal-exito', (event) => {
                    const mensaje = mensajeDeEvento(event, 'Operación realizada correctamente.');
                    if (typeof window.seSwalExito === 'function') {
                        window.seSwalExito(mensaje);
                    }
                });

                $wire.on('se-swal-error', (event) => {
                    const mensaje = mensajeDeEvento(event, 'No se pudo completar la operación.');
                    if (typeof window.seSwalError === 'function') {
                        window.seSwalError(mensaje);
                    }
                });
            })();
        </script>
        @endscript
    </div>

    @if ($modalAltaAbierto)
        @teleport('body')
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="cuota-alta-titulo"
             wire:key="cuota-modal-alta">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalAlta" aria-hidden="true"></div>

            <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl border border-accent-200 bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),44rem)]"
                 @click.stop>
                <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                    <h3 id="cuota-alta-titulo" class="text-base font-bold text-neutral-900">Nueva plantilla de cuota</h3>
                    <p class="mt-1 text-sm text-neutral-600">Ciclo lectivo {{ $ano }}</p>
                </div>

                <form wire:submit="guardarNuevaCuota" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                        @if (! $hayCuotasModelo)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">
                                <p class="font-semibold">Primera cuota del año</p>
                                <p class="mt-1 leading-relaxed">
                                    No hay otra plantilla en este ciclo para tomar como modelo. Configure importes y, si corresponde, fórmulas distintas por curso en
                                    <strong>Importes por curso</strong>. Las siguientes cuotas del año podrán copiar bonificaciones e intereses desde esta.
                                </p>
                                <p class="mt-2 text-xs text-amber-900/80">
                                    No se pueden usar cuotas de años anteriores: los cursos tienen otros identificadores.
                                </p>
                            </div>
                        @endif

                        <div>
                            <label for="alta-nombre" class="form-label">Nombre</label>
                            <input id="alta-nombre"
                                   type="text"
                                   wire:model="alta.nombre"
                                   maxlength="120"
                                   class="form-input @error('alta.nombre') border-red-400 @enderror"
                                   required>
                            @error('alta.nombre') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="alta-mes" class="form-label">Mes</label>
                                <select id="alta-mes"
                                        wire:model="alta.idCuotasmeses"
                                        class="form-input @error('alta.idCuotasmeses') border-red-400 @enderror">
                                    <option value="">— Seleccione —</option>
                                    @foreach ($meses as $m)
                                        <option value="{{ $m->id }}">{{ $m->mes }}</option>
                                    @endforeach
                                </select>
                                @error('alta.idCuotasmeses') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="alta-tipo" class="form-label">Cuota</label>
                                <select id="alta-tipo"
                                        wire:model="alta.idCuotastipo"
                                        class="form-input @error('alta.idCuotastipo') border-red-400 @enderror">
                                    <option value="">— Seleccione —</option>
                                    @foreach ($tipos as $t)
                                        <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('alta.idCuotastipo') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            @foreach (['venc1' => 'Vencimiento 1', 'venc2' => 'Vencimiento 2', 'venc3' => 'Vencimiento 3'] as $campo => $etiqueta)
                                <div>
                                    <label for="alta-{{ $campo }}" class="form-label">{{ $etiqueta }}</label>
                                    <input id="alta-{{ $campo }}"
                                           type="date"
                                           wire:model="alta.{{ $campo }}"
                                           class="form-input font-mono text-xs @error('alta.'.$campo) border-red-400 @enderror"
                                           @if ($campo === 'venc1') required @endif>
                                    @error('alta.'.$campo) <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="alta-beca" class="form-label">Sin / con beca</label>
                                <select id="alta-beca"
                                        wire:model="alta.sinConBeca"
                                        class="form-input @error('alta.sinConBeca') border-red-400 @enderror">
                                    @foreach ($opcionesBeca as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                                @error('alta.sinConBeca') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="alta-orden" class="form-label">Orden</label>
                                <input id="alta-orden"
                                       type="text"
                                       inputmode="numeric"
                                       maxlength="4"
                                       wire:model="alta.orden"
                                       class="form-input font-mono @error('alta.orden') border-red-400 @enderror">
                                @error('alta.orden') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <fieldset class="space-y-3 rounded-xl border border-accent-200 bg-accent-50/50 px-4 py-3">
                            <legend class="text-[11px] font-semibold uppercase tracking-wide text-neutral-600">
                                Bonificaciones e intereses iniciales (por curso)
                            </legend>
                            <p class="text-xs leading-relaxed text-neutral-600">
                                Los importes por curso se cargan después en Importes y quedarán en $0 al crear la plantilla.
                            </p>

                            <label class="flex cursor-pointer items-start gap-2">
                                <input type="radio"
                                       name="origenFormulas"
                                       value="defaults"
                                       wire:model.live="origenFormulas"
                                       class="mt-1">
                                <span class="text-sm text-neutral-800">Valores por defecto del sistema</span>
                            </label>

                            @if ($hayCuotasModelo)
                                <label class="flex cursor-pointer items-start gap-2">
                                    <input type="radio"
                                           name="origenFormulas"
                                           value="modelo"
                                           wire:model.live="origenFormulas"
                                           class="mt-1">
                                    <span class="text-sm text-neutral-800">Copiar desde otra cuota de este año (curso a curso)</span>
                                </label>

                                @if ($origenFormulas === 'modelo')
                                    <div class="pl-6">
                                        <label for="alta-cuota-modelo" class="form-label">Cuota modelo</label>
                                        <select id="alta-cuota-modelo"
                                                wire:model="idCuotaModeloFormulas"
                                                class="form-input @error('idCuotaModeloFormulas') border-red-400 @enderror">
                                            @foreach ($cuotasModelo as $c)
                                                <option value="{{ $c->id }}">{{ \App\Support\Cuotas\CuotasPlantillaCatalog::etiquetaCuota($c) }}</option>
                                            @endforeach
                                        </select>
                                        @error('idCuotaModeloFormulas') <p class="form-error">{{ $message }}</p> @enderror
                                        <p class="mt-1 text-xs text-neutral-500">
                                            Si un curso tenía fórmulas distintas en la cuota modelo, se replican solo para ese curso. Cursos nuevos usan valores por defecto.
                                        </p>
                                    </div>
                                @endif
                            @endif
                        </fieldset>
                    </div>

                    <div class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/80 px-5 py-4">
                        <button type="button" wire:click="cerrarModalAlta" class="btn-secondary">Cancelar</button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="guardarNuevaCuota"
                                class="btn-primary">
                            <span wire:loading.remove wire:target="guardarNuevaCuota">Crear cuota</span>
                            <span wire:loading wire:target="guardarNuevaCuota">Creando…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endteleport
    @endif
</div>
