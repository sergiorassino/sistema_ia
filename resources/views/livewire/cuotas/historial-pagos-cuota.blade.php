@php
    use App\Support\Cuotas\CuotasFormato;
    use App\Support\Security\OpaqueRouteToken;
@endphp

<div class="se-page max-w-5xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Gestión de aranceles</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Historial de pagos</h1>
                @if ($encabezado)
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/90 sm:text-sm">
                        {{ $encabezado['apellido'] }} {{ $encabezado['nombre'] }}
                    </p>
                @endif
                @if ($registro)
                    <p class="text-xs text-white/75">
                        {{ trim((string) ($registro->cuota?->nombre ?? '')) }}
                    </p>
                @endif
            </div>
            <a href="{{ route('cuotas.estudiante') }}"
               wire:navigate
               class="inline-flex shrink-0 items-center justify-center rounded-lg border border-white/25 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/20">
                Volver
            </a>
        </div>
    </section>

    <section class="se-card se-card-cuotas-grid p-0 overflow-hidden">
        @if ($pagos->isEmpty())
            <div class="py-14 text-center text-sm text-neutral-600">
                Esta cuota no tiene pagos registrados.
            </div>
        @else
            <div class="w-full overflow-x-auto se-grid-angosta-wrap">
                <div class="gf gf-vcenter gf-cuotas-historial-pagos">
                    <div class="gf-head">
                        <div class="gf-th gf-th-accion" title="Reimpresión de comprobante">Reimp. Comp.</div>
                        <div class="gf-th gf-th-cuota-nombre">Cuota</div>
                        <div class="gf-th gf-th-fecha-hora">Fecha y hora del Pago</div>
                        <div class="gf-th gf-th-mp">M.P.</div>
                        <div class="gf-th gf-th-right gf-th-importe">Importe</div>
                        <div class="gf-th gf-th-right gf-th-importe">Bonificación</div>
                        <div class="gf-th gf-th-right gf-th-importe">Interés</div>
                        <div class="gf-th gf-th-accion" title="Borrar pago">Borrar Pago</div>
                    </div>

                    @foreach ($pagos as $pago)
                        @php
                            $nombreCuota = trim((string) ($registro?->cuota?->nombre ?? ''));
                            $medioPago = trim((string) ($pago->tipoPago?->abrev ?? ''));
                            if ($medioPago === '') {
                                $medioPago = trim((string) ($pago->tipoPago?->tipoPago ?? ''));
                            }
                            $importePago = (float) ($pago->importe ?? 0);
                        @endphp
                        <div class="gf-row gf-row-hover" wire:key="pago-{{ $pago->id }}">
                            <div class="gf-td gf-td-accion !py-1">
                                @if ($importePago > 0)
                                    <a href="{{ se_route_url('cuotas.comprobante-imputacion', ['ref' => OpaqueRouteToken::forComprobantePagoImputacionAdministracion((int) $pago->id, $idLegajo)]) }}"
                                       target="_blank" rel="noopener noreferrer"
                                       class="inline-flex h-6 w-6 items-center justify-center rounded border border-gray-400 bg-white text-primary-700 hover:bg-primary-50"
                                       title="Reimprimir comprobante de pago">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                        </svg>
                                        <span class="sr-only">Reimprimir comprobante</span>
                                    </a>
                                @endif
                            </div>
                            <div class="gf-td gf-td-cuota-nombre font-semibold uppercase text-primary-800" title="{{ $nombreCuota }}">{{ $nombreCuota }}</div>
                            <div class="gf-td gf-td-fecha-hora tabular-nums">{{ CuotasFormato::formatearFechaHora($pago->fechhora) }}</div>
                            <div class="gf-td gf-td-mp text-center font-semibold uppercase">{{ $medioPago }}</div>
                            <div class="gf-td gf-td-importe tabular-nums whitespace-nowrap">{{ CuotasFormato::formatearImporte($pago->importe) }}</div>
                            <div class="gf-td gf-td-importe tabular-nums whitespace-nowrap">{{ CuotasFormato::formatearImporte($pago->bonificacion) }}</div>
                            <div class="gf-td gf-td-importe tabular-nums whitespace-nowrap">{{ CuotasFormato::formatearImporte($pago->interes) }}</div>
                            <div class="gf-td gf-td-accion !py-1">
                                <button type="button"
                                        x-on:click="window.seSwalConfirmar('¿Eliminar este pago? Se revertirá el importe en la cuota.', 'Confirmar eliminación', { confirmButtonText: 'Sí, eliminar' }).then((ok) => { if (ok) $wire.borrarPago({{ (int) $pago->id }}); })"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded border border-red-300 bg-white text-red-600 hover:bg-red-50"
                                        title="Borrar pago">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    <span class="sr-only">Borrar pago</span>
                                </button>
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
        })();
    </script>
    @endscript
</div>
