@php
    use App\Support\Cuotas\ConsultaAfipComprobanteService;
    use App\Support\Cuotas\CuotasFormato;
@endphp

<div class="se-page max-w-3xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Gestión de aranceles</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Consulta AFIP</h1>
                <p class="text-xs text-white/75">
                    Consulta en AFIP un comprobante emitido por la institución (factura o nota de crédito).
                </p>
            </div>
        </div>
    </section>

    <section class="se-card overflow-hidden mb-4">
        <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
            <p class="text-sm text-neutral-700">
                Ingrese el número del comprobante. Puede usar solo el número
                @if ($ptoVtaInstitucion)
                    (punto de venta {{ str_pad((string) $ptoVtaInstitucion, 4, '0', STR_PAD_LEFT) }})
                @endif
                o el formato completo <span class="font-mono text-xs">PPPP-NNNNNNNN</span>.
            </p>
            @if ($simulado)
                <p class="mt-2 text-xs font-semibold text-amber-800">
                    Modo simulación activo: no se consulta AFIP en vivo. Si el comprobante existe en el sistema, se muestran sus datos locales.
                </p>
            @endif
        </div>

        <form wire:submit="consultar" class="grid gap-4 px-4 py-4 sm:px-5">
            <div>
                <span class="form-label">Tipo de comprobante</span>
                <div class="mt-2 flex flex-wrap gap-3">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold shadow-sm transition has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:text-primary-800">
                        <input type="radio"
                               name="tipo-afip"
                               value="{{ ConsultaAfipComprobanteService::TIPO_FACTURA }}"
                               wire:model="tipo"
                               class="text-primary-600 focus:ring-primary-500" />
                        {{ ConsultaAfipComprobanteService::etiquetaTipo(ConsultaAfipComprobanteService::TIPO_FACTURA) }}
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold shadow-sm transition has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:text-primary-800">
                        <input type="radio"
                               name="tipo-afip"
                               value="{{ ConsultaAfipComprobanteService::TIPO_NOTA_CREDITO }}"
                               wire:model="tipo"
                               class="text-primary-600 focus:ring-primary-500" />
                        Nota de crédito C
                    </label>
                </div>
                @error('tipo')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="numero-comprobante-afip" class="form-label">Número de comprobante</label>
                <input id="numero-comprobante-afip"
                       type="text"
                       wire:model="numeroComprobante"
                       inputmode="numeric"
                       autocomplete="off"
                       placeholder="@if($ptoVtaInstitucion){{ str_pad((string) $ptoVtaInstitucion, 4, '0', STR_PAD_LEFT) }}-00000123 @else 0001-00000123 @endif"
                       class="form-input font-mono tabular-nums" />
                @error('numeroComprobante')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center justify-center gap-3 se-toolbar-pocos-campos border-t border-accent-100 pt-4">
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="consultar">Consultar en AFIP</span>
                    <span wire:loading wire:target="consultar">Consultando…</span>
                </button>
                @if ($resultadoAfip)
                    <button type="button"
                            wire:click="limpiar"
                            class="inline-flex items-center rounded-xl border border-accent-200 bg-white px-5 py-2.5 text-sm font-semibold text-primary-700 shadow-sm hover:bg-accent-50">
                        Nueva consulta
                    </button>
                @endif
            </div>
        </form>
    </section>

    @if ($resultadoAfip)
        <section class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wide text-primary-800">Resultado AFIP</h2>
                    <p class="text-xs text-neutral-600 mt-0.5">
                        {{ $resultadoAfip['tipo_etiqueta'] ?? 'Comprobante' }}
                        · {{ $resultadoAfip['numero_formateado'] ?? '—' }}
                    </p>
                </div>
                @if (! empty($resultadoAfip['simulado']))
                    <span class="se-pill bg-amber-100 text-amber-900 border border-amber-200">Simulado</span>
                @endif
            </div>

            <dl class="grid gap-0 sm:grid-cols-2">
                <div class="border-b border-accent-100 px-4 py-3 sm:px-5 sm:border-r">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Fecha emisión</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-neutral-900 tabular-nums">{{ $resultadoAfip['fecha_emision'] ?? '—' }}</dd>
                </div>
                <div class="border-b border-accent-100 px-4 py-3 sm:px-5">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Importe total</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-neutral-900 tabular-nums">
                        {{ CuotasFormato::formatearImporte($resultadoAfip['importe_total'] ?? 0) }}
                    </dd>
                </div>
                <div class="border-b border-accent-100 px-4 py-3 sm:px-5 sm:border-r">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Importe neto</dt>
                    <dd class="mt-0.5 text-sm text-neutral-800 tabular-nums">
                        {{ CuotasFormato::formatearImporte($resultadoAfip['importe_neto'] ?? 0) }}
                    </dd>
                </div>
                <div class="border-b border-accent-100 px-4 py-3 sm:px-5">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">IVA</dt>
                    <dd class="mt-0.5 text-sm text-neutral-800 tabular-nums">
                        {{ CuotasFormato::formatearImporte($resultadoAfip['importe_iva'] ?? 0) }}
                    </dd>
                </div>
                <div class="border-b border-accent-100 px-4 py-3 sm:px-5 sm:border-r">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">CAE</dt>
                    <dd class="mt-0.5 text-sm font-mono text-neutral-900 break-all">{{ $resultadoAfip['cae'] ?? '—' }}</dd>
                </div>
                <div class="border-b border-accent-100 px-4 py-3 sm:px-5">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Vto. CAE</dt>
                    <dd class="mt-0.5 text-sm text-neutral-800 tabular-nums">{{ $resultadoAfip['vto_cae'] ?? '—' }}</dd>
                </div>
                <div class="border-b border-accent-100 px-4 py-3 sm:px-5 sm:border-r">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Doc. comprador</dt>
                    <dd class="mt-0.5 text-sm text-neutral-800 tabular-nums">
                        @if (! empty($resultadoAfip['doc_nro']))
                            {{ $resultadoAfip['doc_nro'] }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="border-b border-accent-100 px-4 py-3 sm:px-5">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">CUIT emisor</dt>
                    <dd class="mt-0.5 text-sm font-mono text-neutral-800 tabular-nums">{{ $resultadoAfip['cuit_emisor'] ?? '—' }}</dd>
                </div>
                @if (! empty($resultadoAfip['servicio_desde']) || ! empty($resultadoAfip['servicio_hasta']))
                    <div class="border-b border-accent-100 px-4 py-3 sm:px-5 sm:border-r">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Servicio desde</dt>
                        <dd class="mt-0.5 text-sm text-neutral-800 tabular-nums">{{ $resultadoAfip['servicio_desde'] ?? '—' }}</dd>
                    </div>
                    <div class="border-b border-accent-100 px-4 py-3 sm:px-5">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Servicio hasta</dt>
                        <dd class="mt-0.5 text-sm text-neutral-800 tabular-nums">{{ $resultadoAfip['servicio_hasta'] ?? '—' }}</dd>
                    </div>
                @endif
            </dl>

            @if (! empty($resultadoAfip['comprobantes_asociados']))
                <div class="border-t border-accent-200 px-4 py-3 sm:px-5 bg-accent-50/40">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500 mb-2">Comprobantes asociados</h3>
                    <ul class="space-y-1 text-sm text-neutral-800">
                        @foreach ($resultadoAfip['comprobantes_asociados'] as $asoc)
                            <li class="font-mono tabular-nums">
                                Tipo {{ (int) ($asoc['tipo'] ?? 0) }}
                                · {{ ConsultaAfipComprobanteService::numeroFormateado((int) ($asoc['pto_vta'] ?? 0), (int) ($asoc['nro'] ?? 0)) }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($registroLocal)
                <div class="border-t border-accent-200 px-4 py-3 sm:px-5 bg-primary-50/40">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wide text-primary-700 mb-2">Registro en el sistema</h3>
                    <p class="text-sm text-neutral-800">
                        {{ trim((string) ($registroLocal['nombre_alumno'] ?? '')) }}
                        @if (! empty($registroLocal['dni']))
                            · DNI {{ $registroLocal['dni'] }}
                        @endif
                        · {{ CuotasFormato::formatearImporte($registroLocal['importe'] ?? 0) }}
                        @if (! empty($registroLocal['cae']))
                            · CAE {{ $registroLocal['cae'] }}
                        @endif
                    </p>
                </div>
            @endif
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
