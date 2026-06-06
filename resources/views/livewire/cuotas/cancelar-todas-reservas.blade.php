<div>
    <div class="se-page max-w-3xl mx-auto">
        <section class="se-hero mb-4">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-0.5">
                    <p class="se-eyebrow">Gestión masiva</p>
                    <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Cancelar todas las Reservas</h1>
                    <p class="text-xs text-white/75">Ciclo lectivo {{ $ano }}</p>
                </div>
            </div>
        </section>

        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <p class="text-sm text-neutral-700">
                    Pone en cero el importe y el saldo pendiente (<code class="text-xs">faltapa</code>) de todas las
                    reservas sin pagos del ciclo lectivo activo.
                </p>
            </div>

            <div class="space-y-4 px-4 py-5 sm:px-5">
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    <p class="font-semibold">Atención</p>
                    <ul class="mt-2 list-none space-y-1">
                        <li>
                            Reservas con importe:
                            <strong>{{ number_format($resumen['conImporte'], 0, ',', '.') }}</strong>
                        </li>
                        <li>
                            Reservas en cero:
                            <strong>{{ number_format($resumen['enCero'], 0, ',', '.') }}</strong>
                        </li>
                    </ul>
                    <p class="mt-3 leading-relaxed">
                        ¿Seguro que desea cancelar las TODAS reservas de TODOS los estudiantes? Esta operación no puede revertise
                    </p>
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="button"
                            wire:loading.attr="disabled"
                            wire:target="cancelar"
                            class="inline-flex items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:opacity-60"
                            x-on:click="window.seSwalConfirmar(
                                @js($mensajeAdvertencia),
                                'Cancelar todas las reservas',
                                { confirmButtonText: 'Sí, cancelar', icon: 'warning' }
                            ).then((ok) => { if (ok) $wire.cancelar(); })">
                        <span wire:loading.remove wire:target="cancelar">Cancelar todas las reservas</span>
                        <span wire:loading wire:target="cancelar">Cancelando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @script
    <script>
        (function () {
            function payloadDeEvento(event, fallback) {
                return event?.mensaje ?? event?.detail?.mensaje ?? fallback;
            }

            function tituloDeEvento(event, fallback) {
                return event?.titulo ?? event?.detail?.titulo ?? fallback;
            }

            $wire.on('se-swal-exito', (event) => {
                const mensaje = payloadDeEvento(event, 'Operación realizada correctamente.');
                const titulo = tituloDeEvento(event, '¡Listo!');
                if (typeof window.seSwalExito === 'function') {
                    window.seSwalExito(mensaje, titulo);
                }
            });

            $wire.on('se-swal-error', (event) => {
                const mensaje = payloadDeEvento(event, 'No se pudo completar la operación.');
                const titulo = tituloDeEvento(event, 'No se pudo completar');
                if (typeof window.seSwalError === 'function') {
                    window.seSwalError(mensaje, titulo);
                }
            });
        })();
    </script>
    @endscript
</div>
