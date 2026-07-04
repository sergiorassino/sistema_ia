@php
    use App\Support\Cuotas\ConsultaAfipComprobanteService;
    use App\Support\Cuotas\CuotasPlantillaCatalog;
@endphp

<div class="se-card overflow-hidden">
    <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
        <p class="text-sm text-neutral-700">
            Elija el tipo de comprobante y una o más cuotas a procesar. En el siguiente paso seleccionará los estudiantes.
        </p>
    </div>

    <div class="border-b border-accent-200 bg-white px-4 py-4 sm:px-5">
        <span class="form-label">Tipo de comprobante</span>
        <div class="mt-2 flex flex-wrap gap-3">
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold shadow-sm transition has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:text-primary-800">
                <input type="radio"
                       name="tipo-operacion-afip"
                       value="{{ ConsultaAfipComprobanteService::TIPO_FACTURA }}"
                       wire:model.live="tipoOperacion"
                       class="text-primary-600 focus:ring-primary-500" />
                {{ ConsultaAfipComprobanteService::etiquetaComprobanteFacturaAfip() }}
            </label>
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold shadow-sm transition has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:text-primary-800">
                <input type="radio"
                       name="tipo-operacion-afip"
                       value="{{ ConsultaAfipComprobanteService::TIPO_NOTA_CREDITO }}"
                       wire:model.live="tipoOperacion"
                       class="text-primary-600 focus:ring-primary-500" />
                Nota de crédito C
            </label>
        </div>
        @error('tipoOperacion')
            <p class="form-error mt-2">{{ $message }}</p>
        @enderror
    </div>

    <div @class(['px-4 py-4 sm:px-5', 'opacity-60' => ! $puedeSeleccionarCuotas])>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Plantillas de cuota</p>
            <span class="se-pill tabular-nums">{{ $cantidadCuotasSeleccionadas }} seleccionada(s)</span>
        </div>
        @if (! $puedeSeleccionarCuotas)
            <p class="mt-1 text-xs text-neutral-500">Seleccione primero el tipo de comprobante para habilitar las cuotas.</p>
        @elseif ($tipoOperacion !== '')
            <p class="mt-1 text-xs text-neutral-600">
                @if ($esNotaCredito)
                    Se anularán facturas vigentes de las cuotas seleccionadas.
                @else
                    Si un alumno tiene varios conceptos seleccionados, se emitirá una sola factura.
                @endif
            </p>
        @endif
        <div class="mt-2 flex flex-wrap gap-2">
            <button type="button"
                    wire:click="seleccionarTodasCuotas"
                    @disabled(! $puedeSeleccionarCuotas)
                    class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 hover:bg-accent-50 disabled:cursor-not-allowed disabled:opacity-50">
                Todas
            </button>
            <button type="button"
                    wire:click="quitarTodasCuotas"
                    @disabled(! $puedeSeleccionarCuotas)
                    class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-accent-50 disabled:cursor-not-allowed disabled:opacity-50">
                Ninguna
            </button>
        </div>
        <ul class="mt-3 max-h-64 space-y-1 overflow-y-auto rounded-xl border border-accent-200 bg-accent-50/30 p-3">
            @foreach ($plantillas as $cuota)
                <li wire:key="cuota-afip-{{ $cuota->id }}">
                    <label @class([
                        'flex items-center gap-2 py-1',
                        'cursor-pointer' => $puedeSeleccionarCuotas,
                        'cursor-not-allowed' => ! $puedeSeleccionarCuotas,
                    ])>
                        <input type="checkbox"
                               wire:model.live="cuotasSeleccionadas"
                               value="{{ $cuota->id }}"
                               @disabled(! $puedeSeleccionarCuotas)
                               class="h-4 w-4 rounded border-accent-300 text-primary-600 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50" />
                        <span class="text-sm text-neutral-800">{{ CuotasPlantillaCatalog::etiquetaCuota($cuota) }}</span>
                    </label>
                </li>
            @endforeach
        </ul>
        @error('cuotasSeleccionadas') <p class="form-error mt-2">{{ $message }}</p> @enderror
    </div>
    <div class="flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/60 px-4 py-3 sm:px-5">
        <button type="button"
                wire:click="continuarAAlumnos"
                @disabled(! $puedeContinuarCuotas)
                class="inline-flex items-center rounded-xl bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50">
            Continuar
        </button>
    </div>
</div>
