@php
    use App\Support\Cuotas\ComprobantesAfipCuotaService;
    use App\Support\Cuotas\CuotasFormato;
    use App\Support\Security\OpaqueRouteToken;
@endphp

<div class="se-page max-w-5xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Gestión de aranceles</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Comprobantes AFIP</h1>
                @if ($encabezado)
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/90 sm:text-sm">
                        {{ $encabezado['apellido'] }} {{ $encabezado['nombre'] }}
                    </p>
                @endif
                @if ($registro)
                    <p class="text-xs text-white/75">
                        {{ trim((string) ($registro->cuota?->nombre ?? '')) }}
                        @if ($pago)
                            · Pago {{ \App\Support\Cuotas\CuotasFormato::formatearFechaHora($pago->fechhora) }}
                            · {{ CuotasFormato::formatearImporte(ComprobantesAfipCuotaService::importeFacturable($pago)) }}
                        @endif
                    </p>
                @endif
            </div>
            <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                <a href="{{ route('cuotas.cuota.historial-pagos') }}"
                   wire:navigate
                   class="inline-flex items-center justify-center rounded-lg border border-white/25 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/20">
                    Volver al historial
                </a>
            </div>
        </div>
    </section>

    <section class="se-card mb-4 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-center se-toolbar-pocos-campos">
            @if ($puedeGenerarFactura)
                <button type="button"
                        x-on:click="window.seSwalConfirmar('¿Generar la factura AFIP por el importe de este pago?', 'Generar factura', { confirmButtonText: 'Sí, facturar' }).then((ok) => { if (ok) $wire.generarFactura(); })"
                        class="inline-flex items-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                    Generar factura
                </button>
            @else
                <button type="button"
                        disabled
                        title="{{ $mensajeFactura }}"
                        class="inline-flex cursor-not-allowed items-center rounded-xl border border-accent-200 bg-accent-50 px-4 py-2 text-sm font-semibold text-neutral-400">
                    Generar factura
                </button>
            @endif

            @if ($puedeNotaCredito)
                <button type="button"
                        x-on:click="window.seSwalConfirmar('¿Emitir nota de crédito AFIP por el importe de la factura vigente?', 'Nota de crédito', { confirmButtonText: 'Sí, emitir NC' }).then((ok) => { if (ok) $wire.emitirNotaCredito(); })"
                        class="inline-flex items-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 shadow-sm hover:bg-accent-50">
                    Nota de crédito
                </button>
            @else
                <button type="button"
                        disabled
                        title="{{ $mensajeNotaCredito }}"
                        class="inline-flex cursor-not-allowed items-center rounded-xl border border-accent-200 bg-accent-50 px-4 py-2 text-sm font-semibold text-neutral-400">
                    Nota de crédito
                </button>
            @endif
        </div>
        @if ($facturaVigente)
            <p class="mt-3 text-center text-xs text-neutral-600">
                Factura vigente: {{ ComprobantesAfipCuotaService::numeroFormateado($facturaVigente) }}
                · {{ CuotasFormato::formatearImporte($facturaVigente->importePagado) }}
            </p>
        @endif
    </section>

    <section class="se-card se-card-cuotas-grid p-0 overflow-hidden">
        @if ($comprobantes->isEmpty())
            <div class="py-14 text-center text-sm text-neutral-600">
                No hay comprobantes AFIP registrados para este pago.
            </div>
        @else
            <div class="w-full overflow-x-auto se-grid-angosta-wrap">
                <div class="gf gf-vcenter gf-cuotas-comprobantes-afip">
                    <div class="gf-head">
                        <div class="gf-th gf-th-fecha">Fecha</div>
                        <div class="gf-th gf-th-tipo">Tipo</div>
                        <div class="gf-th gf-th-numero">Número</div>
                        <div class="gf-th gf-th-right gf-th-importe">Importe</div>
                        <div class="gf-th gf-th-cae">CAE</div>
                        <div class="gf-th gf-th-accion" title="Reimprimir">Reimp.</div>
                    </div>

                    @foreach ($comprobantes as $comprobante)
                        @php
                            $fechaEmision = trim((string) ($comprobante->fechaEmision ?? ''));
                            if ($fechaEmision !== '' && str_contains($fechaEmision, '/')) {
                                $partes = explode('/', str_replace('-', '/', $fechaEmision));
                                if (count($partes) === 3) {
                                    if (strlen($partes[0]) === 4) {
                                        $fechaEmision = sprintf('%02d/%02d/%04d', (int) $partes[2], (int) $partes[1], (int) $partes[0]);
                                    } else {
                                        $fechaEmision = sprintf('%02d/%02d/%04d', (int) $partes[0], (int) $partes[1], (int) $partes[2]);
                                    }
                                }
                            }
                        @endphp
                        <div class="gf-row gf-row-hover" wire:key="afip-{{ $comprobante->idComprobanteAfip }}">
                            <div class="gf-td gf-td-fecha tabular-nums">{{ $fechaEmision !== '' ? $fechaEmision : '—' }}</div>
                            <div class="gf-td gf-td-tipo font-semibold uppercase text-primary-800" title="{{ ComprobantesAfipCuotaService::etiquetaTipo($comprobante) }}">
                                {{ ComprobantesAfipCuotaService::etiquetaTipo($comprobante) }}
                            </div>
                            <div class="gf-td gf-td-numero tabular-nums font-mono text-sm">
                                {{ ComprobantesAfipCuotaService::numeroFormateado($comprobante) }}
                            </div>
                            <div class="gf-td gf-td-importe tabular-nums">
                                {{ CuotasFormato::formatearImporte($comprobante->importePagado) }}
                            </div>
                            <div class="gf-td gf-td-cae text-xs" title="{{ trim((string) ($comprobante->cae ?? '')) }}">
                                {{ trim((string) ($comprobante->cae ?? '')) }}
                            </div>
                            <div class="gf-td gf-td-accion !py-1">
                                <a href="{{ se_route_url('cuotas.comprobante-afip', ['ref' => OpaqueRouteToken::forComprobanteAfipRegistro((int) $comprobante->idComprobanteAfip, $idLegajo)]) }}"
                                   target="_blank" rel="noopener noreferrer"
                                   class="inline-flex h-6 w-6 items-center justify-center rounded border border-gray-400 bg-white text-primary-700 hover:bg-primary-50"
                                   title="Reimprimir comprobante AFIP">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                    </svg>
                                    <span class="sr-only">Reimprimir</span>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    @script
    <script>
        (function () {
            function mensajeDeEvento(event, fallback) {
                return event?.mensaje ?? event?.detail?.mensaje ?? fallback;
            }

            $wire.on('se-swal-exito', (event) => {
                const mensaje = mensajeDeEvento(event, 'Operación realizada correctamente.');
                if (typeof window.seSwalExito === 'function') {
                    window.seSwalExito(mensaje);
                }
            });

            $wire.on('se-swal-error', (event) => {
                const mensaje = mensajeDeEvento(event, 'No se pudo completar la operación.');
                if (typeof window.seSwalError === 'function') {
                    window.seSwalError(mensaje);
                }
            });

            $wire.on('cuotas-comprobantes-afip-abrir-pdf', (event) => {
                const url = event?.url ?? event?.detail?.url ?? null;
                if (url) {
                    window.open(url, '_blank', 'noopener,noreferrer');
                }
            });
        })();
    </script>
    @endscript
</div>
