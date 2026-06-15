<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Cooperadora</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Movimientos</h2>
                <p class="text-sm text-white/80">Listado diario o por período con saldo acumulado.</p>
            </div>
            @if ($fechaDesde && $fechaHasta)
                <a href="{{ $this->pdfUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="btn-primary shrink-0">Imprimir PDF</a>
            @endif
        </div>
    </section>

    <div class="se-card mb-4 p-4 sm:p-5">
        <div class="se-toolbar flex-wrap gap-3">
            <div>
                <label class="se-label">Desde</label>
                <input type="date" wire:model.live="fechaDesde" class="se-input">
            </div>
            <div>
                <label class="se-label">Hasta</label>
                <input type="date" wire:model.live="fechaHasta" class="se-input">
            </div>
            <div>
                <label class="se-label">Movimiento</label>
                <select wire:model.live="tipoMov" class="se-input min-w-[9rem]">
                    <option value="">Todos</option>
                    <option value="ingreso">Ingresos</option>
                    <option value="egreso">Egresos</option>
                </select>
            </div>
            <div>
                <label class="se-label">Categoría ingreso</label>
                <select wire:model.live="tipoIngreso" class="se-input min-w-[10rem]">
                    <option value="">Todas</option>
                    @foreach (\App\Models\CoopRubroIngreso::etiquetasTipo() as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="se-label">Rubro</label>
                <select wire:model.live="idRubro" class="se-input min-w-[12rem]">
                    <option value="">Todos</option>
                    @foreach ($rubros as $rubro)
                        <option value="{{ $rubro->id }}">{{ $rubro->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="se-label">Ítem</label>
                <select wire:model.live="idItem" class="se-input min-w-[12rem]" @disabled($items->isEmpty())>
                    <option value="">Todos</option>
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}">{{ $item->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="se-label">Proveedor</label>
                <select wire:model.live="idProveedor" class="se-input min-w-[12rem]">
                    <option value="">Todos</option>
                    @foreach ($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}">{{ $proveedor->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="se-label">Medio de pago</label>
                <select wire:model.live="idMedioPago" class="se-input min-w-[10rem]">
                    <option value="">Todos</option>
                    @foreach ($mediosPago as $medio)
                        <option value="{{ $medio->id }}">{{ $medio->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="se-label shrink-0 whitespace-nowrap">Buscar</label>
                <input type="search"
                       wire:model.live.debounce.400ms="busqueda"
                       class="se-input min-w-[26rem]"
                       placeholder="Pagador, concepto, proveedor, rubro…">
            </div>
            @if ($hayFiltros)
                <div class="flex items-end">
                    <button type="button" wire:click="limpiarFiltros" class="btn-secondary">Limpiar filtros</button>
                </div>
            @endif
        </div>
    </div>

    <div class="w-full overflow-x-auto">
        <div class="flex justify-start">
            <div class="gf min-w-[56rem]">
                <div class="gf-head">
                    <div class="gf-th w-24">Fecha</div>
                    <div class="gf-th w-20">Tipo</div>
                    <div class="gf-th w-20 text-right">Nº</div>
                    <div class="gf-th flex-1">Detalle</div>
                    <div class="gf-th w-28 text-right">Ingreso</div>
                    <div class="gf-th w-28 text-right">Egreso</div>
                    <div class="gf-th w-28 text-right">Saldo</div>
                </div>
                @forelse ($filas as $fila)
                    <div @class(['gf-row', 'gf-row-anulado' => ! empty($fila->anulado), 'gf-row-hover' => empty($fila->anulado)])>
                        <div class="gf-td w-24">{{ \Carbon\Carbon::parse($fila->fecha)->format('d/m/Y') }}</div>
                        <div class="gf-td w-20 text-xs uppercase">
                            {{ $fila->tipo_mov === 'egreso' ? 'Egreso' : 'Ingreso' }}
                            @if (! empty($fila->anulado))
                                <span class="mt-0.5 block se-pill bg-red-100 text-red-800 text-[10px] normal-case">Anulado</span>
                            @endif
                        </div>
                        <div class="gf-td w-20 text-right tabular-nums">{{ $fila->numero }}</div>
                        <div class="gf-td flex-1 truncate">{{ $fila->detalle }}</div>
                        <div class="gf-td w-28 text-right tabular-nums">
                            @if (! empty($fila->anulado) && $fila->tipo_mov === 'ingreso')
                                <span class="line-through text-neutral-400">${{ number_format((float) $fila->importe_anulado, 2, ',', '.') }}</span>
                            @elseif ($fila->ingreso > 0)
                                ${{ number_format($fila->ingreso, 2, ',', '.') }}
                            @endif
                        </div>
                        <div class="gf-td w-28 text-right tabular-nums">
                            @if (! empty($fila->anulado) && $fila->tipo_mov === 'egreso')
                                <span class="line-through text-neutral-400">${{ number_format((float) $fila->importe_anulado, 2, ',', '.') }}</span>
                            @elseif ($fila->egreso > 0)
                                ${{ number_format($fila->egreso, 2, ',', '.') }}
                            @endif
                        </div>
                        <div class="gf-td w-28 text-right tabular-nums font-medium">${{ number_format($fila->saldo, 2, ',', '.') }}</div>
                    </div>
                @empty
                    <div class="gf-empty">Sin movimientos con los filtros seleccionados.</div>
                @endforelse
                @if ($filas->isNotEmpty())
                    <div class="gf-row border-t-2 border-primary-300 bg-accent-50 font-bold text-primary-900">
                        <div class="gf-td w-24"></div>
                        <div class="gf-td w-20"></div>
                        <div class="gf-td w-20"></div>
                        <div class="gf-td flex-1 uppercase tracking-wide">Totales</div>
                        <div class="gf-td w-28 text-right tabular-nums">${{ number_format($totalIngresos, 2, ',', '.') }}</div>
                        <div class="gf-td w-28 text-right tabular-nums">${{ number_format($totalEgresos, 2, ',', '.') }}</div>
                        <div class="gf-td w-28 text-right tabular-nums">${{ number_format($saldo, 2, ',', '.') }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
