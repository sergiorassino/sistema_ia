<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">{{ $modoPortalDocente ? 'Menú de Docentes' : 'Docentes / Usuarios' }}</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Libro de temas</h2>
                    <p class="text-sm text-white/80">
                        {{ $materia->cursoLabel }} · {{ $materia->materia !== '' ? $materia->materia : ('Materia '.$materia->idMateria) }}
                    </p>
                </div>
                <a href="{{ route($this->rutaIndiceLibroDeTemas()) }}"
                   class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Volver
                </a>
            </div>
        </section>

        <div class="se-toolbar">
            <div class="relative min-w-0 flex-1 sm:max-w-xs">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="search"
                       wire:model.live.debounce.400ms="buscar"
                       placeholder="Búsqueda rápida"
                       class="form-input w-full pl-9"
                       autocomplete="off"
                       aria-label="Búsqueda rápida">
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button"
                        wire:click="abrirNueva"
                        class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                    Nueva Clase
                </button>
                <button type="button"
                        wire:click="copiarUltima"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60">
                    Insertar Copia de Última clase guardada
                </button>
            </div>
            <p class="shrink-0 text-[11px] font-medium tabular-nums text-neutral-500">
                {{ $registros->total() }} {{ $registros->total() === 1 ? 'clase' : 'clases' }}
            </p>
        </div>

        <div class="se-card overflow-hidden">
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <div class="gf min-w-[72rem]">
                        <div class="gf-head items-stretch">
                            <div class="gf-th w-24 shrink-0">Fecha</div>
                            <div class="gf-th w-20 shrink-0 text-center">Clase Nro</div>
                            <div class="gf-th w-20 shrink-0 text-center">Unidad</div>
                            <div class="gf-th w-32 shrink-0">Carácter</div>
                            <div class="gf-th min-w-[10rem] flex-1">Temas</div>
                            <div class="gf-th min-w-[10rem] flex-1">Actividades</div>
                            <div class="gf-th min-w-[10rem] flex-1">Observaciones</div>
                            <div class="gf-th-right w-40 shrink-0">Acciones</div>
                        </div>
                        @forelse ($registros as $r)
                            <div class="gf-row gf-row-hover items-stretch" wire:key="ldt-clase-{{ $r->id }}">
                                <div class="gf-td w-24 shrink-0 tabular-nums">{{ $r->fecha?->format('d/m/Y') ?? '—' }}</div>
                                <div class="gf-td w-20 shrink-0 text-center tabular-nums">{{ (int) $r->claseNro }}</div>
                                <div class="gf-td w-20 shrink-0 text-center tabular-nums">{{ (int) $r->unidad }}</div>
                                <div class="gf-td w-32 shrink-0 whitespace-normal break-words">{{ trim((string) $r->caracter) !== '' ? $r->caracter : '—' }}</div>
                                <div class="gf-td min-w-[10rem] flex-1 whitespace-pre-wrap break-words">{{ trim((string) $r->temas) !== '' ? $r->temas : '—' }}</div>
                                <div class="gf-td min-w-[10rem] flex-1 whitespace-pre-wrap break-words">{{ trim((string) $r->actividades) !== '' ? $r->actividades : '—' }}</div>
                                <div class="gf-td min-w-[10rem] flex-1 whitespace-pre-wrap break-words">{{ trim((string) $r->observaciones) !== '' ? $r->observaciones : '—' }}</div>
                                <div class="gf-td w-40 shrink-0 flex flex-wrap items-center justify-end gap-1.5">
                                    <button type="button"
                                            wire:click="abrirEditar({{ (int) $r->id }})"
                                            class="inline-flex items-center rounded-lg bg-primary-600 px-2 py-1 text-[11px] font-semibold text-white shadow-sm transition hover:bg-primary-700">
                                        Editar
                                    </button>
                                    <button type="button"
                                            x-on:click="seSwalConfirmar('¿Eliminar esta clase del libro de temas?', 'Confirmar').then(ok => ok && $wire.eliminar({{ (int) $r->id }}))"
                                            class="inline-flex items-center rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-red-700 ring-1 ring-red-200 transition hover:bg-red-50">
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="gf-empty">No hay clases cargadas para esta materia.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            @if ($registros->hasPages())
                <div class="se-matriz-list-footer">
                    {{ $registros->links('vendor.pagination.se-compact') }}
                </div>
            @endif
        </div>
    </div>

    @teleport('body')
        <div>
            @if ($modalAbierto)
                <div class="fixed inset-0 z-[1100] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                     role="dialog"
                     aria-modal="true"
                     aria-labelledby="ldt-modal-titulo"
                     wire:key="ldt-modal">
                    <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModal"></div>
                    <div class="relative z-10 my-auto flex w-full max-w-xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),46rem)]">
                        <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                            <h3 id="ldt-modal-titulo" class="text-lg font-bold text-neutral-900">
                                {{ $editId ? 'Editar clase' : 'Nueva clase' }}
                            </h3>
                        </div>
                        <form wire:submit.prevent="guardar" class="flex min-h-0 flex-1 flex-col">
                            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="form-label" for="ldt-fecha">Fecha *</label>
                                        <input id="ldt-fecha" type="date" wire:model="fecha" class="form-input mt-1 w-full">
                                        @error('fecha') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="form-label" for="ldt-clase">Clase Nro *</label>
                                        <input id="ldt-clase" type="number" min="0" max="99999" wire:model="claseNro" class="form-input mt-1 w-full">
                                        @error('claseNro') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="form-label" for="ldt-unidad">Unidad *</label>
                                        <input id="ldt-unidad" type="number" min="0" max="99999" wire:model="unidad" class="form-input mt-1 w-full">
                                        @error('unidad') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label" for="ldt-caracter">Carácter</label>
                                    <input id="ldt-caracter"
                                           type="text"
                                           list="ldt-caracter-opciones"
                                           wire:model="caracter"
                                           class="form-input mt-1 w-full"
                                           maxlength="50"
                                           autocomplete="off">
                                    <datalist id="ldt-caracter-opciones">
                                        @foreach ($sugerenciasCaracter as $opcion)
                                            <option value="{{ $opcion }}"></option>
                                        @endforeach
                                    </datalist>
                                    @error('caracter') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label" for="ldt-temas">Temas</label>
                                    <textarea id="ldt-temas" wire:model="temas" rows="3" class="form-input mt-1 w-full leading-relaxed"></textarea>
                                    @error('temas') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label" for="ldt-actividades">Actividades</label>
                                    <textarea id="ldt-actividades" wire:model="actividades" rows="3" class="form-input mt-1 w-full leading-relaxed"></textarea>
                                    @error('actividades') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label" for="ldt-observaciones">Observaciones</label>
                                    <textarea id="ldt-observaciones" wire:model="observaciones" rows="3" class="form-input mt-1 w-full leading-relaxed"></textarea>
                                    @error('observaciones') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50 px-5 py-3">
                                <button type="button"
                                        wire:click="cerrarModal"
                                        class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-primary-700 ring-1 ring-accent-200 transition hover:bg-white">
                                    Cancelar
                                </button>
                                <button type="submit"
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60">
                                    <span wire:loading.remove wire:target="guardar">Guardar</span>
                                    <span wire:loading wire:target="guardar">Guardando…</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    @endteleport

    @script
    <script>
        $wire.on('se-swal-exito', (e) => {
            const msg = (e && e.mensaje) ? e.mensaje : 'Listo.';
            if (typeof window.seSwalExito === 'function') window.seSwalExito(msg);
        });
        $wire.on('se-swal-error', (e) => {
            const msg = (e && e.mensaje) ? e.mensaje : 'Error.';
            if (typeof window.seSwalError === 'function') window.seSwalError(msg);
        });
    </script>
    @endscript
</div>
