<div>
    <button type="button"
            wire:click="abrir"
            title="Cambiar contraseña"
            class="text-white/85 hover:text-white transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
        </svg>
    </button>

    @if ($abierto)
        @teleport('body')
            <div class="fixed inset-0 z-[1100] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="cambiar-pwrd-titulo">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrar"></div>

                <div class="relative z-10 my-auto flex w-full max-w-md max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),32rem)]">
                    <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                        <h2 id="cambiar-pwrd-titulo" class="text-lg font-semibold text-neutral-800">
                            Cambiar contraseña
                        </h2>
                        <p class="mt-1 text-sm text-neutral-600">
                            Ingrese la nueva contraseña dos veces.
                        </p>
                    </div>

                    <form wire:submit.prevent="guardar"
                          class="flex min-h-0 flex-1 flex-col"
                          autocomplete="off"
                          x-data="{ showNueva: false, showConfirmacion: false }">
                        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                            <div>
                                <label class="se-auth-label" for="pwrd-nueva">Nueva contraseña</label>
                                <div class="relative">
                                    <input wire:model="pwrdNueva"
                                           id="pwrd-nueva"
                                           x-bind:type="showNueva ? 'text' : 'password'"
                                           autocomplete="new-password"
                                           maxlength="50"
                                           class="se-auth-input py-2.5 pl-3 pr-11 @error('pwrdNueva') !border-red-400 ring-2 ring-red-200/80 @enderror">
                                    <button type="button"
                                            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-neutral-500 transition-colors hover:text-primary-600 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                            @click="showNueva = !showNueva"
                                            x-bind:aria-label="showNueva ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                                        <svg x-show="!showNueva" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 5 12 5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19 12 19c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <svg x-show="showNueva" x-cloak class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19 12 19c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 5c4.756 0 8.773 2.662 10.065 7.022a10.525 10.525 0 01-4.162 5.411m0 0L21 21M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                        </svg>
                                    </button>
                                </div>
                                @error('pwrdNueva')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="se-auth-label" for="pwrd-confirmacion">Confirmar contraseña</label>
                                <div class="relative">
                                    <input wire:model="pwrdConfirmacion"
                                           id="pwrd-confirmacion"
                                           x-bind:type="showConfirmacion ? 'text' : 'password'"
                                           autocomplete="new-password"
                                           maxlength="50"
                                           class="se-auth-input py-2.5 pl-3 pr-11 @error('pwrdConfirmacion') !border-red-400 ring-2 ring-red-200/80 @enderror">
                                    <button type="button"
                                            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-neutral-500 transition-colors hover:text-primary-600 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                            @click="showConfirmacion = !showConfirmacion"
                                            x-bind:aria-label="showConfirmacion ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                                        <svg x-show="!showConfirmacion" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 5 12 5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19 12 19c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <svg x-show="showConfirmacion" x-cloak class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19 12 19c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 5c4.756 0 8.773 2.662 10.065 7.022a10.525 10.525 0 01-4.162 5.411m0 0L21 21M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                        </svg>
                                    </button>
                                </div>
                                @error('pwrdConfirmacion')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50 px-5 py-4">
                            <button type="button"
                                    wire:click="cerrar"
                                    class="rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-50">
                                Cancelar
                            </button>
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="guardar"
                                    class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60">
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje }) => window.seSwalExito(mensaje));
        $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
    </script>
    @endscript
</div>
