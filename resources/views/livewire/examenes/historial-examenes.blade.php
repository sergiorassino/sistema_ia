<div class="se-page max-w-7xl">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Exámenes · Historial</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Historial de exámenes</h2>
                @if (! empty($alumno))
                    <p class="text-sm text-white/90">
                        <span class="font-semibold">{{ $alumno['apellido'] }}, {{ $alumno['nombre'] }}</span>
                        @if ($alumno['dni'] !== '')
                            · DNI {{ $alumno['dni'] }}
                        @endif
                        @if ($alumno['curso'] !== '')
                            · {{ $alumno['curso'] }}
                        @endif
                    </p>
                    <p class="text-sm text-white/70">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() ?? '—' }}
                    </p>
                @endif
            </div>
            <a href="{{ route('examenes.materias-adeudadas.gestion') }}"
               wire:navigate
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al listado
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
            {{ session('success') }}
        </div>
    @endif

    @error('historial')
        <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            {{ $message }}
        </div>
    @enderror

    <div class="se-card mt-6 overflow-hidden p-0">
        <div class="border-b border-accent-200 bg-accent-50 px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Rendiciones registradas</p>
            <p class="mt-1 text-sm text-neutral-600">
                Registros en <code class="rounded bg-white px-1 text-xs">notasexamen</code>
                agrupados por materia (orden del plan de estudios) y, dentro de cada una, por fecha de examen.
            </p>
        </div>

        @if ($totalRegistros === 0)
            <div class="px-6 py-12 text-center">
                <p class="text-sm text-neutral-600">No hay exámenes registrados para este alumno.</p>
            </div>
        @else
            <div class="border-b border-accent-200 bg-white px-5 py-3">
                <span class="se-pill tabular-nums">
                    {{ $totalMaterias }} materia{{ $totalMaterias === 1 ? '' : 's' }}
                    · {{ $totalRegistros }} registro{{ $totalRegistros === 1 ? '' : 's' }}
                </span>
            </div>

            <div class="divide-y divide-accent-200">
                @foreach ($materias as $bloque)
                    <section wire:key="hist-mat-{{ $bloque['clave'] }}" class="bg-white">
                        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-accent-100 bg-accent-50/60 px-5 py-3">
                            <div class="min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Materia</p>
                                <p class="text-sm font-semibold text-neutral-900">{{ $bloque['materia'] }}</p>
                                @if ($bloque['curso'] !== '')
                                    <p class="mt-0.5 text-xs text-neutral-600">{{ $bloque['curso'] }}</p>
                                @endif
                            </div>
                            <span class="se-pill tabular-nums">
                                {{ count($bloque['rendiciones']) }} rendición{{ count($bloque['rendiciones']) === 1 ? '' : 'es' }}
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-accent-200 text-sm">
                                <thead class="bg-white">
                                    <tr>
                                        <th scope="col" class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Fecha examen</th>
                                        <th scope="col" class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Nota</th>
                                        <th scope="col" class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Cond.</th>
                                        <th scope="col" class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Libro</th>
                                        <th scope="col" class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Folio</th>
                                        <th scope="col" class="hidden px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500 sm:table-cell">Curso</th>
                                        <th scope="col" class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Año lectivo</th>
                                        <th scope="col" class="min-w-[7rem] px-3 py-2.5 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-accent-100">
                                    @foreach ($bloque['rendiciones'] as $fila)
                                        <tr class="hover:bg-accent-50/80" wire:key="hist-nota-{{ $fila['id'] }}">
                                            <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-neutral-800">{{ $fila['fecha'] }}</td>
                                            <td class="px-4 py-2.5 font-medium tabular-nums text-neutral-800">{{ $fila['nota'] !== '' ? $fila['nota'] : '—' }}</td>
                                            <td class="px-4 py-2.5 text-neutral-700">{{ $fila['condicion'] !== '' ? $fila['condicion'] : '—' }}</td>
                                            <td class="px-4 py-2.5 text-neutral-700">{{ $fila['libro'] !== '' ? $fila['libro'] : '—' }}</td>
                                            <td class="px-4 py-2.5 text-neutral-700">{{ $fila['folio'] !== '' ? $fila['folio'] : '—' }}</td>
                                            <td class="hidden px-4 py-2.5 text-neutral-700 sm:table-cell">{{ $fila['curso'] !== '' ? $fila['curso'] : '—' }}</td>
                                            <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-neutral-700">{{ $fila['ano_lectivo'] }}</td>
                                            <td class="px-3 py-2.5">
                                                <div class="flex flex-wrap items-center justify-center gap-2">
                                                    <button type="button"
                                                            wire:click="abrirEditar({{ $fila['id'] }})"
                                                            class="inline-flex items-center gap-1.5 rounded-xl border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-700 transition hover:border-primary-400 hover:bg-primary-100 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                                                            title="Editar registro">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                        Editar
                                                    </button>
                                                    <button type="button"
                                                            wire:click="abrirBorrar({{ $fila['id'] }})"
                                                            class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:border-red-300 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500/30"
                                                            title="Eliminar registro">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                        Borrar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>

    @if ($modalEditarAbierto)
        @teleport('body')
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="hist-editar-titulo"
             wire:key="hist-modal-editar-{{ $idNotaSeleccionada }}">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModales" aria-hidden="true"></div>

            <div class="relative z-10 my-auto flex w-full max-w-md max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl border border-accent-200 bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),40rem)]"
                 @click.stop>
                <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                    <h3 id="hist-editar-titulo" class="text-base font-bold text-neutral-900">Editar registro de examen</h3>
                    @if ($materiaEtiqueta !== '')
                        <p class="mt-1 text-sm text-neutral-600">{{ $materiaEtiqueta }}</p>
                    @endif
                </div>

                <form wire:submit="guardarEdicion" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div>
                        <label for="hist-fecha" class="form-label">Fecha del examen</label>
                        <input id="hist-fecha"
                               type="date"
                               wire:model="fecha"
                               class="form-input @error('fecha') border-red-400 @enderror"
                               required>
                        @error('fecha') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="hist-nota" class="form-label">Nota</label>
                        <input id="hist-nota"
                               type="text"
                               wire:model="nota"
                               maxlength="10"
                               class="form-input @error('nota') border-red-400 @enderror"
                               required>
                        @error('nota') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="hist-cond" class="form-label">Condición de examen</label>
                        <select id="hist-cond"
                                wire:model="condExamen"
                                class="form-input @error('condExamen') border-red-400 @enderror">
                            <option value="">— Sin especificar —</option>
                            @foreach ($condiciones as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                        @error('condExamen') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="hist-libro" class="form-label">Libro</label>
                            <input id="hist-libro"
                                   type="text"
                                   wire:model="libro"
                                   maxlength="10"
                                   class="form-input @error('libro') border-red-400 @enderror">
                            @error('libro') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="hist-folio" class="form-label">Folio</label>
                            <input id="hist-folio"
                                   type="text"
                                   wire:model="folio"
                                   maxlength="10"
                                   class="form-input @error('folio') border-red-400 @enderror">
                            @error('folio') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/80 px-5 py-4">
                        <button type="button" wire:click="cerrarModales" class="btn-secondary">Cancelar</button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="guardarEdicion"
                                class="btn-primary">
                            <span wire:loading.remove wire:target="guardarEdicion">Guardar cambios</span>
                            <span wire:loading wire:target="guardarEdicion">Guardando…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endteleport
    @endif

    @if ($modalBorrarAbierto)
        @teleport('body')
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="hist-borrar-titulo"
             wire:key="hist-modal-borrar-{{ $idNotaSeleccionada }}">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModales" aria-hidden="true"></div>

            <div class="relative z-10 my-auto w-full max-w-sm rounded-2xl border border-accent-200 bg-white p-6 shadow-xl ring-1 ring-black/5"
                 @click.stop>
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 id="hist-borrar-titulo" class="mt-4 text-center text-base font-bold text-neutral-900">Eliminar registro</h3>
                <p class="mt-2 text-center text-sm text-neutral-600">
                    ¿Confirma eliminar la nota de examen
                    @if ($materiaEtiqueta !== '')
                        de <strong>{{ $materiaEtiqueta }}</strong>
                    @endif
                    ? Esta acción no se puede deshacer.
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    <button type="button" wire:click="cerrarModales" class="btn-secondary">Cancelar</button>
                    <button type="button"
                            wire:click="confirmarBorrado"
                            wire:loading.attr="disabled"
                            wire:target="confirmarBorrado"
                            class="inline-flex items-center justify-center rounded-xl border border-red-300 bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        <span wire:loading.remove wire:target="confirmarBorrado">Eliminar</span>
                        <span wire:loading wire:target="confirmarBorrado">Eliminando…</span>
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif
</div>
