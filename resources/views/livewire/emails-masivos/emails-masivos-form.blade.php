<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0">
                <p class="se-eyebrow">Correo masivo</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $titulo }}</h2>
            </div>
            <a href="{{ route('emails-masivos.index') }}" class="rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white">Volver</a>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="space-y-6 p-5 sm:p-6">
            <div>
                <label for="asunto-em" class="form-label">Asunto</label>
                <input id="asunto-em" type="text" wire:model="asunto" maxlength="254" class="form-input mt-1">
                @error('asunto') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-se-html-editor wire-model="contenidoHtml" :value="$contenidoHtml" label="Cuerpo del correo (HTML)" min-height="18rem" />
                @error('contenidoHtml') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Adjuntos</label>
                <p class="-mt-1 mb-3 text-xs text-neutral-500">
                    Nombre ≤ 30 caracteres · hasta {{ (int) config('emails_masivos.adjuntos_max_count', 5) }} archivos · máx. {{ (int) config('emails_masivos.adjunto_max_mb', 10) }}&nbsp;MB c/u
                </p>

                @if (! empty($adjuntosExistentes) || ! empty($adjuntosNuevos))
                    <ul class="space-y-1.5">
                        @foreach ($adjuntosExistentes as $i => $nombre)
                            <li wire:key="adj-ex-{{ $i }}-{{ $nombre }}"
                                class="flex items-center justify-between gap-3 rounded-xl border border-accent-200 bg-accent-50/70 px-3 py-2 text-sm">
                                <span class="min-w-0 truncate">{{ $nombre }}</span>
                                <button type="button"
                                        wire:click="quitarAdjuntoExistente({{ $i }})"
                                        class="shrink-0 text-xs font-semibold text-red-600 hover:text-red-700">
                                    Quitar
                                </button>
                            </li>
                        @endforeach
                        @foreach ($adjuntosNuevos as $i => $f)
                            <li wire:key="adj-nuevo-{{ $i }}-{{ $f->getFilename() }}"
                                class="flex items-center justify-between gap-3 rounded-xl border border-primary-200 bg-primary-50/50 px-3 py-2 text-sm">
                                <span class="min-w-0 truncate">{{ $f->getClientOriginalName() }}</span>
                                <button type="button"
                                        wire:click="quitarAdjuntoNuevo({{ $i }})"
                                        class="shrink-0 text-xs font-semibold text-red-600 hover:text-red-700">
                                    Quitar
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="{{ ! empty($adjuntosExistentes) || ! empty($adjuntosNuevos) ? 'mt-3' : '' }}">
                    <label class="btn-primary inline-flex cursor-pointer items-center gap-2 py-2.5">
                        <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32a4.5 4.5 0 0 1-6.364-6.364l7.693-7.693" />
                        </svg>
                        Elegir archivos
                        <input type="file" wire:model="adjuntosNuevos" multiple class="sr-only">
                    </label>
                    <p wire:loading wire:target="adjuntosNuevos" class="mt-2 text-xs font-medium text-primary-700">
                        Subiendo archivos…
                    </p>
                </div>
                @error('adjuntosNuevos') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-accent-100 pt-4">
                <a href="{{ route('emails-masivos.index') }}" class="rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-neutral-700">Cancelar</a>
                <button type="button" wire:click="guardar" wire:loading.attr="disabled" class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                    <span wire:loading.remove wire:target="guardar">Guardar</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
            </div>
        </div>
    </div>

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje }) => window.seSwalExito(mensaje));
    </script>
    @endscript
</div>
