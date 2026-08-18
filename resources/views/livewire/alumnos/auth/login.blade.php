<div>
    <div class="card p-4 sm:p-5">
        <h2 class="text-base sm:text-lg font-semibold text-gray-800 mb-2.5 sm:mb-3">Acceso estudiantes</h2>

        @if (session('error'))
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        @if (session('se_swal_error'))
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (typeof window.seSwalError === 'function') {
                        window.seSwalError(
                            @js(session('se_swal_error')),
                            @js(session('se_swal_error_titulo', 'Acceso no disponible')),
                            { confirmButtonText: 'Volver al inicio de sesión' }
                        );
                    }
                });
            </script>
        @endif

        <noscript>
            <p class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                Se requiere JavaScript activo para iniciar sesión de forma segura.
            </p>
        </noscript>

        <form wire:submit.prevent="login"
              class="space-y-3"
              autocomplete="on">
            <div>
                <label class="form-label text-xs" for="dni">DNI (usuario)</label>
                <input wire:model="dni"
                       id="dni"
                       type="text"
                       inputmode="numeric"
                       maxlength="11"
                       autocomplete="username"
                       placeholder="Ej: 25038868"
                       x-on:input="$event.target.value = $event.target.value.replace(/\D/g, '').slice(0, 11)"
                       class="form-input text-sm py-2 @error('dni') border-red-400 @enderror">
                @error('dni')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div x-data="{ showPassword: false }">
                <label class="form-label text-xs" for="pwrd">Contraseña</label>
                <div class="relative">
                    <input wire:model="pwrd"
                           id="pwrd"
                           x-bind:type="showPassword ? 'text' : 'password'"
                           autocomplete="current-password"
                           class="form-input text-sm py-2 pl-2 pr-10 @error('pwrd') border-red-400 @enderror">
                    <button type="button"
                            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-md text-gray-500 transition-colors hover:text-primary-600 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                            @click="showPassword = !showPassword"
                            x-bind:aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                        <svg x-show="!showPassword" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 5 12 5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19 12 19c-4.638 0-8.573-3.007-9.963-7.178z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <svg x-show="showPassword" x-cloak class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19 12 19c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 5c4.756 0 8.773 2.662 10.065 7.022a10.525 10.525 0 01-4.162 5.411m0 0L21 21M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                        </svg>
                    </button>
                </div>
                @error('pwrd')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            @include('layouts.partials.login-olvidar-contrasena', ['variant' => 'alumno'])

            <div class="pt-1">
                <button type="submit"
                        class="btn-primary w-full py-2 text-sm"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-75"
                        wire:target="login">
                    <span wire:loading.remove wire:target="login">Ingresar</span>
                    <span wire:loading wire:target="login" class="flex items-center justify-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        Verificando…
                    </span>
                </button>
            </div>
        </form>

        @include('layouts.partials.login-autofill-sync', ['loginAutofillSugerirAcceso' => false])
        @include('layouts.partials.pwa-instalar-boton')
    </div>
</div>

