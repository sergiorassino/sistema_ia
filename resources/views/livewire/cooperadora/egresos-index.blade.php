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
                    <div class="gf-th-right w-24">PDF</div>
                </div>
                @forelse ($egresos as $egreso)
                    @php $ref = \App\Support\Security\OpaqueRouteToken::forCoopOrdenPago((int) $egreso->id); @endphp
                    <div class="gf-row gf-row-hover" wire:key="egr-{{ $egreso->id }}">
                        <div class="gf-td w-24">{{ $egreso->fecha->format('d/m/Y') }}</div>
                        <div class="gf-td w-20 text-right tabular-nums">{{ $egreso->orden_numero }}</div>
                        <div class="gf-td w-40">{{ $egreso->proveedor?->nombre }}</div>
                        <div class="gf-td flex-1 truncate">{{ $egreso->concepto }}</div>
                        <div class="gf-td w-28 text-right tabular-nums">${{ number_format((float) $egreso->importe, 2, ',', '.') }}</div>
                        <div class="gf-td-actions w-24">
                            <a href="{{ route('cooperadora.orden-pago.pdf', ['ref' => $ref]) }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="btn-secondary btn-sm">Orden</a>
                        </div>
                    </div>
                @empty
                    <div class="gf-empty">No hay egresos en el período.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-4">{{ $egresos->links() }}</div>
</div>
