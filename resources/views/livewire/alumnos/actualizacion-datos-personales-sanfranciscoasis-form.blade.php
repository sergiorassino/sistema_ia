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

    {{-- Identificación del estudiante (solo lectura, como en el formulario modelo) --}}
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
        <div class="se-soft-card border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            La actualización de datos no está habilitada para este legajo. Comuníquese con secretaría.
        </div>
    @endif

    <p class="mb-4 text-center text-xs font-semibold uppercase tracking-wide text-red-700">
        (En aquellos casos donde no se deba consignar ningún dato, escribir un guión)
    </p>

    <form wire:submit.prevent="guardar" novalidate class="space-y-4 @if($bloqueado) opacity-60 pointer-events-none @endif">
        {{-- Aceptaciones --}}
        <section id="documentos-institucionales" class="se-card p-4 sm:p-5 space-y-4 scroll-mt-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-primary-700">Documentos institucionales</p>

            @foreach ($documentos as $clave => $doc)
                @php
                    $def = $doc['def'];
                    $aceptado = $doc['aceptado'];
                    $disponible = $doc['disponible'];
                @endphp
                <div class="rounded-2xl border border-accent-200 bg-white p-4" wire:key="acept-{{ $clave }}">
                    @if ($aceptado)
                        <p class="text-sm font-bold leading-snug text-blue-800">
                            @if ($clave === \App\Support\MatriculaWeb\MatriculaWebDocumentos::COMPROMISO)
                                <span class="uppercase">
                                    {{ \App\Support\MatriculaWeb\MatriculaWebDocumentos::etiquetaAceptado($clave) }}:
                                </span>
                                <span class="font-bold normal-case">{{ $textoCompromiso }}</span>
                            @else
                                <span class="uppercase">
                                    {{ \App\Support\MatriculaWeb\MatriculaWebDocumentos::etiquetaAceptado($clave) }}
                                </span>
                            @endif
                        </p>
                        <label class="mt-3 flex cursor-pointer items-center gap-2 text-xs text-neutral-600">
                            <input type="checkbox"
                                   class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                   wire:click="revocarAceptacion('{{ $clave }}')">
                            <span>(click para revocar la aceptación)</span>
                        </label>
                    @elseif ($disponible)
                        <a href="{{ route('alumnos.actualizacion-datos.aceptacion', ['tipo' => $clave]) }}"
                           class="flex items-start gap-3 text-sm hover:opacity-90">
                            <svg class="h-8 w-8 shrink-0 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            <span>
                                <span class="font-bold uppercase text-red-700">
                                    {{ \App\Support\MatriculaWeb\MatriculaWebDocumentos::etiquetaPendiente($clave) }}
                                </span>
                                <span class="mt-1 block text-xs font-normal normal-case text-neutral-600">
                                    (click para leer el documento y ACEPTAR)
                                </span>
                            </span>
                        </a>
                    @else
                        <p class="text-sm text-amber-800">
                            {{ $def['label'] }}: documento no disponible. Contacte a la institución.
                        </p>
                    @endif
                </div>
            @endforeach
        </section>

        {{-- Adulto responsable --}}
        <section class="se-card p-4 sm:p-5">
            <p class="se-section-title mb-4">Adulto responsable</p>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="form-label">Apellido y nombre (del adulto responsable) *</label>
                    <input id="campo-reglamApenom" wire:model="reglamApenom" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('reglamApenom') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">DNI *</label>
                    <input id="campo-reglamDni" wire:model="reglamDni" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('reglamDni') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">E-mail *</label>
                    <input id="campo-reglamEmail" wire:model="reglamEmail" type="text" inputmode="email" autocomplete="email" class="form-input mt-1" @disabled($bloqueado)>
                    @error('reglamEmail') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Estudiante --}}
        <section class="se-card p-4 sm:p-5">
            <p class="se-section-title mb-1">Datos del estudiante</p>
            <p class="text-xs text-neutral-500 mb-4">Campos con * son obligatorios</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Fecha de nacimiento *</label>
                    <input id="campo-fechnaci" wire:model="fechnaci" type="text" placeholder="dd/mm/aaaa" class="form-input mt-1" @disabled($bloqueado)>
                    @error('fechnaci') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Lugar de nac. (Depto/Partido) *</label>
                    <input id="campo-ln_depto" wire:model="ln_depto" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('ln_depto') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Provincia *</label>
                    <input id="campo-ln_provincia" wire:model="ln_provincia" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('ln_provincia') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">País *</label>
                    <input id="campo-ln_pais" wire:model="ln_pais" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('ln_pais') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Dirección (calle y nº) *</label>
                    <input id="campo-callenum" wire:model="callenum" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('callenum') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Barrio *</label>
                    <input id="campo-barrio" wire:model="barrio" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('barrio') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Localidad *</label>
                    <input id="campo-localidad" wire:model="localidad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('localidad') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">
                        Celular del estudiante @if($esSecundario)*@endif
                        @if($esSecundario)<span class="font-normal normal-case text-neutral-500">(solo secundario)</span>@endif
                    </label>
                    <input id="campo-telefono" wire:model="telefono" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('telefono') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Email institucional del estudiante *</label>
                    <input id="campo-email" wire:model="email" type="text" inputmode="email" autocomplete="email" class="form-input mt-1" @disabled($bloqueado)>
                    @error('email') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Escuela de origen</label>
                    <input id="campo-escori" wire:model="escori" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('escori') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Necesidades especiales *</label>
                    <select id="campo-needes" wire:model.live="needes" class="form-select mt-1" @disabled($bloqueado)>
                        <option value="">Seleccione</option>
                        <option value="no">No</option>
                        <option value="si">Sí</option>
                    </select>
                    @error('needes') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                @if ($needes === 'si')
                    <div class="sm:col-span-2">
                        <label class="form-label leading-snug">
                            Necesidades Especiales
                            <span class="mt-1 block text-xs font-normal normal-case text-neutral-600">
                                (consignar Centro o Profesional que lo acompaña y teléfono de contacto) *
                            </span>
                        </label>
                        <textarea id="campo-needes_detalle" wire:model="needes_detalle" rows="3" class="form-input mt-1 resize-y" @disabled($bloqueado)></textarea>
                        @error('needes_detalle') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>
        </section>

        {{-- Padre --}}
        <section class="se-card p-4 sm:p-5">
            <p class="se-section-title mb-4">Datos del padre</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="form-label">Apellidos y nombres *</label>
                    <input id="campo-nombrepad" wire:model="nombrepad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('nombrepad') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">DNI *</label>
                    <input id="campo-dnipad" wire:model="dnipad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('dnipad') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Celular *</label>
                    <input id="campo-telepad" wire:model="telepad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('telepad') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Email *</label>
                    <input id="campo-emailpad" wire:model="emailpad" type="text" inputmode="email" autocomplete="email" class="form-input mt-1" @disabled($bloqueado)>
                    @error('emailpad') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Ocupación *</label>
                    <input id="campo-ocupacpad" wire:model="ocupacpad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('ocupacpad') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Lugar de trabajo</label>
                    <input id="campo-lugtrapad" wire:model="lugtrapad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                </div>
                <div>
                    <label class="form-label">Teléfono laboral</label>
                    <input id="campo-telltp" wire:model="telltp" type="text" class="form-input mt-1" @disabled($bloqueado)>
                </div>
            </div>
        </section>

        {{-- Madre --}}
        <section class="se-card p-4 sm:p-5">
            <p class="se-section-title mb-4">Datos de la madre</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="form-label">Apellidos y nombres *</label>
                    <input id="campo-nombremad" wire:model="nombremad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('nombremad') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">DNI *</label>
                    <input id="campo-dnimad" wire:model="dnimad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('dnimad') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Celular *</label>
                    <input id="campo-telemad" wire:model="telemad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('telemad') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Email *</label>
                    <input id="campo-emailmad" wire:model="emailmad" type="text" inputmode="email" autocomplete="email" class="form-input mt-1" @disabled($bloqueado)>
                    @error('emailmad') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Ocupación *</label>
                    <input id="campo-ocupacmad" wire:model="ocupacmad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('ocupacmad') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Lugar de trabajo</label>
                    <input id="campo-lugtramad" wire:model="lugtramad" type="text" class="form-input mt-1" @disabled($bloqueado)>
                </div>
                <div>
                    <label class="form-label">Teléfono laboral</label>
                    <input id="campo-telltm" wire:model="telltm" type="text" class="form-input mt-1" @disabled($bloqueado)>
                </div>
            </div>
        </section>

        {{-- Tutor --}}
        <section class="se-card p-4 sm:p-5">
            <p class="se-section-title mb-4">Tutor legal (acreditado con oficio judicial)</p>
            <p class="text-xs text-neutral-500 mb-3">No repetir datos de los padres. Si no hay tutor, deje vacío o guión.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="form-label">Tutor legal</label>
                    <input id="campo-nombretut" wire:model="nombretut" type="text" class="form-input mt-1" @disabled($bloqueado)>
                </div>
                <div>
                    <label class="form-label">DNI</label>
                    <input id="campo-dnitut" wire:model="dnitut" type="text" class="form-input mt-1" @disabled($bloqueado)>
                </div>
                <div>
                    <label class="form-label">Teléfono</label>
                    <input id="campo-teletut" wire:model="teletut" type="text" class="form-input mt-1" @disabled($bloqueado)>
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input id="campo-emailtut" wire:model="emailtut" type="text" inputmode="email" autocomplete="email" class="form-input mt-1" @disabled($bloqueado)>
                    @error('emailtut') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Lugar de trabajo</label>
                    <input id="campo-lugtratut" wire:model="lugtratut" type="text" class="form-input mt-1" @disabled($bloqueado)>
                </div>
                <div>
                    <label class="form-label">Teléfono laboral</label>
                    <input id="campo-telltt" wire:model="telltt" type="text" class="form-input mt-1" @disabled($bloqueado)>
                </div>
            </div>
        </section>

        {{-- Adicionales --}}
        <section class="se-card p-4 sm:p-5">
            <p class="se-section-title mb-4">Adicionales</p>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="form-label">Estado civil de los padres *</label>
                    <input id="campo-ec_padres" wire:model="ec_padres" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('ec_padres') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Vive con *</label>
                    <input id="campo-vivecon" wire:model="vivecon" type="text" class="form-input mt-1" @disabled($bloqueado)>
                    @error('vivecon') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-xl border border-accent-200 bg-accent-50/60 p-4 space-y-4">
                    <p class="text-sm font-bold leading-snug text-neutral-900">
                        En caso de imposibilidad de contactar a padres/tutor
                    </p>
                    <div>
                        <label class="form-label">Apellido y Nombres, relación y teléfono (1) *</label>
                        <input id="campo-contacto1" wire:model="contacto1" type="text" class="form-input mt-1 bg-white" @disabled($bloqueado)>
                        @error('contacto1') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Apellido y Nombres, relación y teléfono (2)</label>
                        <input id="campo-contacto2" wire:model="contacto2" type="text" class="form-input mt-1 bg-white" @disabled($bloqueado)>
                        @error('contacto2') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Apellido y Nombres, relación y teléfono (3)</label>
                        <input id="campo-contacto3" wire:model="contacto3" type="text" class="form-input mt-1 bg-white" @disabled($bloqueado)>
                        @error('contacto3') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="form-label">Personas autorizadas para el retiro del estudiante</label>
                    <input id="campo-retira1" wire:model="retira1" type="text" class="form-input mt-1" placeholder="Apellido, nombre, relación, teléfono" @disabled($bloqueado)>
                    @error('retira1') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Observaciones</label>
                    <textarea id="campo-obs_web" wire:model="obs_web" rows="3" class="form-input mt-1 resize-y" @disabled($bloqueado)></textarea>
                </div>
            </div>
        </section>

        <div class="sticky bottom-0 z-10 -mx-2 border-t border-accent-200 bg-[#F4F8F9]/95 px-2 py-3 backdrop-blur-sm sm:static sm:border-0 sm:bg-transparent sm:p-0">
            <button type="submit"
                    wire:loading.attr="disabled"
                    @disabled($bloqueado)
                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50">
                <svg wire:loading.remove wire:target="guardar" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                 wire:key="aviso-campos-sfa"
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

    @if ($mostrarAvisoDocumentosPendientes)
        @teleport('body')
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="alertdialog"
                 aria-modal="true"
                 aria-labelledby="aviso-documentos-titulo"
                 wire:key="aviso-documentos-sfa"
                 x-data
                 x-on:keydown.escape.window="$wire.cerrarAvisoDocumentosPendientes()">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm"
                     wire:click="cerrarAvisoDocumentosPendientes"
                     aria-hidden="true"></div>
                <div class="relative z-10 my-auto w-full max-w-md rounded-2xl border-2 border-amber-400 bg-white p-6 shadow-xl ring-1 ring-black/5 sm:p-8"
                     @click.stop
                     x-init="$nextTick(() => $el.querySelector('[data-modal-focus]')?.focus())">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100">
                        <svg class="h-8 w-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </div>
                    <h3 id="aviso-documentos-titulo" class="text-center text-lg font-bold text-neutral-900">
                        Documentos pendientes
                    </h3>
                    <p class="mt-3 text-center text-sm leading-relaxed text-neutral-700">
                        Debe aceptar los <strong>cuatro documentos institucionales</strong> antes de guardar.
                        Los datos que cargó en el formulario se mantienen; solo falta completar las aceptaciones.
                    </p>
                    <button type="button"
                            data-modal-focus
                            wire:click="cerrarAvisoDocumentosPendientes"
                            x-on:click="document.getElementById('documentos-institucionales')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                            class="mt-6 w-full rounded-xl bg-amber-500 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2">
                        Entendido
                    </button>
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
