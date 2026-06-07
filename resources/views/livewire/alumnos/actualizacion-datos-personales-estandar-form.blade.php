<div>
    <div class="se-page max-w-3xl mx-auto">
        <section class="se-hero">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Legajo del estudiante</p>
                    <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Actualización de Datos Personales</h2>
                </div>
            </div>
        </section>

        <section class="se-card mb-4 border-2 border-primary-300 bg-gradient-to-b from-primary-50 to-white p-4 shadow-sm sm:p-5"
                 aria-label="Datos del estudiante">
            <p class="text-center text-xs font-bold uppercase tracking-wider text-primary-800 mb-4">
                Datos del estudiante
            </p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="form-label text-primary-900">Apellido</label>
                    <input type="text"
                           value="{{ $apellido }}"
                           readonly
                           tabindex="-1"
                           class="form-input mt-1 border-primary-200 bg-white font-semibold text-neutral-900 shadow-inner"
                           aria-readonly="true">
                </div>
                <div>
                    <label class="form-label text-primary-900">Nombre</label>
                    <input type="text"
                           value="{{ $nombre }}"
                           readonly
                           tabindex="-1"
                           class="form-input mt-1 border-primary-200 bg-white font-semibold text-neutral-900 shadow-inner"
                           aria-readonly="true">
                </div>
                <div>
                    <label class="form-label text-primary-900">DNI</label>
                    <input type="text"
                           value="{{ $dni }}"
                           readonly
                           tabindex="-1"
                           class="form-input mt-1 border-primary-200 bg-white font-semibold text-neutral-900 shadow-inner"
                           aria-readonly="true">
                </div>
            </div>
        </section>

        @if (session('error'))
            <div class="se-soft-card mb-4 border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->isNotEmpty())
            <style>
                @foreach ($errors->keys() as $campoError)
                #campo-{{ $campoError }} { border-color: #ef4444 !important; box-shadow: 0 0 0 1px #fecaca; }
                @endforeach
            </style>
        @endif

        @if ($bloqueado)
            <div class="se-soft-card mb-4 border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                La actualización de datos no está habilitada para este legajo. Comuníquese con secretaría.
            </div>
        @endif

        <form wire:submit="guardar" novalidate class="@if($bloqueado) opacity-60 pointer-events-none @endif">
            <div class="se-card mb-4 overflow-hidden p-0">
                <div class="border-b border-accent-200 bg-accent-50 px-4 py-3 sm:px-5">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            @disabled($bloqueado)
                            class="mx-auto flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50">
                        <svg wire:loading.remove wire:target="guardar" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        <span wire:loading.remove wire:target="guardar">Guardar</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </div>

            <p class="mb-6 text-center text-sm leading-relaxed text-red-800">
                Se solicita completar <strong>todos los datos requeridos</strong>.
                Es fundamental que la información esté completa y actualizada para garantizar el correcto registro de cada estudiante.
            </p>
            <p class="mb-6 text-center text-xs font-semibold uppercase tracking-wide text-neutral-600">
                Si algún dato no corresponde, escriba un guión (-).
            </p>

            <div class="space-y-6">
                <section class="se-card p-4 sm:p-5" aria-labelledby="seccion-pad">
                    <p id="seccion-pad" class="se-section-title mb-4">Datos del padre</p>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="form-label" for="campo-nombrepad">Apellidos y nombres *</label>
                            <input id="campo-nombrepad" wire:model="nombrepad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                            @error('nombrepad') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="form-label" for="campo-dnipad">DNI *</label>
                                <input id="campo-dnipad" wire:model="dnipad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                                @error('dnipad') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="campo-fechnacpad">Fecha de nacimiento *</label>
                                <input id="campo-fechnacpad" wire:model="fechnacpad" type="date" class="form-input mt-1" @disabled($bloqueado)>
                                @error('fechnacpad') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="form-label" for="campo-nacionpad">Nacionalidad *</label>
                            <input id="campo-nacionpad" wire:model="nacionpad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                            @error('nacionpad') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label" for="campo-domipad">Domicilio (calle, nº y barrio) *</label>
                            <input id="campo-domipad" wire:model="domipad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                            @error('domipad') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="form-label" for="campo-telepad">Celular *</label>
                                <input id="campo-telepad" wire:model="telepad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                                @error('telepad') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="campo-emailpad">E-mail *</label>
                                <input id="campo-emailpad" wire:model="emailpad" type="text" inputmode="email" autocomplete="email" class="form-input mt-1" @disabled($bloqueado)>
                                @error('emailpad') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="form-label" for="campo-ocupacpad">Ocupación *</label>
                                <input id="campo-ocupacpad" wire:model="ocupacpad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                                @error('ocupacpad') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="campo-telltp">Teléfono laboral</label>
                                <input id="campo-telltp" wire:model="telltp" type="text" class="form-input mt-1" @disabled($bloqueado)>
                                @error('telltp') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </section>

                <section class="se-card p-4 sm:p-5" aria-labelledby="seccion-mad">
                    <p id="seccion-mad" class="se-section-title mb-4">Datos de la madre</p>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="form-label" for="campo-nombremad">Apellidos y nombres *</label>
                            <input id="campo-nombremad" wire:model="nombremad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                            @error('nombremad') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="form-label" for="campo-dnimad">DNI *</label>
                                <input id="campo-dnimad" wire:model="dnimad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                                @error('dnimad') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="campo-fechnacmad">Fecha de nacimiento *</label>
                                <input id="campo-fechnacmad" wire:model="fechnacmad" type="date" class="form-input mt-1" @disabled($bloqueado)>
                                @error('fechnacmad') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="form-label" for="campo-nacionmad">Nacionalidad *</label>
                            <input id="campo-nacionmad" wire:model="nacionmad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                            @error('nacionmad') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label" for="campo-domimad">Domicilio (calle, nº y barrio) *</label>
                            <input id="campo-domimad" wire:model="domimad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                            @error('domimad') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="form-label" for="campo-telemad">Celular *</label>
                                <input id="campo-telemad" wire:model="telemad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                                @error('telemad') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="campo-emailmad">E-mail *</label>
                                <input id="campo-emailmad" wire:model="emailmad" type="text" inputmode="email" autocomplete="email" class="form-input mt-1" @disabled($bloqueado)>
                                @error('emailmad') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="form-label" for="campo-ocupacmad">Ocupación *</label>
                                <input id="campo-ocupacmad" wire:model="ocupacmad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                                @error('ocupacmad') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="campo-telltm">Teléfono laboral</label>
                                <input id="campo-telltm" wire:model="telltm" type="text" class="form-input mt-1" @disabled($bloqueado)>
                                @error('telltm') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="sticky bottom-0 z-10 -mx-2 mt-6 border-t border-accent-200 bg-[#F4F8F9]/95 px-2 py-3 backdrop-blur-sm sm:static sm:border-0 sm:bg-transparent sm:p-0">
                <button type="submit"
                        wire:loading.attr="disabled"
                        @disabled($bloqueado)
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50">
                    <svg wire:loading.remove wire:target="guardar" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    <span wire:loading.remove wire:target="guardar">Guardar</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
            </div>
        </form>
    </div>

    @if ($mostrarAvisoCamposIncompletos && count($camposIncompletosAviso) > 0)
        @php
            $primerCampoIncompleto = $camposIncompletosAviso[0]['campo'] ?? null;
        @endphp
        @teleport('body')
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="alertdialog"
                 aria-modal="true"
                 aria-labelledby="aviso-campos-titulo"
                 wire:key="aviso-campos-estandar"
                 x-data
                 x-on:keydown.escape.window="$wire.cerrarAvisoCamposIncompletos()">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm"
                     wire:click="cerrarAvisoCamposIncompletos"
                     aria-hidden="true"></div>
                <div class="relative z-10 my-auto flex w-full max-w-md max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl border-2 border-amber-400 bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),40rem)]"
                     @click.stop
                     x-init="$nextTick(() => $el.querySelector('[data-modal-focus]')?.focus())">
                    <div class="min-h-0 flex-1 overflow-y-auto p-6 sm:p-8">
                        <div class="mx-auto mb-4 flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-amber-100">
                            <svg class="h-8 w-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </div>
                        <h3 id="aviso-campos-titulo" class="text-center text-lg font-bold text-neutral-900">
                            Campos incompletos
                        </h3>
                        <p class="mt-3 text-center text-sm leading-relaxed text-neutral-700">
                            Complete o corrija los siguientes campos antes de guardar.
                            Los datos que ya cargó <strong>se mantienen</strong>.
                        </p>
                        <ul class="mt-4 max-h-48 space-y-2 overflow-y-auto rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-neutral-800"
                            role="list">
                            @foreach ($camposIncompletosAviso as $item)
                                <li class="flex gap-2" role="listitem">
                                    <span class="mt-0.5 text-amber-600" aria-hidden="true">•</span>
                                    <span>{{ $item['etiqueta'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="shrink-0 border-t border-amber-100 px-6 pb-6 sm:px-8">
                        <button type="button"
                                data-modal-focus
                                wire:click="cerrarAvisoCamposIncompletos"
                                @if ($primerCampoIncompleto)
                                    x-on:click="document.getElementById('campo-{{ $primerCampoIncompleto }}')?.scrollIntoView({ behavior: 'smooth', block: 'center' })"
                                @endif
                                class="w-full rounded-xl bg-amber-500 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2">
                            Entendido
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    @script
    <script>
        (function () {
            function mostrarExito(mensaje) {
                if (typeof window.seSwalExito === 'function') {
                    window.seSwalExito(mensaje);
                }
            }

            $wire.on('se-swal-exito', (event) => {
                const mensaje = event?.mensaje ?? event?.detail?.mensaje ?? 'Datos guardados correctamente.';
                mostrarExito(mensaje);
            });

            @if (session('success'))
                mostrarExito(@js(session('success')));
            @endif
        })();
    </script>
    @endscript
</div>
