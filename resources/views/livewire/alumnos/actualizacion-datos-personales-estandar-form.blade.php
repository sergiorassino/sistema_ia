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

        @if ($bloqueado)
            @include('livewire.alumnos.partials.bloqueo-ficha-y-datos')
        @else
        @include('livewire.alumnos.partials.foto-carnet-actualizacion')

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

        <form wire:submit="guardar" novalidate>
            <div class="se-card mb-4 overflow-hidden p-0">
                <div class="border-b border-accent-200 bg-accent-50 px-4 py-3 sm:px-5">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="guardar,fotoCarnetUpload"
                            @disabled($bloqueado)
                            class="mx-auto flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50">
                        <svg wire:loading.remove wire:target="guardar,fotoCarnetUpload" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        <span wire:loading.remove wire:target="guardar,fotoCarnetUpload">Guardar</span>
                        <span wire:loading wire:target="guardar,fotoCarnetUpload">Guardando…</span>
                    </button>
                </div>
            </div>

            <p class="mb-6 text-center text-sm leading-relaxed text-red-800">
                Se solicita completar <strong>todos los datos requeridos</strong>.
                Es fundamental que la información esté completa y actualizada para garantizar el correcto registro de cada estudiante.
            </p>
            <p class="mb-6 text-center text-xs font-semibold uppercase tracking-wide text-neutral-600">
                Si algún dato no corresponde, escriba un guión (-).
                En fechas de nacimiento, déjelas en blanco.
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
                                <label class="form-label" for="campo-fechnacpad">Fecha de nacimiento</label>
                                <input id="campo-fechnacpad" wire:model="fechnacpad" type="date" class="form-input mt-1" @disabled($bloqueado)>
                                <p class="mt-1 text-[11px] text-neutral-500">Si no corresponde, déjela en blanco.</p>
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
                                <label class="form-label" for="campo-fechnacmad">Fecha de nacimiento</label>
                                <input id="campo-fechnacmad" wire:model="fechnacmad" type="date" class="form-input mt-1" @disabled($bloqueado)>
                                <p class="mt-1 text-[11px] text-neutral-500">Si no corresponde, déjela en blanco.</p>
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

                <section class="se-card p-4 sm:p-5" aria-labelledby="seccion-tut">
                    <p id="seccion-tut" class="se-section-title mb-4">Datos del tutor</p>
                    <p class="mb-4 text-xs text-neutral-500">
                        Si el estudiante no tiene tutor legal, complete cada campo con un guión (-).
                    </p>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="form-label" for="campo-nombretut">Apellidos y nombres *</label>
                            <input id="campo-nombretut" wire:model="nombretut" type="text" class="form-input mt-1" @disabled($bloqueado)>
                            @error('nombretut') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="form-label" for="campo-dnitut">DNI *</label>
                                <input id="campo-dnitut" wire:model="dnitut" type="text" class="form-input mt-1" @disabled($bloqueado)>
                                @error('dnitut') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="campo-teletut">Celular *</label>
                                <input id="campo-teletut" wire:model="teletut" type="text" class="form-input mt-1" @disabled($bloqueado)>
                                @error('teletut') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="form-label" for="campo-emailtut">E-mail *</label>
                                <input id="campo-emailtut" wire:model="emailtut" type="text" inputmode="email" autocomplete="email" class="form-input mt-1" @disabled($bloqueado)>
                                @error('emailtut') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="campo-ocupactut">Ocupación *</label>
                                <input id="campo-ocupactut" wire:model="ocupactut" type="text" class="form-input mt-1" @disabled($bloqueado)>
                                @error('ocupactut') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </section>

                @if ($documentosEstudianteHabilitados && count($tiposDocumentoEstudiante) > 0)
                    <section class="se-card p-4 sm:p-5" aria-labelledby="seccion-documentos-estudiante">
                        <p id="seccion-documentos-estudiante" class="se-section-title mb-2">Documentación del estudiante</p>
                        <p class="mb-4 text-xs leading-relaxed text-neutral-600">
                            Suba fotos (JPG) o archivos PDF en cada casillero.
                            Al presionar <strong>Subir archivos</strong>, el sistema los unifica en un único PDF por documento.
                        </p>

                        <div class="space-y-5">
                            @foreach ($tiposDocumentoEstudiante as $tipo)
                                @php
                                    $clave = $tipo['clave'];
                                    $estado = $estadoDocumentos[$clave] ?? ['existe' => false, 'actualizado_en' => null, 'nombre' => ''];
                                    $maxArchivos = (int) $tipo['max_archivos'];
                                @endphp
                                <div class="rounded-xl border border-accent-200 bg-accent-50/40 p-4"
                                     id="campo-archivosDocumento-{{ $clave }}">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-neutral-900">
                                                {{ $tipo['label'] }}
                                                @if ($tipo['obligatorio'])
                                                    <span class="text-red-600">*</span>
                                                @endif
                                            </p>
                                            <p class="mt-1 text-[11px] text-neutral-500">
                                                {{ $maxArchivos === 1 ? '1 archivo' : $maxArchivos.' archivos' }} —
                                                {{ strtoupper(implode(', ', $tipo['extensiones'])) }}
                                                · máx. {{ $tipo['max_mb'] }} MB c/u
                                            </p>
                                            @if (! empty($tipo['explicacion']))
                                                <p class="mt-2 rounded-lg border border-accent-200 bg-white/80 px-3 py-2 text-sm leading-relaxed text-neutral-700">
                                                    {{ $tipo['explicacion'] }}
                                                </p>
                                            @endif
                                            @if ($estado['existe'])
                                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                                    <p class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200">
                                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                        Documento recibido
                                                        @if ($estado['actualizado_en'])
                                                            · {{ $estado['actualizado_en'] }}
                                                        @endif
                                                    </p>
                                                    @if (! empty($estado['url_ver']))
                                                        <a href="{{ $estado['url_ver'] }}"
                                                           target="_blank"
                                                           rel="noopener noreferrer"
                                                           class="inline-flex items-center gap-1.5 rounded-lg border border-primary-200 bg-white px-2.5 py-1 text-xs font-semibold text-primary-700 shadow-sm hover:bg-primary-50">
                                                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                            </svg>
                                                            Ver PDF subido
                                                        </a>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mt-3 space-y-3"
                                         wire:key="doc-slots-{{ $clave }}-{{ $revisionInputsDocumento[$clave] ?? 0 }}">
                                        @for ($i = 0; $i < $maxArchivos; $i++)
                                            @php
                                                $inputId = 'doc-estudiante-'.$clave.'-'.$i.'-'.($revisionInputsDocumento[$clave] ?? 0);
                                                $etiquetaArchivo = $maxArchivos === 1
                                                    ? 'Archivo'
                                                    : 'Archivo '.($i + 1);
                                            @endphp
                                            <div wire:key="doc-input-{{ $clave }}-{{ $i }}-{{ $revisionInputsDocumento[$clave] ?? 0 }}">
                                                <label class="form-label" for="{{ $inputId }}">{{ $etiquetaArchivo }}</label>
                                                <input id="{{ $inputId }}"
                                                       type="file"
                                                       wire:model="archivosDocumento.{{ $clave }}.{{ $i }}"
                                                       accept="{{ \App\Support\Alumnos\DocumentosEstudianteAutogestion::acceptAttribute($tipo['extensiones']) }}"
                                                       @disabled($bloqueado)
                                                       class="mt-1 block w-full text-sm text-neutral-700 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-700">
                                                <div wire:loading wire:target="archivosDocumento.{{ $clave }}.{{ $i }}" class="mt-1 text-xs text-neutral-500">
                                                    Subiendo archivo…
                                                </div>
                                                @error('archivosDocumento.'.$clave.'.'.$i)
                                                    <p class="form-error">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        @endfor
                                    </div>

                                    <div class="mt-4 flex flex-wrap justify-end gap-2">
                                        @if ($estado['existe'])
                                            <button type="button"
                                                    x-on:click="window.seSwalConfirmar(@js('Se eliminará el PDF completo de este documento. Si es obligatorio, deberá volver a subirlo.'), @js('Eliminar documento'), { confirmButtonText: 'Sí, eliminar' }).then(ok => ok && $wire.eliminarDocumento(@js($clave)))"
                                                    wire:loading.attr="disabled"
                                                    wire:target="eliminarDocumento"
                                                    @disabled($bloqueado)
                                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 shadow-sm hover:bg-red-50 disabled:opacity-50">
                                                <span wire:loading.remove wire:target="eliminarDocumento">Eliminar PDF subido</span>
                                                <span wire:loading wire:target="eliminarDocumento">Eliminando…</span>
                                            </button>
                                        @endif
                                        @php
                                            $uploadTargets = ['subirDocumento'];
                                            for ($u = 0; $u < $maxArchivos; $u++) {
                                                $uploadTargets[] = 'archivosDocumento.'.$clave.'.'.$u;
                                            }
                                        @endphp
                                        <button type="button"
                                                wire:click="subirDocumento('{{ $clave }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="{{ implode(', ', $uploadTargets) }}"
                                                @disabled($bloqueado)
                                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-primary-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm hover:bg-primary-50 disabled:opacity-50">
                                            <span wire:loading.remove wire:target="subirDocumento">Subir archivos</span>
                                            <span wire:loading wire:target="subirDocumento">Procesando…</span>
                                        </button>
                                    </div>
                                    @error('archivosDocumento.'.$clave)
                                        <p class="form-error mt-2">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
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
        @endif
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
