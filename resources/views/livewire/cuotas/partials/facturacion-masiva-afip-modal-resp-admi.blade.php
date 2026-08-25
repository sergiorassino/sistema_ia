@php
    use App\Support\Cuotas\CuotasFormato;
@endphp

@if ($modalRespAdmiAbierto)
    <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-hidden px-3 py-2 sm:px-4"
         role="dialog"
         aria-modal="true"
         aria-labelledby="modal-resp-admi-titulo">
        <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalRespAdmi"></div>

        <div class="relative z-10 my-auto flex w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
            <div class="shrink-0 border-b border-accent-200 px-4 py-2.5">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                    <p id="modal-resp-admi-titulo" class="text-base font-bold text-neutral-800">Destinatario de facturación AFIP</p>
                    @if ($respAdmiEstudianteEtiqueta !== '')
                        <p class="text-[11px] font-medium text-neutral-600">{{ $respAdmiEstudianteEtiqueta }}</p>
                    @endif
                </div>
            </div>

            <div class="shrink-0 space-y-2.5 px-4 py-3">
                <p class="text-[11px] text-neutral-500">
                    Elija padre, madre o tutor para completar, o escriba los datos. Se guardan en el legajo.
                </p>

                <div class="grid grid-cols-3 gap-1.5">
                    @foreach (['padre' => 'Padre', 'madre' => 'Madre', 'tutor' => 'Tutor'] as $vinculo => $etiqueta)
                        @php
                            $filaVinculo = $respAdmiVinculos[$vinculo] ?? [];
                            $apellidoVinculo = trim((string) ($filaVinculo['apellido'] ?? ''));
                            $nombrePilaVinculo = trim((string) ($filaVinculo['nombrePila'] ?? ''));
                            $dniVinculo = (string) ($filaVinculo['dni'] ?? '');
                            $emailVinculo = trim((string) ($filaVinculo['email'] ?? ''));
                            $tieneDatosVinculo = (bool) ($filaVinculo['tieneDatos'] ?? false);
                            $nombreMostrar = $apellidoVinculo;
                            if ($nombrePilaVinculo !== '') {
                                $nombreMostrar .= ($nombreMostrar !== '' ? ', ' : '').$nombrePilaVinculo;
                            }
                            if ($nombreMostrar === '') {
                                $nombreMostrar = trim((string) ($filaVinculo['nombre'] ?? ''));
                            }
                        @endphp
                        <button type="button"
                                wire:click="seleccionarRespAdmiVinculo('{{ $vinculo }}')"
                                @disabled(! $tieneDatosVinculo)
                                title="{{ $tieneDatosVinculo ? $nombreMostrar.($dniVinculo !== '' ? ' · DNI '.CuotasFormato::formatearDni($dniVinculo) : '').($emailVinculo !== '' ? ' · '.$emailVinculo : '') : 'Sin datos en el legajo' }}"
                                class="flex w-full flex-col items-start rounded-lg border px-2 py-1.5 text-left leading-tight transition
                                    {{ $respAdmiVinculo === $vinculo
                                        ? 'border-primary-500 bg-primary-600 text-white'
                                        : ($tieneDatosVinculo
                                            ? 'border-accent-200 bg-white text-neutral-800 hover:border-primary-300 hover:bg-accent-50'
                                            : 'cursor-not-allowed border-accent-100 bg-accent-50/60 text-neutral-400') }}">
                            <span class="text-[9px] font-semibold uppercase tracking-wide
                                {{ $respAdmiVinculo === $vinculo ? 'text-white/90' : 'text-primary-700' }}">
                                {{ $etiqueta }}
                            </span>
                            @if ($tieneDatosVinculo)
                                <span class="mt-0.5 line-clamp-2 text-[10px] font-medium">
                                    {{ $nombreMostrar !== '' ? $nombreMostrar : '—' }}
                                </span>
                                <span class="mt-0.5 text-[9px] tabular-nums
                                    {{ $respAdmiVinculo === $vinculo ? 'text-white/85' : 'text-neutral-500' }}">
                                    @if ($dniVinculo !== '')
                                        {{ CuotasFormato::formatearDni($dniVinculo) }}
                                    @else
                                        Sin DNI
                                    @endif
                                </span>
                            @else
                                <span class="mt-0.5 text-[10px] italic">Sin datos</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="form-label !mb-0.5" for="resp-admi-nombre">Apellidos y nombres *</label>
                        <input wire:model="respAdmiNombre"
                               id="resp-admi-nombre"
                               type="text"
                               maxlength="100"
                               class="form-input !py-1.5 text-sm w-full @error('respAdmiNombre') ring-2 ring-red-400 @enderror"
                               placeholder="Apellidos y nombres a facturar">
                        @error('respAdmiNombre') <p class="se-field-error text-[11px]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label !mb-0.5" for="resp-admi-dni">DNI *</label>
                        <input wire:model="respAdmiDni"
                               id="resp-admi-dni"
                               type="text"
                               inputmode="numeric"
                               maxlength="11"
                               class="form-input !py-1.5 text-sm w-full tabular-nums @error('respAdmiDni') ring-2 ring-red-400 @enderror"
                               placeholder="Solo números">
                        @error('respAdmiDni') <p class="se-field-error text-[11px]">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="shrink-0 flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50 px-4 py-2.5">
                <button type="button" wire:click="cerrarModalRespAdmi" class="btn-secondary btn-sm">Cancelar</button>
                <button type="button"
                        wire:click="guardarRespAdmi"
                        wire:loading.attr="disabled"
                        wire:target="guardarRespAdmi"
                        class="btn-primary btn-sm">
                    <span wire:loading.remove wire:target="guardarRespAdmi">Guardar</span>
                    <span wire:loading wire:target="guardarRespAdmi">Guardando…</span>
                </button>
            </div>
        </div>
    </div>
@endif
