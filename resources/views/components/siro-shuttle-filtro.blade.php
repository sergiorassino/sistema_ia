@props([
    'titulo',
    'tipo',
    'habilitado' => false,
    'wireHabilitado' => '',
    'disponibles' => [],
    'seleccionados' => [],
    'marcadasIzqWire' => '',
    'marcadasDerWire' => '',
    'nota' => null,
])

<div class="se-siro-shuttle-row border-b border-accent-200 py-4 last:border-b-0">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start">
        <div class="flex shrink-0 items-start gap-2 lg:w-44">
            <label class="inline-flex items-center gap-2 pt-1">
                <input type="checkbox"
                       @if ($wireHabilitado !== '')
                           wire:model.live="{{ $wireHabilitado }}"
                       @endif
                       class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
            </label>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-neutral-800">{{ $titulo }}</p>
                @if ($nota)
                    <p class="mt-1 text-[11px] leading-snug text-neutral-500">{{ $nota }}</p>
                @endif
            </div>
        </div>

        <div class="min-w-0 flex-1">
            <div class="grid gap-2 sm:grid-cols-[1fr_auto_1fr] sm:items-stretch">
                <div>
                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Disponibles</p>
                    <select multiple
                            size="7"
                            wire:model="{{ $marcadasIzqWire }}"
                            class="se-siro-shuttle-list form-input h-40 w-full font-mono text-xs"
                            @disabled(!$habilitado)>
                        @foreach ($disponibles as $item)
                            <option value="{{ (int) $item['id'] }}">{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-row justify-center gap-1 sm:flex-col sm:justify-center">
                    <button type="button"
                            wire:click="moverSeleccion('{{ $tipo }}', 'agregar')"
                            class="se-siro-shuttle-btn"
                            title="Agregar seleccionados"
                            @disabled(!$habilitado)>
                        →
                    </button>
                    <button type="button"
                            wire:click="moverSeleccion('{{ $tipo }}', 'agregar-todos')"
                            class="se-siro-shuttle-btn"
                            title="Agregar todos"
                            @disabled(!$habilitado)>
                        ⇒
                    </button>
                    <button type="button"
                            wire:click="moverSeleccion('{{ $tipo }}', 'quitar')"
                            class="se-siro-shuttle-btn"
                            title="Quitar seleccionados"
                            @disabled(!$habilitado)>
                        ←
                    </button>
                    <button type="button"
                            wire:click="moverSeleccion('{{ $tipo }}', 'quitar-todos')"
                            class="se-siro-shuttle-btn"
                            title="Quitar todos"
                            @disabled(!$habilitado)>
                        ⇐
                    </button>
                </div>

                <div>
                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Seleccionados</p>
                    <select multiple
                            size="7"
                            wire:model="{{ $marcadasDerWire }}"
                            class="se-siro-shuttle-list form-input h-40 w-full bg-accent-50/60 font-mono text-xs"
                            @disabled(!$habilitado)>
                        @foreach ($seleccionados as $item)
                            <option value="{{ (int) $item['id'] }}">{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>
