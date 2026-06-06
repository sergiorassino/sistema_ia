{{-- Datos adicionales del analítico (analiticodatos) por legajo. --}}
<div class="se-page max-w-3xl">
    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner !gap-3 !p-4 sm:!p-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1 space-y-1">
                <p class="se-eyebrow">Matríz y analíticos</p>
                <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Datos Adicionales</h2>
                @if (! empty($alumno))
                    <p class="text-sm text-white/90">
                        <span class="font-semibold">{{ $alumno['apellido'] }}, {{ $alumno['nombre'] }}</span>
                        @if (($alumno['dni'] ?? '') !== '')
                            · DNI {{ $alumno['dni'] }}
                        @endif
                        @if (($alumno['curso'] ?? '') !== '')
                            · {{ $alumno['curso'] }}
                        @endif
                        <span class="text-white/70"> · {{ schoolCtx()->nivelNombre() }}</span>
                    </p>
                @endif
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <button type="button"
                        wire:click="guardar"
                        wire:loading.attr="disabled"
                        wire:target="guardar"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100 disabled:opacity-60">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" wire:loading.remove wire:target="guardar" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    <span wire:loading.remove wire:target="guardar">Guardar</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
                <x-nav-contexto-estudiante
                    destino="matrizAnaliticos.libroMatriz.editar"
                    :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::MATRIZ_ANALITICOS"
                    :id-legajos="$idLegajos"
                    :buscar="$buscarRetorno"
                    class="inline">
                    <span class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Volver a matriz
                    </span>
                </x-nav-contexto-estudiante>
            </div>
        </div>
    </section>

    @error('guardar')
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            {{ $message }}
        </div>
    @enderror

    <form wire:submit="guardar" class="se-card overflow-hidden p-6 sm:p-7">
        @include('livewire.matriz-analiticos.partials.libro-matriz-datos-adicionales-campos', ['idPrefijo' => 'pagina-da-'])

        @if ($idAnaliticoDato)
            <p class="mt-6 text-xs text-neutral-400">Registro existente · se actualizará al guardar.</p>
        @else
            <p class="mt-6 text-xs text-neutral-400">Sin registro previo: al guardar se creará uno nuevo para este legajo.</p>
        @endif

        <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-accent-200 pt-5">
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="guardar"
                    class="btn-primary">
                <span wire:loading.remove wire:target="guardar">Guardar</span>
                <span wire:loading wire:target="guardar">Guardando…</span>
            </button>
        </div>
    </form>
</div>
