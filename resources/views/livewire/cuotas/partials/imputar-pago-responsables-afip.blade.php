@php
    $filasFacturar = [
        'madre' => [
            'etiqueta' => 'Madre',
            'nombre' => 'facturarMadreNombre',
            'dni' => 'facturarMadreDni',
            'editable' => true,
        ],
        'padre' => [
            'etiqueta' => 'Padre',
            'nombre' => 'facturarPadreNombre',
            'dni' => 'facturarPadreDni',
            'editable' => true,
        ],
        'resp_admin' => [
            'etiqueta' => 'Responsable Administrativo',
            'nombre' => 'facturarRespAdmiNombre',
            'dni' => 'facturarRespAdmiDni',
            'editable' => true,
        ],
        'estudiante' => [
            'etiqueta' => 'Estudiante',
            'nombre' => 'facturarEstudianteNombre',
            'dni' => 'facturarEstudianteDni',
            'editable' => false,
        ],
    ];
@endphp

<div class="space-y-2.5">
    <p class="se-section-title text-center">Facturar a</p>
    <p class="text-center text-[11px] text-neutral-500">
        El interruptor indica a quién se emite el comprobante AFIP. Madre, padre y responsable se guardan en el legajo al salir del campo.
    </p>
    <div class="space-y-1.5">
        @foreach ($filasFacturar as $clave => $fila)
            @php
                $seleccionado = $facturarA === $clave;
            @endphp
            <div class="grid grid-cols-1 items-center gap-2 rounded-xl border px-3 py-2 sm:grid-cols-[auto_minmax(9.5rem,11rem)_minmax(0,1fr)_7.5rem]
                {{ $seleccionado ? 'border-primary-400 bg-primary-50/70' : 'border-accent-200 bg-white' }}">
                <div class="flex items-center gap-2 sm:contents">
                    <button type="button"
                            wire:click="seleccionarFacturarA('{{ $clave }}')"
                            class="relative h-6 w-11 shrink-0 rounded-full transition
                                {{ $seleccionado ? 'bg-primary-600' : 'bg-neutral-300' }}"
                            role="radio"
                            aria-checked="{{ $seleccionado ? 'true' : 'false' }}"
                            aria-label="Facturar a {{ $fila['etiqueta'] }}">
                        <span class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all
                            {{ $seleccionado ? 'left-[1.35rem]' : 'left-0.5' }}"></span>
                    </button>
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-neutral-600 sm:min-w-[9.5rem]">
                        {{ $fila['etiqueta'] }}
                    </span>
                </div>
                <div>
                    <label class="sr-only" for="facturar-nombre-{{ $clave }}">Nombre {{ $fila['etiqueta'] }}</label>
                    <input id="facturar-nombre-{{ $clave }}"
                           type="text"
                           maxlength="{{ $clave === 'resp_admin' ? 100 : 50 }}"
                           @if ($fila['editable'])
                               wire:model.blur="{{ $fila['nombre'] }}"
                               wire:blur="guardarResponsablesAlSalirDelCampo('{{ $fila['nombre'] }}', $event.target.value)"
                           @else
                               wire:model="{{ $fila['nombre'] }}"
                           @endif
                           @if (! $fila['editable']) readonly tabindex="-1" @endif
                           class="form-input !py-1.5 w-full text-sm {{ $fila['editable'] ? '' : 'bg-accent-50' }}"
                           placeholder="Apellidos y nombres">
                </div>
                <div>
                    <label class="sr-only" for="facturar-dni-{{ $clave }}">DNI {{ $fila['etiqueta'] }}</label>
                    <input id="facturar-dni-{{ $clave }}"
                           type="text"
                           inputmode="numeric"
                           maxlength="11"
                           @if ($fila['editable'])
                               wire:model.blur="{{ $fila['dni'] }}"
                               wire:blur="guardarResponsablesAlSalirDelCampo('{{ $fila['dni'] }}', $event.target.value)"
                           @else
                               wire:model="{{ $fila['dni'] }}"
                               readonly tabindex="-1"
                           @endif
                           class="form-input !py-1.5 w-full text-sm tabular-nums {{ $fila['editable'] ? '' : 'bg-accent-50' }}"
                           placeholder="DNI">
                </div>
            </div>
        @endforeach
    </div>
    @error('facturarA') <p class="form-error text-center">{{ $message }}</p> @enderror
</div>
