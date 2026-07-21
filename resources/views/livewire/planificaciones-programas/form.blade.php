<div class="se-page max-w-3xl">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Planificaciones y programas</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $etiquetaTipo }}</h2>
                <p class="text-sm text-white/80">{{ $materiaNombre }}</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                @if ($tieneArchivo)
                    <button type="button"
                            x-on:click="seSwalConfirmar('¿Eliminar el archivo y borrar los datos asociados?', 'Eliminar').then(ok => ok && $wire.eliminar())"
                            class="inline-flex items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/20">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Eliminar
                    </button>
                @endif
                <button type="submit" form="pp-form-guardar"
                        class="inline-flex items-center gap-2 rounded-xl border border-white/30 bg-white px-4 py-2 text-sm font-semibold text-primary-700 transition hover:bg-accent-100">
                    « Guardar y volver
                </button>
            </div>
        </div>
    </section>

    <form id="pp-form-guardar" wire:submit="guardar" class="se-card mt-6 space-y-5 p-5 sm:p-6">
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="form-label">Año lectivo</label>
                <input type="text" readonly value="{{ schoolCtx()->terlecAno() }}"
                       class="form-input mt-1.5 w-full bg-accent-50 text-neutral-700">
            </div>
            <div>
                <label class="form-label">Curso y sección</label>
                <input type="text" readonly value="{{ $cursecEtiqueta }}"
                       class="form-input mt-1.5 w-full bg-accent-50 text-neutral-700">
            </div>
        </div>

        <div>
            <label class="form-label">Espacio curricular</label>
            <input type="text" readonly value="{{ $materiaNombre }}"
                   class="form-input mt-1.5 w-full bg-accent-50 text-neutral-700">
        </div>

        @if (! $tieneArchivo || $archivoPdf)
            <div>
                <label class="form-label" for="pp-archivo">Archivo PDF @if (! $tieneArchivo)<span class="text-red-600">*</span>@endif</label>
                <input id="pp-archivo" type="file" wire:model="archivoPdf" accept="application/pdf,.pdf"
                       class="mt-1.5 block w-full text-sm text-neutral-600 file:mr-4 file:rounded-xl file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-700">
                <div wire:loading wire:target="archivoPdf" class="mt-1 text-xs text-neutral-500">Subiendo archivo…</div>
                @error('archivoPdf')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <div class="flex items-center justify-between gap-4 rounded-2xl border border-accent-200 bg-accent-50/60 px-4 py-3">
            <div>
                <p class="text-sm font-semibold text-neutral-800">Aprobado</p>
                <p class="text-xs text-neutral-500">Visible para estudiantes en la descarga pública de programas.</p>
            </div>
            <label class="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" wire:model="aprobado" class="peer sr-only">
                <span class="h-7 w-12 rounded-full bg-neutral-300 transition peer-checked:bg-primary-600 peer-focus:ring-2 peer-focus:ring-primary-500/40"></span>
                <span class="absolute left-0.5 top-0.5 h-6 w-6 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
            </label>
        </div>

        <div>
            <label class="form-label" for="pp-obs">Observaciones</label>
            <textarea id="pp-obs" wire:model="observaciones" rows="4"
                      class="form-input mt-1.5 w-full resize-y leading-relaxed"
                      placeholder="Notas internas sobre el documento…"></textarea>
            @error('observaciones')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-accent-200 pt-4">
            @if ($urlArchivo)
                <a href="{{ $urlArchivo }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 shadow-sm transition hover:border-primary-500 hover:bg-accent-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    Ver archivo
                </a>
            @else
                <span></span>
            @endif
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40">
                <span wire:loading.remove wire:target="guardar,archivoPdf">Guardar</span>
                <span wire:loading wire:target="guardar,archivoPdf">Guardando…</span>
            </button>
        </div>
    </form>

    @script
    <script>
        $wire.on('se-swal-error', ({ mensaje }) => {
            if (typeof seSwalError === 'function') {
                seSwalError(mensaje);
            }
        });
    </script>
    @endscript
</div>
