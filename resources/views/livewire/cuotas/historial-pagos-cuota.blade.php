@php
    use App\Support\Cuotas\ComprobantesAfipCuotaService;
    use App\Support\Cuotas\CuotasFormato;
    use App\Support\Navegacion\ContextoEstudianteSesion;
    use App\Support\Security\OpaqueRouteToken;

    $muestraCompAfip = tenantCuotasFacturacionAfipEnPago();
    $muestraCambiarFechaPago = tenantCuotasFacturacionAfipModo() === 'pago';
    $vistaCuotasNav = ContextoEstudianteSesion::etiquetaVistaCuotas(ContextoEstudianteSesion::CUOTAS_GESTION);
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
            <x-volver-cuotas-estudiante
                :id-legajos="$idLegajo"
                class="inline-flex shrink-0 items-center justify-center rounded-lg border border-white/25 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/20" />
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
                        <div class="gf-th gf-th-accion gf-th-accion-reimp gf-th-accion-label" title="Reimpresión de comprobante">Reimp. Comp.</div>
                        @if ($muestraCompAfip)
                            <div class="gf-th gf-th-accion gf-th-accion-afip gf-th-accion-label" title="Comprobantes AFIP">Comp. AFIP</div>
                        @endif
                        <div class="gf-th gf-th-cuota-nombre">Cuota</div>
                        <div class="gf-th gf-th-fecha-hora">Fecha y hora</div>
                        <div class="gf-th gf-th-mp">M.P.</div>
                        <div class="gf-th gf-th-right gf-th-importe">Importe</div>
                        <div class="gf-th gf-th-right gf-th-importe">Bonif.</div>
                        <div class="gf-th gf-th-right gf-th-importe">Interés</div>
                        <div class="gf-th gf-th-accion gf-th-accion-borrar gf-th-accion-label" title="Borrar pago">Borrar</div>
                        @if ($muestraCambiarFechaPago)
                            <div class="gf-th gf-th-accion gf-th-accion-fecha gf-th-accion-label" title="Cambiar fecha de pago">
                                <span class="gf-th-accion-fecha-label"><span>Cambiar</span><span>Fecha</span><span>Pago</span></span>
                            </div>
                        @endif
                    </div>

                    @foreach ($pagos as $pago)
                        @php
                            $nombreCuota = trim((string) ($registro?->cuota?->nombre ?? ''));
                            $medioPago = trim((string) ($pago->tipoPago?->abrev ?? ''));
                            if ($medioPago === '') {
                                $medioPago = trim((string) ($pago->tipoPago?->tipoPago ?? ''));
                            }
                            $importePago = (float) ($pago->importe ?? 0);
                            $bloqueadoPorFacturaAfip = $muestraCompAfip
                                && ComprobantesAfipCuotaService::facturaVigente((int) $pago->id) !== null;
                        @endphp
                        <div class="gf-row gf-row-hover" wire:key="pago-{{ $pago->id }}">
                            <div class="gf-td gf-td-accion gf-td-accion-reimp !py-1">
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
                            @if ($muestraCompAfip)
                                <div class="gf-td gf-td-accion gf-td-accion-afip !py-1">
                                    @if ($importePago > 0)
                                        <x-nav-contexto-estudiante
                                            destino="cuotas.cuota.comprobantes-afip"
                                            :alcance="ContextoEstudianteSesion::CUOTAS_GESTION"
                                            :id-legajos="$idLegajo"
                                            :id-cuota-generada="$idCuotaGenerada"
                                            :id-cuota-pago="(int) $pago->id"
                                            :vista-cuotas="$vistaCuotasNav"
                                            class="inline">
                                            <span class="inline-flex h-6 w-6 cursor-pointer items-center justify-center rounded border border-gray-400 bg-white text-primary-700 hover:bg-primary-50"
                                                  title="Comprobantes AFIP">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                <span class="sr-only">Comprobantes AFIP</span>
                                            </span>
                                        </x-nav-contexto-estudiante>
                                    @endif
                                </div>
                            @endif
                            <div class="gf-td gf-td-cuota-nombre font-semibold uppercase text-primary-800" title="{{ $nombreCuota }}">{{ $nombreCuota }}</div>
                            <div class="gf-td gf-td-fecha-hora tabular-nums">{{ CuotasFormato::formatearFechaHora($pago->fechhora) }}</div>
                            <div class="gf-td gf-td-mp font-semibold uppercase" title="{{ $medioPago }}">{{ $medioPago }}</div>
                            <div class="gf-td gf-td-importe tabular-nums whitespace-nowrap">{{ CuotasFormato::formatearImporte($pago->importe) }}</div>
                            <div class="gf-td gf-td-importe tabular-nums whitespace-nowrap">{{ CuotasFormato::formatearImporte($pago->bonificacion) }}</div>
                            <div class="gf-td gf-td-importe tabular-nums whitespace-nowrap">{{ CuotasFormato::formatearImporte($pago->interes) }}</div>
                            <div class="gf-td gf-td-accion gf-td-accion-borrar !py-1">
                                @if ($bloqueadoPorFacturaAfip)
                                    <span class="inline-flex h-6 w-6 cursor-not-allowed items-center justify-center rounded border border-gray-200 bg-gray-50 text-gray-400"
                                          title="No se puede borrar: hay factura AFIP vigente. Emita una nota de crédito primero.">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        <span class="sr-only">Borrar pago no disponible (factura AFIP vigente)</span>
                                    </span>
                                @else
                                    <button type="button"
                                            x-on:click="window.seSwalConfirmar('¿Eliminar este pago? Se revertirá el importe en la cuota.', 'Confirmar eliminación', { confirmButtonText: 'Sí, eliminar' }).then((ok) => { if (ok) $wire.borrarPago({{ (int) $pago->id }}); })"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded border border-red-300 bg-white text-red-600 hover:bg-red-50"
                                            title="Borrar pago">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        <span class="sr-only">Borrar pago</span>
                                    </button>
                                @endif
                            </div>
                            @if ($muestraCambiarFechaPago)
                                <div class="gf-td gf-td-accion gf-td-accion-fecha !py-1">
                                    <button type="button"
                                            wire:click="abrirModalFechaPago({{ (int) $pago->id }})"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded border border-gray-400 bg-white text-primary-700 hover:bg-primary-50"
                                            title="Cambiar fecha de pago">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="sr-only">Cambiar fecha de pago</span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    @if ($muestraCambiarFechaPago && $modalFechaPagoAbierto)
        @teleport('body')
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="historial-fecha-pago-titulo"
             wire:key="historial-modal-fecha-pago">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalFechaPago" aria-hidden="true"></div>

            <div class="relative z-10 my-auto flex w-full max-w-md max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl border border-accent-200 bg-white shadow-xl ring-1 ring-black/5"
                 @click.stop>
                <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                    <h3 id="historial-fecha-pago-titulo" class="text-base font-bold text-neutral-900">Cambiar fecha de pago</h3>
                    <p class="mt-1 text-sm text-neutral-600">Ingrese la nueva fecha del pago seleccionado.</p>
                </div>

                <form wire:submit="guardarFechaPago" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                        <div>
                            <label for="historial-fecha-pago" class="form-label">Fecha de pago</label>
                            <input id="historial-fecha-pago"
                                   type="date"
                                   wire:model="fechaPagoEdit"
                                   class="form-input @error('fechaPagoEdit') border-red-400 @enderror"
                                   required>
                            @error('fechaPagoEdit') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-accent-200 bg-accent-50 px-5 py-4">
                        <button type="button"
                                wire:click="cerrarModalFechaPago"
                                class="inline-flex items-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 hover:bg-accent-50">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="inline-flex items-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endteleport
    @endif

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
