@if ($modalPagadorAbierto)
    <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
         role="dialog"
         aria-modal="true"
         aria-labelledby="modal-pagador-titulo">
        <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalPagador"></div>

        <div class="relative z-10 my-auto flex w-full max-w-2xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),44rem)]">
            <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                <p id="modal-pagador-titulo" class="text-lg font-bold text-neutral-800">Señor / pagador</p>
                <p class="mt-1 text-sm text-neutral-600">
                    Revise o complete los datos de padre, madre y tutor. Elija quién figura como pagador del recibo.
                </p>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-5">
                @foreach (['padre' => 'Padre', 'madre' => 'Madre', 'tutor' => 'Tutor'] as $vinculo => $etiqueta)
                    <fieldset class="rounded-2xl border border-accent-200 bg-accent-50/40 p-4 space-y-3">
                        <legend class="flex items-center gap-3 px-1">
                            <label class="inline-flex cursor-pointer items-center gap-2">
                                <input type="radio"
                                       wire:model="pagadorVinculo"
                                       value="{{ $vinculo }}"
                                       class="h-4 w-4 border-accent-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-semibold uppercase tracking-wide text-neutral-700">{{ $etiqueta }}</span>
                            </label>
                            @if ($pagadorVinculo === $vinculo)
                                <span class="se-pill text-[10px]">Pagador del recibo</span>
                            @endif
                        </legend>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="se-label">Apellido</label>
                                <input type="text"
                                       wire:model="pagadorResponsables.{{ $vinculo }}.apellido"
                                       maxlength="80"
                                       class="se-input w-full @error('pagadorResponsables.'.$vinculo.'.apellido') ring-2 ring-red-400 @enderror">
                                @error('pagadorResponsables.'.$vinculo.'.apellido') <p class="se-field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="se-label">Nombre</label>
                                <input type="text"
                                       wire:model="pagadorResponsables.{{ $vinculo }}.nombre"
                                       maxlength="80"
                                       class="se-input w-full @error('pagadorResponsables.'.$vinculo.'.nombre') ring-2 ring-red-400 @enderror">
                                @error('pagadorResponsables.'.$vinculo.'.nombre') <p class="se-field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="se-label">DNI</label>
                                <input type="text"
                                       wire:model="pagadorResponsables.{{ $vinculo }}.dni"
                                       maxlength="20"
                                       class="se-input w-full @error('pagadorResponsables.'.$vinculo.'.dni') ring-2 ring-red-400 @enderror">
                                @error('pagadorResponsables.'.$vinculo.'.dni') <p class="se-field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="se-label">Email</label>
                                <input type="email"
                                       wire:model="pagadorResponsables.{{ $vinculo }}.email"
                                       maxlength="120"
                                       placeholder="Para envío del recibo"
                                       class="se-input w-full @error('pagadorResponsables.'.$vinculo.'.email') ring-2 ring-red-400 @enderror">
                                @error('pagadorResponsables.'.$vinculo.'.email') <p class="se-field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </fieldset>
                @endforeach

                @error('pagadorVinculo') <p class="se-field-error">{{ $message }}</p> @enderror
            </div>

            <div class="shrink-0 flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50 px-5 py-4">
                <button type="button" wire:click="cerrarModalPagador" class="btn-secondary">Cancelar</button>
                <button type="button"
                        wire:click="guardarModalPagador"
                        wire:loading.attr="disabled"
                        wire:target="guardarModalPagador"
                        class="btn-primary">
                    <span wire:loading.remove wire:target="guardarModalPagador">Guardar y cerrar</span>
                    <span wire:loading wire:target="guardarModalPagador">Guardando…</span>
                </button>
            </div>
        </div>
    </div>
@endif
