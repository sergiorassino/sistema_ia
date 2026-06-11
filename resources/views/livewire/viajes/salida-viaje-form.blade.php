<div class="se-page max-w-4xl mx-auto">
    <section class="se-hero mb-6">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Viajes / Salidas educativas</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">
                    {{ $esNuevo ? 'Nuevo viaje' : 'Editar viaje' }}
                </h1>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 shrink-0">
                <button type="submit"
                        form="form-salida-viaje"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100 disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">Guardar</span>
                    <span wire:loading wire:target="save">Guardando…</span>
                </button>
                <a href="{{ route('viajes.salidas') }}"
                   class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                    Volver al listado
                </a>
            </div>
        </div>
    </section>

    <form id="form-salida-viaje"
          x-on:submit.prevent="syncSeHtmlEditors($el); $wire.save()"
          class="se-card space-y-5 p-5 sm:p-6">
        <div>
            <label for="viaje-titulo" class="form-label">Título</label>
            <input id="viaje-titulo"
                   type="text"
                   wire:model="formTitulo"
                   maxlength="200"
                   class="form-input mt-1.5"
                   placeholder="Nombre de la salida educativa"
                   autofocus>
            @error('formTitulo') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="viaje-desde" class="form-label">Desde</label>
                <input id="viaje-desde" type="date" wire:model="formDesde" class="form-input mt-1.5">
                @error('formDesde') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="viaje-hasta" class="form-label">Hasta</label>
                <input id="viaje-hasta" type="date" wire:model="formHasta" class="form-input mt-1.5">
                @error('formHasta') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <x-se-html-editor wire-model="formTexto" :value="$formTexto" label="Descripción del viaje" min-height="18rem" />
            @error('formTexto') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-accent-200 pt-4">
            <a href="{{ route('viajes.salidas') }}" class="btn-secondary">Cancelar</a>
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
