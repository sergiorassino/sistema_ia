{{-- Permiso de examen por alumno: una hoja PDF por estudiante con materias adeudadas inscriptas a examen. --}}
<div class="mx-auto w-full max-w-5xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Exámenes</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Permiso de examen</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Una hoja por alumno con las materias adeudadas inscriptas a examen
                    (<code class="rounded bg-white/15 px-1 text-xs">apro = 1</code>,
                    <code class="rounded bg-white/15 px-1 text-xs">inscri = 1</code>)
                </p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al panel
            </a>
        </div>
    </section>

    @if (session('status'))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">
            {{ session('status') }}
        </div>
    @endif

    <livewire:examenes.materias-adeudadas-preparacion-panel
        modulo="permiso_examen"
        wire:key="prep-panel-permiso-examen-{{ $prepTick ?? 0 }}" />

    @if ($preparacionLista ?? false)
        <div class="se-card px-5 py-5">
            <p class="se-section-title">Datos del permiso</p>
            <p class="mt-1 text-sm text-neutral-600">
                @if ($etiquetaTurno ?? null)
                    Turno activo: <span class="font-semibold text-neutral-800">{{ $etiquetaTurno }}</span>.
                @endif
                La fecha solo se imprime en el PDF; no se guarda en el sistema.
            </p>

            <div class="mt-4 grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="numero-permiso-inicio" class="form-label">Nº de permiso para comenzar</label>
                    <input type="number"
                           id="numero-permiso-inicio"
                           wire:model.live="numeroPermisoInicio"
                           min="1"
                           max="99999"
                           class="form-input mt-1.5 w-full max-w-[12rem] tabular-nums">
                    <p class="mt-1 text-xs text-neutral-500">Se asigna en orden alfabético; cada alumno suma uno.</p>
                </div>
                <div>
                    <label for="fecha-permiso-pdf" class="form-label">Fecha en el permiso</label>
                    <input type="date"
                           id="fecha-permiso-pdf"
                           wire:model.live="fechaPdf"
                           class="form-input mt-1.5 w-full max-w-[14rem]">
                </div>
            </div>
        </div>

        <div class="se-card px-5 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="se-section-title">Alumnos a imprimir</p>
                    <p class="mt-1 text-sm text-neutral-600">
                        Solo materias adeudadas con inscripción a examen activa en este nivel
                        (hasta {{ \App\Support\Examenes\PermisoExamen::FILAS_POR_PERMISO }} materias por hoja).
                    </p>
                </div>
                <span class="se-pill tabular-nums">
                    {{ $cantidadSeleccionados }} de {{ $totalEstudiantes ?? $estudiantes->count() }} seleccionados
                    @if ($filtrandoBusqueda ?? false)
                        · {{ $estudiantes->count() }} visibles
                    @endif
                </span>
            </div>

            <div class="se-toolbar mt-4 flex-col !items-stretch gap-4 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <label for="permiso-buscar-alumno" class="form-label">Buscar alumno</label>
                    <input id="permiso-buscar-alumno"
                           type="search"
                           wire:model.live.debounce.300ms="buscar"
                           class="form-input mt-1.5 w-full max-w-md"
                           placeholder="Apellido, nombre o DNI (mín. 2 caracteres)"
                           autocomplete="off">
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" wire:click="seleccionarTodosAlumnos"
                        class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    Todos
                </button>
                <button type="button" wire:click="quitarTodosAlumnos"
                        class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    Ninguno
                </button>
            </div>

            @if ($estudiantes->isEmpty())
                <p class="mt-4 text-sm text-neutral-600">
                    @if ($filtrandoBusqueda ?? false)
                        No hay alumnos que coincidan con la búsqueda.
                    @else
                        No hay alumnos con materias adeudadas en este nivel.
                    @endif
                </p>
            @else
                <div class="mt-4 overflow-x-auto rounded-xl border border-accent-200">
                    <table class="min-w-full divide-y divide-accent-200 text-sm">
                        <thead class="bg-accent-50">
                            <tr>
                                <th scope="col" class="w-10 px-3 py-3">
                                    <span class="sr-only">Seleccionar</span>
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Alumno/a</th>
                                <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">DNI</th>
                                <th scope="col" class="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Materias</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-100 bg-white">
                            @foreach ($estudiantes as $est)
                                <tr class="hover:bg-accent-50/80" wire:key="permiso-alumno-{{ $est->idLegajos }}">
                                    <td class="px-3 py-2.5 text-center">
                                        <input type="checkbox"
                                               class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                               wire:model.live="alumnosSeleccionados"
                                               value="{{ $est->idLegajos }}">
                                    </td>
                                    <td class="px-4 py-2.5 font-medium text-neutral-800">
                                        {{ $est->apellido }}, {{ $est->nombre }}
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-neutral-700">{{ $est->dni !== '' ? $est->dni : '—' }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums text-neutral-700">{{ $est->cantidadMaterias }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($puedeGenerarPdf ?? false)
            <div class="se-card space-y-4 px-5 py-5">
                <p class="text-sm text-neutral-600">
                    Se generará un único PDF con una hoja por alumno seleccionado. En el pie figura la localidad del colegio
                    y la fecha indicada arriba.
                </p>
                <form method="POST"
                      action="{{ route('examenes.permiso-examen.pdf.preparar') }}"
                      target="_blank"
                      class="inline">
                    @csrf
                    <input type="hidden" name="numero" value="{{ max(1, (int) $numeroPermisoInicio) }}">
                    <input type="hidden" name="fecha" value="{{ trim($fechaPdf) }}">
                    @foreach ($alumnosSeleccionados as $idAlumno)
                        <input type="hidden" name="alumnos[]" value="{{ (int) $idAlumno }}">
                    @endforeach
                    <button type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Imprimir permisos de examen (PDF)
                    </button>
                </form>
            </div>
        @else
            <div class="se-card px-5 py-8">
                <p class="text-center text-sm text-neutral-600 sm:text-left">
                    @if ($estudiantes->isEmpty())
                        No hay alumnos disponibles para imprimir en este nivel.
                    @elseif (trim($fechaPdf ?? '') === '')
                        Indicá la fecha del permiso.
                    @else
                        Seleccioná al menos un alumno para generar el PDF.
                    @endif
                </p>
            </div>
        @endif
    @endif
</div>
