<div class="se-page" x-on:cooperadora-abrir-pdf.window="window.open($event.detail.url, '_blank', 'noopener,noreferrer')">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Cooperadora</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Ingresos</h2>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('cooperadora.ingresos.nuevo', ['tipo' => 'por_alumno']) }}" class="btn-primary btn-sm">Cuota / matrícula</a>
                <a href="{{ route('cooperadora.ingresos.nuevo', ['tipo' => 'eventual']) }}" class="btn-secondary btn-sm">Eventual</a>
                <a href="{{ route('cooperadora.ingresos.nuevo', ['tipo' => 'uniforme']) }}" class="btn-secondary btn-sm">Uniforme</a>
            </div>
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
            <div class="gf min-w-[52rem]">
                <div class="gf-head">
                    <div class="gf-th w-24">Fecha</div>
                    <div class="gf-th w-20 text-right">Recibo</div>
                    <div class="gf-th flex-1">Pagador / detalle</div>
                    <div class="gf-th w-28 text-right">Importe</div>
                    <div class="gf-th-right w-24">PDF</div>
                </div>
                @forelse ($ingresos as $ingreso)
                    @php $ref = \App\Support\Security\OpaqueRouteToken::forCoopRecibo((int) $ingreso->id); @endphp
                    <div class="gf-row gf-row-hover" wire:key="ing-{{ $ingreso->id }}">
                        <div class="gf-td w-24">{{ $ingreso->fecha->format('d/m/Y') }}</div>
                        <div class="gf-td w-20 text-right tabular-nums">{{ $ingreso->recibo_numero }}</div>
                        <div class="gf-td flex-1 min-w-0">
                            <span class="font-medium">{{ $ingreso->pagador_nombre }}</span>
                            <span class="block text-xs text-neutral-500 truncate">{{ $ingreso->rubro?->nombre }}@if($ingreso->item) — {{ $ingreso->item->nombre }}@endif</span>
                        </div>
                        <div class="gf-td w-28 text-right tabular-nums">${{ number_format((float) $ingreso->importe, 2, ',', '.') }}</div>
                        <div class="gf-td-actions w-24">
                            <a href="{{ route('cooperadora.recibo.pdf', ['ref' => $ref]) }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="btn-secondary btn-sm">Recibo</a>
                        </div>
                    </div>
                @empty
                    <div class="gf-empty">No hay ingresos en el período.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-4">{{ $ingresos->links() }}</div>
</div>
