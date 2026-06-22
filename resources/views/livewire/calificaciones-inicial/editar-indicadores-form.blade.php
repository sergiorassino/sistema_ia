{{-- Edición de indicadores: Primera y Segunda etapa en la misma pantalla (legacy). --}}
<div class="mx-auto w-full max-w-5xl space-y-4">
    <div class="overflow-hidden rounded-2xl border border-accent-200 bg-white shadow-sm ring-1 ring-black/5">
        <div class="border-b border-accent-200 bg-neutral-800 px-5 py-3">
            <h2 class="text-center text-sm font-bold uppercase tracking-wide text-white sm:text-base">
                {{ $materiaNombre }}
            </h2>
            <p class="mt-1 text-center text-xs text-white/70">{{ $cursoLabel }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-accent-200 bg-accent-50/80 px-4 py-3">
            <div class="flex-1"></div>
            <button type="button"
                    wire:click="guardar"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-60">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" wire:loading.remove wire:target="guardar">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                <span wire:loading.remove wire:target="guardar">Guardar</span>
                <span wire:loading wire:target="guardar">Guardando…</span>
            </button>
            <a href="{{ \App\Support\PortalDocente\CalificacionesInicialPortalDocente::route('indicadores') }}"
               wire:navigate
               class="inline-flex items-center justify-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver a espacios curriculares
            </a>
        </div>

        <div class="divide-y divide-accent-200">
            @foreach ($etapas as $etapa)
                <div class="grid grid-cols-1 sm:grid-cols-[11rem_minmax(0,1fr)]" wire:key="indic-etapa-{{ $etapa }}">
                    <div class="flex items-start border-accent-200 bg-accent-50/50 px-4 py-4 sm:border-r sm:py-5">
                        <span class="text-sm font-semibold text-neutral-800">
                            {{ \App\Support\CalificacionesInicial\CalificacionesInicialIndicadoresCatalogo::etiquetaEtapaFormulario($etapa) }}
                        </span>
                    </div>
                    <div class="px-4 py-4 sm:py-5">
                        <label for="indicadores-etapa-{{ $etapa }}" class="sr-only">
                            {{ \App\Support\CalificacionesInicial\CalificacionesInicialIndicadoresCatalogo::etiquetaEtapaFormulario($etapa) }}
                        </label>
                        <textarea id="indicadores-etapa-{{ $etapa }}"
                                  wire:model="textosPorEtapa.{{ $etapa }}"
                                  rows="14"
                                  class="form-input min-h-[12rem] w-full resize-y rounded-xl leading-relaxed"
                                  placeholder="Indicadores de logro…"></textarea>
                        @error("textosPorEtapa.{$etapa}")
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <p class="text-center text-xs text-neutral-500">
        Calificaciones (inicial) · Ciclo {{ schoolCtx()->terlecAno() }}
    </p>
</div>

@script
<script>
    $wire.on('se-swal-exito', ({ mensaje }) => {
        if (typeof seSwalExito === 'function') {
            seSwalExito(mensaje ?? 'Guardado.');
        }
    });
    $wire.on('se-swal-error', ({ mensaje }) => {
        if (typeof seSwalError === 'function') {
            seSwalError(mensaje ?? 'Error al guardar.');
        }
    });
</script>
@endscript
