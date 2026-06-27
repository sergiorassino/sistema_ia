<div class="se-cierre-anual-grid se-cierre-anual-grid--matriz-listado">
    <section class="se-hero se-matriz-list-hero min-w-0 shrink-0">
        <div class="se-hero-inner flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow !text-[10px]">Medios de pago · SIRO</p>
                <h2 class="font-bold tracking-tight">{{ $titulo }}</h2>
                <p class="text-xs text-white/80 truncate">{{ $subtitulo }}</p>
            </div>
            <button type="button"
                    wire:click="volverAFiltros"
                    class="inline-flex shrink-0 items-center justify-center gap-1 rounded-lg border border-white/25 bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-white/20">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver a filtros
            </button>
        </div>
    </section>

    <div class="se-matriz-list-toolbar">
        @if ($cantidadSubeSiro > 0)
            <button type="button"
                    x-on:click="$dispatch(@js($confirmEvent), {
                        mensaje: @js($confirmMensaje),
                        titulo: 'Procesar y descargar'
                    })"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700 sm:text-sm sm:px-4 sm:py-2.5">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Procesar y Generar Archivo de Pagos para subir a SIRO
            </button>
        @else
            <button type="button"
                    disabled
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-neutral-300 px-3 py-2 text-xs font-semibold text-neutral-600 cursor-not-allowed sm:text-sm sm:px-4 sm:py-2.5">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Procesar y Generar Archivo de Pagos para subir a SIRO
            </button>
        @endif
        <p class="ml-auto shrink-0 text-[11px] font-medium tabular-nums text-neutral-500">
            {{ count($filasGrilla) }} registro(s)
        </p>
    </div>

    @include('livewire.cuotas.partials.siro-subida-grilla-tabla', [
        'filasGrilla' => $filasGrilla,
        'cantidadSubeSiro' => $cantidadSubeSiro,
        'cantidadNoSubeSiro' => $cantidadNoSubeSiro,
        'filaKeyPrefix' => $filaKeyPrefix,
    ])
</div>
