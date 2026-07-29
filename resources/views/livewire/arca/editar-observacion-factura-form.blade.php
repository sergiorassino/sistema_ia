<div class="se-page max-w-4xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">ARCA</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Editar Observación Factura</h1>
                <p class="text-xs text-white/75">
                    Texto que aparece en el impreso de la factura AFIP, debajo del concepto y de la leyenda de beca.
                </p>
            </div>
        </div>
    </section>

    <form id="form-obs-factura"
          x-on:submit.prevent="syncSeHtmlEditors($el); $wire.guardar()"
          class="se-card space-y-5 p-5 sm:p-6">
        <div>
            <x-se-html-editor
                wire-model="obsFactura"
                :value="$obsFactura"
                label="Observación (párrafos)"
                min-height="16rem"
            />
            <p class="mt-1.5 text-xs text-neutral-500">
                Puede escribir varios párrafos (punto aparte). El interlineado se respeta en el impreso.
            </p>
            @error('obsFactura')
                <p class="form-error mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-accent-200 pt-4">
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="guardar"
                    class="btn-primary">
                <span wire:loading.remove wire:target="guardar">Guardar</span>
                <span wire:loading wire:target="guardar">Guardando…</span>
            </button>
        </div>
    </form>

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
