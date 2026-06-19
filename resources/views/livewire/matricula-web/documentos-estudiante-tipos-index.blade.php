<div class="se-page !max-w-none min-w-0">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="se-soft-card mb-4 flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Matrícula web</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Documentos a subir (familia)</h2>
                <p class="text-sm text-white/80 max-w-2xl">
                    Define qué documentación pedir en el formulario de actualización de datos del portal familia.
                    Solo aparecen los tipos <strong class="text-white">activos</strong>.
                    Si no hay ninguno activo, la sección de documentación no se muestra.
                </p>
            </div>
            <button type="button" wire:click="openCreate"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                + Nuevo documento
            </button>
        </div>
    </section>

    <div class="w-full min-w-0 overflow-x-auto">
        <div class="gf gf-doc-estudiante-tipos">
            <div class="gf-head">
                <div class="gf-th gf-th-doc-clave">Clave</div>
                <div class="gf-th gf-th-doc-etiqueta">Etiqueta</div>
                <div class="gf-th gf-th-doc-explicacion">Explicación</div>
                <div class="gf-th gf-th-doc-ext">Extensiones</div>
                <div class="gf-th gf-th-doc-arch">Arch.</div>
                <div class="gf-th gf-th-doc-mb">MB</div>
                <div class="gf-th gf-th-doc-oblig">Oblig.</div>
                <div class="gf-th gf-th-doc-activo">Activo</div>
                <div class="gf-th-right gf-th-doc-acciones">Acciones</div>
            </div>

            @forelse ($tipos as $tipo)
                @php
                    $explicacion = trim((string) ($tipo->explicacion ?? ''));
                @endphp
                <div class="gf-row gf-row-hover @if(!$tipo->activo) opacity-60 @endif" wire:key="doc-tipo-{{ $tipo->id }}">
                    <div class="gf-td gf-td-doc-clave font-mono text-xs" title="{{ $tipo->clave }}">{{ $tipo->clave }}</div>
                    <div class="gf-td gf-td-doc-etiqueta font-medium" title="{{ $tipo->etiqueta }}">{{ $tipo->etiqueta }}</div>
                    <div class="gf-td gf-td-doc-explicacion text-xs text-neutral-600"
                         title="{{ $explicacion !== '' ? $explicacion : 'Sin indicaciones' }}">
                        {{ $explicacion !== '' ? $explicacion : '—' }}
                    </div>
                    <div class="gf-td gf-td-doc-ext text-xs uppercase" title="{{ implode(', ', $tipo->extensionesNormalizadas()) }}">{{ implode(', ', $tipo->extensionesNormalizadas()) }}</div>
                    <div class="gf-td gf-td-doc-arch tabular-nums">{{ (int) $tipo->max_archivos }}</div>
                    <div class="gf-td gf-td-doc-mb tabular-nums">{{ $tipo->maxMbEfectivo() }}</div>
                    <div class="gf-td gf-td-doc-oblig">
                        @if ($tipo->obligatorio)
                            <span class="font-semibold text-primary-700">Sí</span>
                        @else
                            <span class="text-neutral-400">No</span>
                        @endif
                    </div>
                    <div class="gf-td gf-td-doc-activo">
                        <button type="button"
                                wire:click="toggleActivo({{ $tipo->id }})"
                                class="rounded-lg px-2 py-1 text-xs font-semibold transition
                                       @if($tipo->activo) bg-emerald-100 text-emerald-800 hover:bg-emerald-200
                                       @else bg-neutral-100 text-neutral-600 hover:bg-neutral-200 @endif">
                            {{ $tipo->activo ? 'Sí' : 'No' }}
                        </button>
                    </div>
                    <div class="gf-td gf-td-doc-acciones">
                        <button type="button" wire:click="openEdit({{ $tipo->id }})" class="btn-secondary btn-sm">Editar</button>
                        <button type="button" wire:click="confirmDelete({{ $tipo->id }})" class="btn-danger btn-sm">Eliminar</button>
                    </div>
                </div>
            @empty
                <div class="gf-empty">
                    No hay documentos parametrizados. Agregue tipos o actívelos para que la familia pueda subirlos.
                </div>
            @endforelse
        </div>
    </div>

    @if ($showModal)
        @teleport('body')
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog" aria-modal="true" aria-labelledby="modal-doc-tipo-titulo"
                 x-data x-on:keydown.escape.window="$wire.$set('showModal', false)">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm"
                     wire:click="$set('showModal', false)" aria-hidden="true"></div>
                <div class="relative z-10 my-auto w-full max-w-lg max-h-[calc(100dvh-1.75rem)] overflow-hidden rounded-2xl border border-accent-200 bg-white shadow-xl"
                     @click.stop
                     x-init="$nextTick(() => $el.querySelector('[data-modal-focus]')?.focus())">
                    <div class="border-b border-accent-200 px-6 py-4">
                        <h3 id="modal-doc-tipo-titulo" class="text-base font-semibold text-neutral-900">
                            {{ $editId ? 'Editar documento' : 'Nuevo documento' }}
                        </h3>
                        <p class="mt-1 text-xs text-neutral-500">
                            El PDF guardado usará el nombre <span class="font-mono">{dni}_{clave}.pdf</span>
                            en la carpeta del colegio (todos los estudiantes en el mismo directorio).
                        </p>
                    </div>

                    <div class="max-h-[min(calc(100dvh-10rem),32rem)] overflow-y-auto px-6 py-4 space-y-4">
                        @unless ($editId)
                            <div>
                                <label class="form-label" for="doc-clave">Clave *</label>
                                <input id="doc-clave" wire:model="clave" type="text" maxlength="40"
                                       placeholder="ej: dni, partida_nacimiento, vacunas"
                                       data-modal-focus
                                       class="form-input mt-1 font-mono text-sm @error('clave') border-red-400 @enderror">
                                <p class="mt-1 text-[11px] text-neutral-500">Solo minúsculas, números, _ y -. No se puede cambiar después.</p>
                                @error('clave') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        @else
                            <div>
                                <label class="form-label">Clave</label>
                                <p class="mt-1 font-mono text-sm text-neutral-700">{{ $clave }}</p>
                            </div>
                        @endunless

                        <div>
                            <label class="form-label" for="doc-etiqueta">Etiqueta en el formulario *</label>
                            <input id="doc-etiqueta" wire:model="etiqueta" type="text" maxlength="120"
                                   placeholder="ej: DNI del estudiante"
                                   @if($editId) data-modal-focus @endif
                                   class="form-input mt-1 @error('etiqueta') border-red-400 @enderror">
                            @error('etiqueta') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="form-label" for="doc-explicacion">Indicaciones para la familia</label>
                            <textarea id="doc-explicacion"
                                      wire:model="explicacion"
                                      rows="3"
                                      maxlength="{{ \App\Models\DocEstudianteTipo::MAX_EXPLICACION_LENGTH }}"
                                      placeholder="Ej: Suba foto del frente y dorso del DNI del estudiante, legible y sin recortes."
                                      class="form-input mt-1 leading-relaxed @error('explicacion') border-red-400 @enderror"></textarea>
                            <p class="mt-1 text-[11px] text-neutral-500">
                                Texto opcional (máx. {{ \App\Models\DocEstudianteTipo::MAX_EXPLICACION_LENGTH }} caracteres) visible en el portal familia.
                            </p>
                            @error('explicacion') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <p class="form-label">Extensiones permitidas *</p>
                            <div class="mt-2 flex flex-wrap gap-4">
                                @foreach ($extensionesDisponibles as $ext)
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox"
                                               wire:model="extensionesSeleccionadas"
                                               value="{{ $ext }}"
                                               class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                        <span class="uppercase">{{ $ext }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('extensionesSeleccionadas') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="form-label" for="doc-max-archivos">Cantidad máx. de archivos *</label>
                                <input id="doc-max-archivos" wire:model="maxArchivos" type="number" min="1" max="20"
                                       class="form-input mt-1 @error('maxArchivos') border-red-400 @enderror">
                                @error('maxArchivos') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="doc-max-mb">MB máx. por archivo *</label>
                                <input id="doc-max-mb" wire:model="maxMb" type="number" min="1" max="50"
                                       class="form-input mt-1 @error('maxMb') border-red-400 @enderror">
                                @error('maxMb') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="form-label" for="doc-orden">Orden</label>
                                <input id="doc-orden" wire:model="orden" type="number" min="0" max="9999"
                                       class="form-input mt-1 @error('orden') border-red-400 @enderror">
                                @error('orden') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex flex-col justify-end gap-3 pb-1">
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="checkbox" wire:model="obligatorio"
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                    Obligatorio para guardar datos personales
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="checkbox" wire:model="activo"
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                    Activo (visible en portal familia)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50/60 px-6 py-4">
                        <button type="button" wire:click="$set('showModal', false)" class="btn-secondary">Cancelar</button>
                        <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn-primary">
                            <span wire:loading.remove wire:target="save">Guardar</span>
                            <span wire:loading wire:target="save">Guardando…</span>
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    @if ($showConfirm)
        @teleport('body')
            <div class="fixed inset-0 z-[90] flex items-center justify-center px-4 py-8"
                 role="alertdialog" aria-modal="true">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm"
                     wire:click="$set('showConfirm', false)" aria-hidden="true"></div>
                <div class="relative z-10 my-auto w-full max-w-md rounded-2xl border border-accent-200 bg-white p-6 shadow-xl">
                    <p class="text-sm leading-relaxed text-neutral-800">{{ $deleteInfo }}</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="$set('showConfirm', false)" class="btn-secondary">Cancelar</button>
                        @if ($deleteId)
                            <button type="button" wire:click="delete" class="btn-danger">Eliminar</button>
                        @endif
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
