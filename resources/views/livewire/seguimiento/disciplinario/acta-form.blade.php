<div class="se-page max-w-4xl mx-auto">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero mb-6">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Seguimiento disciplinario</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Acta</h1>
                <p class="text-sm text-white/80">
                    {{ $alumnoLabel }} · {{ $sancionLabel }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 shrink-0">
                <button type="submit"
                        form="form-sancion-acta"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100 disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">Guardar</span>
                    <span wire:loading wire:target="save">Guardando…</span>
                </button>
                <a href="{{ route('seguimiento.disciplinario') }}"
                   class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                    Volver
                </a>
            </div>
        </div>
    </section>

    <form id="form-sancion-acta"
          x-on:submit.prevent="syncSeHtmlEditors($el); $wire.save()"
          class="se-card space-y-5 p-5 sm:p-6">
        <p class="text-sm text-neutral-600">
            Opcional. Si hay contenido, se imprime en hoja aparte debajo del comunicado y se anexa al refuerzo por correo al notificar a los padres.
        </p>

        <div>
            <x-se-html-editor wire-model="formActa" :value="$formActa" label="Texto del acta" min-height="18rem" />
            @error('formActa') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-accent-200 pt-4">
            <a href="{{ route('seguimiento.disciplinario') }}" class="btn-secondary">Cancelar</a>
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="btn-primary">
                <span wire:loading.remove wire:target="save">Guardar</span>
                <span wire:loading wire:target="save">Guardando…</span>
            </button>
        </div>
    </form>
</div>

@script
<script>
    $wire.on('se-swal-error', (e) => { window.seSwalError?.(e.mensaje ?? e[0]?.mensaje ?? ''); });
</script>
@endscript
