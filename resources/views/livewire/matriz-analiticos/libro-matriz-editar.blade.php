{{-- Edición en grilla de calificaciones del matriz (secundario) — guardado por celda al blur. --}}
<div class="se-cierre-anual-fill se-matriz-edit-fill">
    <div class="se-cierre-anual-grid se-cierre-anual-grid--matriz-edit gap-2 min-h-0 flex-1">
        <section class="se-hero se-matriz-edit-hero min-w-0 shrink-0">
            <div class="se-hero-inner flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 flex-1 space-y-0.5">
                    <p class="se-eyebrow !text-[10px]">Matríz y analíticos</p>
                    <h2 class="font-bold tracking-tight">Calificaciones en matriz</h2>
                    @if (! empty($alumno))
                        <p class="text-xs text-white/90 leading-snug truncate" title="{{ $alumno['apellido'] }}, {{ $alumno['nombre'] }}">
                            <span class="font-semibold">{{ $alumno['apellido'] }}, {{ $alumno['nombre'] }}</span>
                            @if (($alumno['dni'] ?? '') !== '')
                                · {{ $alumno['dni'] }}
                            @endif
                            @if (($alumno['curso'] ?? '') !== '')
                                · {{ $alumno['curso'] }}
                            @endif
                        </p>
                    @endif
                </div>
                <div class="flex shrink-0 flex-wrap gap-1.5">
                    <button type="button"
                            wire:click="abrirModalDatosAdicionales"
                            class="inline-flex items-center justify-center rounded-lg border border-white/25 bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                        Datos Adicionales
                    </button>
                    <button type="button"
                            wire:click="volver"
                            class="inline-flex items-center justify-center gap-1 rounded-lg border border-white/25 bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Volver
                    </button>
                </div>
            </div>
        </section>

        <div class="se-matriz-edit-body">
            @error('guardar')
                <div class="shrink-0 rounded-lg border border-red-200 bg-red-50 px-2 py-1 text-xs text-red-900" role="alert">
                    {{ $message }}
                </div>
            @enderror

            <div class="se-matriz-edit-panel se-matriz-edit-panel--solo-grilla min-h-0 flex-1">
                {{-- Cabecera fuera del scroll vertical (mismo patrón que cierre anual; evita bleed con sticky). --}}
                <div class="se-cierre-anual-grilla se-matriz-excel-grilla se-matriz-excel-grilla--unified">
                    @if (count($lineas) > 0)
                        <div class="se-cierre-anual-head-wrap se-matriz-excel-head-wrap"
                             data-se-cierre-head>
                            <table class="se-matriz-excel-tabla min-w-[52rem] w-full">
                                <colgroup>
                                    <col style="width:7%">
                                    <col style="width:14%">
                                    <col style="width:21.5%">
                                    <col style="width:7%">
                                    <col style="width:5.5%">
                                    <col style="width:6.5%">
                                    <col style="width:8.5%">
                                    <col style="width:17.5%">
                                    <col style="width:12%">
                                </colgroup>
                                <thead class="se-matriz-excel-thead">
                                    <tr>
                                        <th scope="col" class="text-left">Año</th>
                                        <th scope="col" class="text-left">Curso</th>
                                        <th scope="col" class="text-left">Asignatura</th>
                                        <th scope="col" class="text-center">Calif</th>
                                        <th scope="col" class="text-center">Mes</th>
                                        <th scope="col" class="text-center">Año</th>
                                        <th scope="col" class="text-left">Cond</th>
                                        <th scope="col" class="text-left">Escuapro</th>
                                        <th scope="col" class="text-center">Apro</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    @endif
                    <div class="se-cierre-anual-body-wrap se-matriz-excel-scroll" tabindex="0" data-se-cierre-body>
                        <table class="se-matriz-excel-tabla min-w-[52rem] w-full">
                            <colgroup>
                                <col style="width:7%">
                                <col style="width:14%">
                                <col style="width:21.5%">
                                <col style="width:7%">
                                <col style="width:5.5%">
                                <col style="width:6.5%">
                                <col style="width:8.5%">
                                <col style="width:17.5%">
                                <col style="width:12%">
                            </colgroup>
                            <tbody class="bg-white" data-se-matriz-tbody>
                                    @forelse ($lineas as $i => $lin)
                                        <tr wire:key="matriz-lin-{{ $i }}-{{ $lin['id'] }}-{{ $lin['idMaterias'] }}">
                                            <td><span class="se-matriz-excel-read tabular-nums">{{ $lin['ano_lectivo'] }}</span></td>
                                            <td><span class="se-matriz-excel-read" title="{{ $lin['curso'] }}">{{ $lin['curso'] !== '' ? $lin['curso'] : '—' }}</span></td>
                                            <td>
                                                <div class="se-matriz-excel-materia-cell">
                                                    <button type="button"
                                                            wire:click="abrirModalNombreMateria({{ $i }})"
                                                            class="se-matriz-excel-nombre-btn {{ ! empty($lin['tiene_override']) ? 'se-matriz-excel-nombre-btn--activo' : '' }}"
                                                            title="{{ ! empty($lin['tiene_override']) ? 'Editar nombre para analítico' : 'Definir nombre para analítico' }}"
                                                            aria-label="Nombre de asignatura para analítico: {{ $lin['materia'] }}">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                  d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                                        </svg>
                                                    </button>
                                                    <span @class([
                                                              'se-matriz-excel-read font-medium min-w-0 flex-1',
                                                              'se-matriz-excel-read--override' => ! empty($lin['tiene_override']),
                                                          ])
                                                          title="{{ $lin['materia'] }}{{ ! empty($lin['tiene_override']) ? ' (nombre para analítico)' : '' }}">{{ $lin['materia'] }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text"
                                                       id="se-matriz-{{ (int) $lin['id'] }}-calif"
                                                       data-se-matriz-nav
                                                       wire:model="lineas.{{ $i }}.calif"
                                                       wire:blur="guardarCelda({{ $i }}, 'calif', $event.target.value)"
                                                       maxlength="10"
                                                       @class([
                                                           'se-matriz-excel-input text-center',
                                                           'se-matriz-excel-input--err' => $errors->has('lineas.'.$i.'.calif'),
                                                       ])
                                                       aria-label="Calificación {{ $lin['materia'] }}">
                                            </td>
                                            <td>
                                                <input type="text"
                                                       id="se-matriz-{{ (int) $lin['id'] }}-mes"
                                                       data-se-matriz-nav
                                                       wire:model="lineas.{{ $i }}.mes"
                                                       wire:blur="guardarCelda({{ $i }}, 'mes', $event.target.value)"
                                                       maxlength="2"
                                                       inputmode="numeric"
                                                       @class([
                                                           'se-matriz-excel-input text-center',
                                                           'se-matriz-excel-input--err' => $errors->has('lineas.'.$i.'.mes'),
                                                       ])
                                                       aria-label="Mes {{ $lin['materia'] }}">
                                            </td>
                                            <td>
                                                <input type="text"
                                                       id="se-matriz-{{ (int) $lin['id'] }}-ano"
                                                       data-se-matriz-nav
                                                       wire:model="lineas.{{ $i }}.ano"
                                                       wire:blur="guardarCelda({{ $i }}, 'ano', $event.target.value)"
                                                       maxlength="4"
                                                       inputmode="numeric"
                                                       @class([
                                                           'se-matriz-excel-input text-center',
                                                           'se-matriz-excel-input--err' => $errors->has('lineas.'.$i.'.ano'),
                                                       ])
                                                       aria-label="Año matrícula {{ $lin['materia'] }}">
                                            </td>
                                            <td>
                                                <input type="text"
                                                       id="se-matriz-{{ (int) $lin['id'] }}-cond"
                                                       data-se-matriz-nav
                                                       wire:model="lineas.{{ $i }}.cond"
                                                       wire:blur="guardarCelda({{ $i }}, 'cond', $event.target.value)"
                                                       maxlength="20"
                                                       @class([
                                                           'se-matriz-excel-input',
                                                           'se-matriz-excel-input--err' => $errors->has('lineas.'.$i.'.cond'),
                                                       ])
                                                       aria-label="Condición {{ $lin['materia'] }}">
                                            </td>
                                            <td>
                                                <input type="text"
                                                       id="se-matriz-{{ (int) $lin['id'] }}-escuapro"
                                                       data-se-matriz-nav
                                                       wire:model="lineas.{{ $i }}.escuapro"
                                                       wire:blur="guardarCelda({{ $i }}, 'escuapro', $event.target.value)"
                                                       maxlength="100"
                                                       @class([
                                                           'se-matriz-excel-input',
                                                           'se-matriz-excel-input--err' => $errors->has('lineas.'.$i.'.escuapro'),
                                                       ])
                                                       aria-label="Escuela de aprobación {{ $lin['materia'] }}">
                                            </td>
                                            <td class="text-center">
                                                <span class="se-matriz-excel-apro" title="apro = {{ $lin['apro'] }}">{{ $lin['apro_etiqueta'] }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="!h-auto !max-h-none px-3 py-6 text-center text-xs text-neutral-500">
                                                No hay calificaciones de secundario registradas para este alumno.
                                            </td>
                                        </tr>
                                    @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($modalDatosAdicionalesAbierto)
        @teleport('body')
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="matriz-datos-adicionales-titulo"
             wire:key="matriz-modal-datos-adicionales">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm"
                 wire:click="cerrarModalDatosAdicionales"
                 aria-hidden="true"></div>

            <div class="relative z-10 my-auto flex w-full max-w-2xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl border border-accent-200 bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),44rem)]"
                 @click.stop>
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-accent-200 px-5 py-4">
                    <h3 id="matriz-datos-adicionales-titulo" class="text-base font-bold text-neutral-900">
                        Datos Adicionales
                    </h3>
                    <button type="button"
                            wire:click="guardarDatosAdicionales"
                            wire:loading.attr="disabled"
                            wire:target="guardarDatosAdicionales"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-60">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" wire:loading.remove wire:target="guardarDatosAdicionales" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        <span wire:loading.remove wire:target="guardarDatosAdicionales">Guardar</span>
                        <span wire:loading wire:target="guardarDatosAdicionales">Guardando…</span>
                    </button>
                </div>

                <form wire:submit="guardarDatosAdicionales" class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
                    @error('guardarDatosAdicionales')
                        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900" role="alert">
                            {{ $message }}
                        </div>
                    @enderror

                    @include('livewire.matriz-analiticos.partials.libro-matriz-datos-adicionales-campos', ['idPrefijo' => 'modal-da-'])

                    @if ($idAnaliticoDato)
                        <p class="mt-4 text-xs text-neutral-400">Registro existente · se actualizará al guardar.</p>
                    @else
                        <p class="mt-4 text-xs text-neutral-400">Sin registro previo: al guardar se creará uno nuevo para este legajo.</p>
                    @endif
                </form>

                <div class="flex shrink-0 justify-end border-t border-accent-200 bg-accent-50/80 px-5 py-3">
                    <button type="button"
                            wire:click="cerrarModalDatosAdicionales"
                            class="btn-secondary">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    @if ($modalNombreMateriaAbierto)
        @teleport('body')
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="matriz-nombre-materia-titulo"
             wire:key="matriz-modal-nombre-materia">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm"
                 wire:click="cerrarModalNombreMateria"
                 aria-hidden="true"></div>

            <div class="relative z-10 my-auto flex w-full max-w-md max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl border border-accent-200 bg-white shadow-xl ring-1 ring-black/5"
                 @click.stop>
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-accent-200 px-5 py-4">
                    <h3 id="matriz-nombre-materia-titulo" class="text-base font-bold text-neutral-900">
                        Nombre para analítico
                    </h3>
                    <button type="button"
                            wire:click="cerrarModalNombreMateria"
                            class="rounded-lg p-1.5 text-neutral-500 transition hover:bg-accent-50 hover:text-neutral-800"
                            aria-label="Cerrar">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit="guardarNombreMateriaOverride" class="min-h-0 flex-1 overflow-y-auto px-5 py-5 space-y-4">
                    @error('guardarNombreOverride')
                        <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900" role="alert">
                            {{ $message }}
                        </div>
                    @enderror

                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Asignatura en materias</p>
                        <p class="mt-1 text-sm text-neutral-800">{{ $nombreOverrideMateriaBase !== '' ? $nombreOverrideMateriaBase : '—' }}</p>
                    </div>

                    <div>
                        <label for="matriz-nombre-override-valor" class="block text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                            Nombre en certificado analítico
                        </label>
                        <input id="matriz-nombre-override-valor"
                               type="text"
                               wire:model="nombreOverrideValor"
                               maxlength="300"
                               @class([
                                   'mt-1.5 w-full rounded-xl border border-accent-200 bg-accent-50/60 px-3 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30',
                                   'border-red-400 focus:border-red-500 focus:ring-red-400/30' => $errors->has('nombreOverrideValor'),
                               ])>
                        @error('nombreOverrideValor')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-[11px] text-neutral-500">
                            Este texto se usa en los PDF del analítico. La grilla lo muestra en rojo cuando hay override.
                        </p>
                    </div>
                </form>

                <div class="flex shrink-0 flex-col-reverse gap-2 border-t border-accent-200 bg-accent-50/80 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        @if ($nombreOverrideTiene)
                            <button type="button"
                                    class="btn-secondary text-red-700 hover:border-red-300 hover:bg-red-50"
                                    x-on:click="window.seSwalConfirmar('¿Quitar el nombre especial? El analítico volverá a usar el nombre de materias.', 'Quitar override', { confirmButtonText: 'Sí, quitar' }).then((ok) => { if (ok) $wire.eliminarNombreMateriaOverride(); })">
                                Quitar override
                            </button>
                        @endif
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <button type="button"
                                wire:click="cerrarModalNombreMateria"
                                class="btn-secondary">
                            Cancelar
                        </button>
                        <button type="button"
                                wire:click="guardarNombreMateriaOverride"
                                wire:loading.attr="disabled"
                                wire:target="guardarNombreMateriaOverride"
                                class="btn-primary">
                            <span wire:loading.remove wire:target="guardarNombreMateriaOverride">Guardar</span>
                            <span wire:loading wire:target="guardarNombreMateriaOverride">Guardando…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endteleport
    @endif
</div>

@script
<script>
    $wire.on('se-swal-exito', ({ mensaje }) => {
        if (typeof seSwalExito === 'function') {
            seSwalExito(mensaje ?? 'Guardado.');
        }
    });
    $wire.on('se-swal-error', ({ mensaje }) => {
        if (typeof seSwalError === 'function') {
            seSwalError(mensaje ?? 'No se pudo completar la acción.');
        }
    });
</script>
@endscript
