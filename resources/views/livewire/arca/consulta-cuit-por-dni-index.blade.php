<div class="se-page max-w-3xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">ARCA</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Consulta CUIT por DNI</h1>
                <p class="text-xs text-white/75">
                    Consulta en ARCA el CUIT o CUIL asociado a un documento nacional de identidad.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                @if (\App\Support\PermisosArca::puedeDescargarGuiasArca())
                    <a href="{{ route('arca.guia-configuracion-facturacion.pdf') }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-white/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Guía ARCA (PDF)
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section class="se-card overflow-hidden mb-4">
        <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
            <p class="text-sm text-neutral-700">
                Ingrese el DNI sin puntos. Si la persona tiene más de un identificador tributario, se listarán todos los devueltos por ARCA.
            </p>
            @if (! $configurado)
                <p class="mt-2 text-xs font-semibold text-red-800">
                    Faltan certificados AFIP en Parámetros del sistema. Configure carpeta, clave y certificado, y autorice el servicio ws_sr_padron_a13 en ARCA.
                </p>
            @elseif ($modoSimulacion)
                <p class="mt-2 text-xs font-semibold text-amber-800">
                    Modo simulación activo: no se consulta ARCA en vivo.
                </p>
            @endif
        </div>

        <form wire:submit="consultar" class="grid gap-4 px-4 py-4 sm:px-5">
            <div>
                <label for="dni-arca" class="form-label">DNI</label>
                <input id="dni-arca"
                       type="text"
                       wire:model="dni"
                       inputmode="numeric"
                       autocomplete="off"
                       placeholder="12345678"
                       class="form-input font-mono tabular-nums max-w-xs" />
                @error('dni')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center justify-center gap-3 se-toolbar-pocos-campos border-t border-accent-100 pt-4">
                <button type="submit"
                        wire:loading.attr="disabled"
                        @disabled(! $configurado)
                        class="inline-flex items-center rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="consultar">Consultar en ARCA</span>
                    <span wire:loading wire:target="consultar">Consultando…</span>
                </button>
                @if ($cuits !== [])
                    <button type="button"
                            wire:click="limpiar"
                            class="inline-flex items-center rounded-xl border border-accent-200 bg-white px-5 py-2.5 text-sm font-semibold text-primary-700 shadow-sm hover:bg-accent-50">
                        Nueva consulta
                    </button>
                @endif
            </div>
        </form>
    </section>

    @if ($cuits !== [])
        <section class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wide text-primary-800">Resultado</h2>
                    <p class="text-xs text-neutral-600 mt-0.5">
                        DNI {{ $dni }}
                    </p>
                </div>
                @if ($simulado)
                    <span class="se-pill bg-amber-100 text-amber-900 border border-amber-200">Simulado</span>
                @endif
            </div>

            <div class="px-4 py-4 sm:px-5 se-grid-angosta-wrap">
                <table class="se-grid-pocos-campos w-max mx-auto">
                    <thead>
                        <tr class="border-b border-accent-200">
                            <th class="pb-2 text-[10px] font-semibold uppercase tracking-wide text-neutral-500 text-left">CUIT / CUIL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cuits as $cuit)
                            <tr class="border-b border-accent-100 last:border-0">
                                <td class="py-2.5 text-sm font-mono font-semibold text-neutral-900 tabular-nums">{{ $cuit }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @script
    <script>
        (function () {
            function mensajeDeEvento(event, fallback) {
                return event?.mensaje ?? event?.detail?.mensaje ?? fallback;
            }

            $wire.on('se-swal-error', (event) => {
                const mensaje = mensajeDeEvento(event, 'No se pudo completar la consulta.');
                if (typeof window.seSwalError === 'function') {
                    window.seSwalError(mensaje);
                }
            });

            $wire.on('se-swal-aviso', (event) => {
                const mensaje = mensajeDeEvento(event, 'Consulta en modo simulación.');
                if (typeof window.seSwalAviso === 'function') {
                    window.seSwalAviso(mensaje);
                }
            });
        })();
    </script>
    @endscript
</div>
