<div class="se-page max-w-3xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Resúmenes</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Listado de pagos por fecha</h1>
                <p class="text-xs text-white/75">
                    Ciclo lectivo {{ $ano }} · Pagos registrados en el rango elegido
                </p>
            </div>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
            <p class="text-sm text-neutral-700">
                Indique el rango de fechas de pago y, si corresponde, filtre por medio de pago o cuota del ciclo activo.
            </p>
        </div>

        <div class="grid gap-4 px-4 py-4 sm:grid-cols-2 sm:px-5">
            <div>
                <label for="fecha-desde-pagos" class="form-label">Fecha de pago desde</label>
                <input id="fecha-desde-pagos"
                       type="date"
                       wire:model.live="fechaDesde"
                       class="form-input tabular-nums" />
            </div>
            <div>
                <label for="fecha-hasta-pagos" class="form-label">Fecha de pago hasta</label>
                <input id="fecha-hasta-pagos"
                       type="date"
                       wire:model.live="fechaHasta"
                       class="form-input tabular-nums" />
            </div>
            <div>
                <label for="medio-pago-pagos" class="form-label">Medio de pago</label>
                <select id="medio-pago-pagos"
                        wire:model.live="idMedioPago"
                        class="form-input">
                    <option value="0">Todos</option>
                    @foreach ($mediosDePago as $medio)
                        <option value="{{ (int) $medio->id }}">{{ $medio->tipoPago }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="cuota-pagos" class="form-label">Cuota</label>
                <select id="cuota-pagos"
                        wire:model.live="idCuota"
                        class="form-input">
                    <option value="0">Todas</option>
                    @foreach ($cuotas as $cuota)
                        <option value="{{ (int) $cuota->id }}">{{ $cuota->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($pdfUrl !== '#')
            <div class="border-t border-accent-200 bg-accent-50/60 px-4 py-4 sm:px-5">
                <p class="mb-3 text-sm text-neutral-600">
                    Se generará un PDF con los pagos recibidos entre
                    {{ \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y') }}
                    y {{ \Carbon\Carbon::parse($fechaHasta)->format('d/m/Y') }}.
                </p>
                <a href="{{ $pdfUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir listado (PDF)
                </a>
            </div>
        @else
            <div class="border-t border-accent-200 px-4 py-6 sm:px-5">
                <p class="text-center text-sm text-neutral-600 sm:text-left">
                    Revise las fechas: la fecha hasta debe ser igual o posterior a la fecha desde.
                </p>
            </div>
        @endif
    </div>
</div>
