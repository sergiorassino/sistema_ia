<div class="se-page max-w-3xl mx-auto">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-4 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Administración · Gestión de mora</p>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Textos de la Notificación de Deuda</h1>
                <p class="text-sm text-white/80 max-w-2xl">
                    Configure los párrafos que aparecen al inicio y al final de la notificación impresa.
                </p>
            </div>
            <a href="{{ route('mora.gestion-morosos') }}"
               wire:navigate
               class="inline-flex shrink-0 items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                Volver
            </a>
        </div>
    </section>

    <form wire:submit="guardar" class="se-card overflow-hidden p-5 sm:p-6">
        <div class="space-y-5">
            <div>
                <label class="form-label" for="textoInicNotDeuda">Texto Inicial de la Notificación</label>
                <textarea id="textoInicNotDeuda"
                          wire:model="textoInicNotDeuda"
                          rows="6"
                          class="form-input mt-1.5 w-full leading-relaxed @error('textoInicNotDeuda') border-red-400 @enderror"></textarea>
                @error('textoInicNotDeuda') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label" for="textoFinalNotDeuda">Texto Final de la Notificación (para NO BECADOS)</label>
                <textarea id="textoFinalNotDeuda"
                          wire:model="textoFinalNotDeuda"
                          rows="6"
                          class="form-input mt-1.5 w-full leading-relaxed @error('textoFinalNotDeuda') border-red-400 @enderror"></textarea>
                @error('textoFinalNotDeuda') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label" for="textoFinalNotDeudaBec">Texto Final de la Notificación (para BECADOS)</label>
                <textarea id="textoFinalNotDeudaBec"
                          wire:model="textoFinalNotDeudaBec"
                          rows="6"
                          class="form-input mt-1.5 w-full leading-relaxed @error('textoFinalNotDeudaBec') border-red-400 @enderror"></textarea>
                @error('textoFinalNotDeudaBec') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-2 border-t border-accent-200 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('mora.gestion-morosos') }}"
               wire:navigate
               class="inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-5 py-2.5 text-sm font-semibold text-primary-700 transition hover:bg-accent-50">
                Cancelar
            </a>
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="guardar"
                    class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="guardar">Guardar</span>
                <span wire:loading wire:target="guardar">Guardando…</span>
            </button>
        </div>
    </form>
</div>
