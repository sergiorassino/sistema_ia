<div>
    <div class="se-auth-card p-6 sm:p-8">
        <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-neutral-800">Iniciar sesión</h2>
        <p class="mt-1.5 text-sm text-neutral-600">Ingrese sus datos y seleccione nivel y año lectivo.</p>

        @if (session('error'))
            <div class="mt-5 p-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        <noscript>
            <p class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                Se requiere JavaScript activo para iniciar sesión de forma segura.
            </p>
        </noscript>

        <form wire:submit.prevent="login"
              class="mt-6 space-y-4"
              autocomplete="on">
            {{-- DNI: sin name= para que un envío HTML accidental no lleve datos en la URL --}}
            <div>
                <label class="se-auth-label" for="dni">DNI (usuario)</label>
                <input wire:model.live.debounce.400ms="dni"
                       id="dni"
                       type="text"
                       inputmode="numeric"
                       maxlength="11"
                       autocomplete="username"
                       placeholder="Ej: 25038868"
                       x-on:input="$event.target.value = $event.target.value.replace(/\D/g, '').slice(0, 11)"
                       class="se-auth-input py-2.5 px-3 @error('dni') !border-red-400 ring-2 ring-red-200/80 @enderror">
                @error('dni')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Contraseña --}}
            <div x-data="{ showPassword: false }">
                <label class="se-auth-label" for="pwrd">Contraseña</label>
                <div class="relative">
                    <input wire:model="pwrd"
                           id="pwrd"
                           x-bind:type="showPassword ? 'text' : 'password'"
                           autocomplete="current-password"
                           class="se-auth-input py-2.5 pl-3 pr-11 @error('pwrd') !border-red-400 ring-2 ring-red-200/80 @enderror">
                    <button type="button"
                            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-neutral-500 transition-colors hover:text-primary-600 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                            @click="showPassword = !showPassword"
                            x-bind:aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                        <svg x-show="!showPassword" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 5 12 5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19 12 19c-4.638 0-8.573-3.007-9.963-7.178z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <svg x-show="showPassword" x-cloak class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19 12 19c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 5c4.756 0 8.773 2.662 10.065 7.022a10.525 10.525 0 01-4.162 5.411m0 0L21 21M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                        </svg>
                    </button>
                </div>
                @error('pwrd')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Nivel --}}
            <div>
                <label class="se-auth-label" for="idNivel">Nivel</label>
                <select wire:model="idNivel"
                        id="idNivel"
                        class="se-auth-select py-2.5 px-3 @error('idNivel') !border-red-400 ring-2 ring-red-200/80 @enderror">
                    <option value="">— Seleccione nivel —</option>
                    @foreach ($niveles as $n)
                        <option value="{{ $n->id }}">{{ $n->nivel }}</option>
                    @endforeach
                </select>
                @error('idNivel')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Año lectivo (componente aislado: evita que Livewire desordene las opciones al actualizar el DNI) --}}
            <div>
                <label class="se-auth-label" for="idTerlec">Año lectivo</label>
                @php
                    $terlecSelectClass = 'se-auth-select py-2.5 px-3'
                        .($errors->has('idTerlec') ? ' !border-red-400 ring-2 ring-red-200/80' : '');
                @endphp
                <livewire:components.terlec-selector
                    wire:model="idTerlec"
                    input-id="idTerlec"
                    :select-class="$terlecSelectClass"
                    :key="'login-terlec-selector'" />
                @error('idTerlec')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-1">
                <button type="submit"
                        class="se-auth-btn"
                        wire:loading.attr="disabled"
                        wire:target="login">
                    <span wire:loading.remove wire:target="login">Ingresar al sistema</span>
                    <span wire:loading wire:target="login" class="flex items-center justify-center gap-2">
                        <svg class="animate-spin h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        Verificando…
                    </span>
                </button>
            </div>
        </form>

        @include('layouts.partials.login-autofill-sync')
    </div>

    @if ($mensajeBloqueoDocenteTerlec)
        <div class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-neutral-900/50 backdrop-blur-sm"
             role="alertdialog"
             aria-modal="true"
             aria-labelledby="login-bloqueo-docente-titulo">
            <div class="w-full max-w-md rounded-2xl border border-accent-200 bg-white p-6 sm:p-8 shadow-xl text-center">
                <p id="login-bloqueo-docente-titulo"
                   class="text-base sm:text-lg font-semibold leading-relaxed text-neutral-800">
                    {{ $mensajeBloqueoDocenteTerlec }}
                </p>
                <button type="button"
                        wire:click="cerrarMensajeBloqueoDocenteTerlec"
                        class="se-auth-btn mt-6 min-w-[8rem]">
                    Entendido
                </button>
            </div>
        </div>
    @endif
</div>
