<div class="se-page" x-on:cooperadora-abrir-pdf.window="window.open($event.detail.url, '_blank', 'noopener,noreferrer')">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Cooperadora</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Egresos</h2>
            </div>
            <a href="{{ route('cooperadora.egresos.nuevo') }}" class="btn-primary shrink-0">+ Nuevo egreso</a>
        </div>
    </section>

    <div class="se-toolbar flex-wrap gap-3">
        <div>
            <label class="se-label">Desde</label>
            <input type="date" wire:model.live="fechaDesde" class="se-input">
        </div>
        <div>
            <label class="se-label">Hasta</label>
            <input type="date" wire:model.live="fechaHasta" class="se-input">
        </div>
    </div>

    <div class="w-full overflow-x-auto">
        <div class="flex justify-start">
            <div class="gf min-w-[50rem]">
                <div class="gf-head">
                    <div class="gf-th w-24">Fecha</div>
                    <div class="gf-th w-20 text-right">Orden</div>
                    <div class="gf-th w-40">Proveedor</div>
                    <div class="gf-th flex-1">Concepto</div>
                    <div class="gf-th w-28 text-right">Importe</div>
                    <div class="gf-th-right w-36">Acciones</div>
                </div>
                @forelse ($egresos as $egreso)
                    @php $ref = \App\Support\Security\OpaqueRouteToken::forCoopOrdenPago((int) $egreso->id); @endphp
                    <div class="gf-row @if($egreso->anulado) gf-row-anulado @else gf-row-hover @endif" wire:key="egr-{{ $egreso->id }}">
                        <div class="gf-td w-24">{{ $egreso->fecha->format('d/m/Y') }}</div>
                        <div class="gf-td w-20 text-right tabular-nums">
                            {{ $egreso->orden_numero }}
                            @if ($egreso->anulado)
                                <span class="mt-0.5 block se-pill bg-red-100 text-red-800 text-[10px]">Anulado</span>
                            @endif
                        </div>
                        <div class="gf-td w-40">{{ $egreso->proveedor?->nombre }}</div>
                        <div class="gf-td flex-1 truncate">{{ $egreso->concepto }}</div>
                        <div class="gf-td w-28 text-right tabular-nums">${{ number_format((float) $egreso->importe, 2, ',', '.') }}</div>
                        <div class="gf-td-actions w-36 !justify-end gap-1">
                            <a href="{{ route('cooperadora.orden-pago.pdf', ['ref' => $ref]) }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="btn-secondary btn-sm">Orden</a>
                            @if (! $egreso->anulado)
                                <button type="button"
                                        class="btn-danger btn-sm"
                                        title="Anular egreso"
                                        x-on:click="window.seSwalConfirmar(
                                            'El egreso quedará marcado como anulado y dejará de sumar en movimientos y saldos. ¿Continuar?',
                                            'Anular egreso',
                                            { confirmButtonText: 'Sí, anular' }
                                        ).then((ok) => { if (ok) $wire.anular({{ $egreso->id }}); })">
                                    Anular
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="gf-empty">No hay egresos en el período.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-4">{{ $egresos->links() }}</div>

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje, titulo }) => window.seSwalExito(mensaje, titulo ?? 'Listo'));
        $wire.on('se-swal-error', ({ mensaje, titulo }) => window.seSwalError(mensaje, titulo ?? 'Error'));
    </script>
    @endscript
</div>
