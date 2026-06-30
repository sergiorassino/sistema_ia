<div class="se-card overflow-hidden">
    <div class="border-b border-accent-200 bg-white px-5 py-4">
        <p class="se-section-title">Mensaje</p>
    </div>
    <div class="space-y-6 p-5 sm:p-6">
        <div>
            <label for="asunto-em" class="form-label">Asunto</label>
            <input id="asunto-em" type="text" wire:model="asunto" maxlength="254" class="form-input mt-1">
            @error('asunto') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-se-html-editor wire-model="contenidoHtml" :value="$contenidoHtml" label="Cuerpo del correo (HTML)" min-height="16rem" />
            @error('contenidoHtml') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">Adjuntos (máx. {{ config('emails_masivos.adjuntos_max_count', 5) }}, nombre ≤ 30 caracteres)</label>
            <input type="file" wire:model="adjuntosArchivos" multiple class="mt-2 block w-full text-sm">
            @error('adjuntosArchivos') <p class="form-error">{{ $message }}</p> @enderror
            @if (! empty($adjuntosArchivos))
                <ul class="mt-2 space-y-1 text-sm text-neutral-700">
                    @foreach ($adjuntosArchivos as $i => $f)
                        <li class="flex items-center justify-between gap-2 rounded-lg bg-accent-50 px-3 py-1.5">
                            <span class="truncate">{{ $f->getClientOriginalName() }}</span>
                            <button type="button" wire:click="removeAdjunto({{ $i }})" class="text-xs font-semibold text-red-600">Quitar</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="flex justify-end">
            <button type="button" wire:click="irADestinatarios" class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                Siguiente: destinatarios
            </button>
        </div>
    </div>
</div>
