<div class="se-page max-w-4xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">ARCA</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Editar Observación Factura</h1>
                <p class="text-xs text-white/75">
                    Texto por nivel que aparece en el impreso de la factura AFIP, debajo del concepto y de la leyenda de beca.
                </p>
            </div>
        </div>
    </section>

    @if (! $columnaDisponible)
        <section class="se-card p-5 sm:p-6">
            <p class="text-sm font-semibold text-red-800">
                Falta la columna <span class="font-mono">ento.obsFactura</span> en la base de datos. Ejecute la migración o el SQL correspondiente.
            </p>
        </section>
    @else
        <form id="form-obs-factura"
              x-on:submit.prevent="syncSeHtmlEditors($el); $wire.guardar()"
              class="space-y-4">
            @foreach ($bloques as $bloque)
                <section class="se-card space-y-3 p-5 sm:p-6">
                    <div class="border-b border-accent-200 pb-2">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-primary-800">
                            {{ $bloque['etiqueta'] }}
                        </h2>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            Se guarda en el registro <span class="font-mono">ento</span> de este nivel.
                        </p>
                    </div>

                    <div>
                        <x-se-html-editor
                            :wire-model="$bloque['wireModel']"
                            :value="$bloque['value']"
                            :label="'Observación — '.$bloque['etiqueta']"
                            min-height="12rem"
                        />
                        <p class="mt-1.5 text-xs text-neutral-500">
                            Puede escribir varios párrafos (punto aparte). El interlineado se respeta en el impreso.
                        </p>
                        @error($bloque['errorKey'])
                            <p class="form-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </section>
            @endforeach

            <div class="flex flex-wrap justify-end gap-3 rounded-2xl border border-accent-200 bg-white px-4 py-3 shadow-sm">
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="guardar"
                        class="btn-primary">
                    <span wire:loading.remove wire:target="guardar">Guardar los tres niveles</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
            </div>
        </form>
    @endif

    @script
    <script>
        (function () {
            function mensajeDeEvento(event, fallback) {
                return event?.mensaje ?? event?.detail?.mensaje ?? fallback;
            }

            $wire.on('se-swal-exito', (event) => {
                const mensaje = mensajeDeEvento(event, 'Guardado.');
                if (typeof window.seSwalExito === 'function') {
                    window.seSwalExito(mensaje);
                }
            });

            $wire.on('se-swal-error', (event) => {
                const mensaje = mensajeDeEvento(event, 'No se pudo guardar.');
                if (typeof window.seSwalError === 'function') {
                    window.seSwalError(mensaje);
                }
            });
        })();
    </script>
    @endscript
</div>
