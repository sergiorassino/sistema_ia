@php
    use App\Support\Cuotas\CuotasFormato;
    use App\Support\Cuotas\CuotasPlantillaCatalog;
@endphp

<div class="se-page max-w-5xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Gestión masiva</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Generación masiva de cuotas</h1>
                <p class="text-xs text-white/75">
                    Ciclo lectivo {{ $ano }} · Solo estudiantes <strong class="text-white/90">regulares</strong>
                </p>
            </div>
            @if ($paso === 2)
                <button type="button"
                        wire:click="volverACursos"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                    ← Volver a cursos
                </button>
            @elseif ($paso === 3)
                <button type="button"
                        wire:click="volverAlInicio"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                    ← Volver al inicio
                </button>
            @endif
        </div>
    </section>

    @if ($cursos->isEmpty())
        <div class="se-card p-6 text-sm text-neutral-600">
            No hay cursos cargados para el ciclo lectivo activo.
        </div>
    @elseif ($paso === 1)
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-neutral-700">
                        Elija los cursos por nivel. En el siguiente paso seleccionará la cuota a generar.
                    </p>
                    @if ($cursos->isNotEmpty())
                        <span class="se-pill shrink-0 tabular-nums">
                            {{ $cantidadSeleccionados }} de {{ $cursos->count() }} seleccionados
                        </span>
                    @endif
                </div>
            </div>

            <div class="border-b border-accent-200 bg-white px-4 py-3 sm:px-5">
                <label for="filtro-cursos-masiva" class="form-label">Buscar curso</label>
                <input id="filtro-cursos-masiva"
                       type="search"
                       wire:model.live.debounce.300ms="filtroCursos"
                       placeholder="Nivel o nombre del curso…"
                       class="form-input max-w-xl" />
                <div class="mt-2 flex flex-wrap gap-2">
                    <button type="button"
                            wire:click="seleccionarTodosCursos"
                            class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 transition hover:bg-accent-50">
                        Todos
                    </button>
                    <button type="button"
                            wire:click="quitarTodosCursos"
                            class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-accent-50">
                        Ninguno
                    </button>
                </div>
            </div>

            @if ($cursosPorNivel === [])
                <div class="px-4 py-6 text-sm text-neutral-600 sm:px-5">
                    @if (trim($filtroCursos) !== '')
                        Ningún curso coincide con la búsqueda.
                    @else
                        No hay cursos para mostrar.
                    @endif
                </div>
            @else
                @php
                    $cantidadNiveles = count($cursosPorNivel);
                @endphp
                <div class="max-h-[min(65dvh,36rem)] overflow-y-auto px-3 py-2 sm:px-4">
                    <div @class([
                        'mx-auto w-full max-w-md' => $cantidadNiveles === 1,
                        'overflow-x-auto' => $cantidadNiveles > 1,
                    ])>
                        <div @class([
                            'grid w-full gap-3' => $cantidadNiveles === 1,
                            'grid min-w-full gap-3' => $cantidadNiveles > 1,
                        ])
                             @if ($cantidadNiveles > 1)
                                 style="grid-template-columns: repeat({{ $cantidadNiveles }}, minmax(12.5rem, 1fr));"
                             @endif>
                            @foreach ($cursosPorNivel as $bloqueNivel)
                                <section wire:key="nivel-{{ $bloqueNivel['idNivel'] }}"
                                         class="flex min-w-0 flex-col rounded-lg border border-accent-200/90 bg-accent-50/20">
                                    <div class="border-b border-accent-200/80 px-2.5 py-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide leading-snug text-primary-800">
                                            {{ $bloqueNivel['nivelNombre'] }}
                                            <span class="font-normal text-neutral-500 tabular-nums">
                                                ({{ $bloqueNivel['seleccionados'] }}/{{ $bloqueNivel['total'] }})
                                            </span>
                                        </p>
                                        <div class="mt-1.5 flex flex-col gap-1">
                                            <button type="button"
                                                    wire:click="marcarNivel({{ $bloqueNivel['idNivel'] }})"
                                                    class="inline-flex w-full justify-center rounded-md border border-accent-200 bg-white px-2 py-1 text-xs font-semibold leading-snug text-primary-800 hover:bg-accent-50">
                                                Marcar nivel
                                            </button>
                                            <button type="button"
                                                    wire:click="quitarNivel({{ $bloqueNivel['idNivel'] }})"
                                                    class="inline-flex w-full justify-center rounded-md border border-accent-200 bg-white px-2 py-1 text-xs font-semibold leading-snug text-neutral-600 hover:bg-accent-50">
                                                Quitar nivel
                                            </button>
                                        </div>
                                    </div>
                                    <ul class="min-h-0 flex-1 list-none divide-y divide-accent-100/80 px-2 py-1">
                                        @foreach ($bloqueNivel['cursos'] as $cursoItem)
                                            <li wire:key="curso-{{ $cursoItem['id'] }}">
                                                <label class="flex cursor-pointer items-center gap-2 py-1.5 transition-colors hover:bg-accent-50/70">
                                                    <input type="checkbox"
                                                           wire:model.live="cursosSeleccionados"
                                                           value="{{ $cursoItem['id'] }}"
                                                           class="h-4 w-4 shrink-0 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                                                    <span class="min-w-0 flex-1 text-sm leading-normal text-neutral-800">
                                                        {{ $cursoItem['etiqueta'] }}
                                                    </span>
                                                </label>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if ($cantidadSeleccionados > 0)
                <div class="border-t border-accent-200 bg-accent-50/40 px-4 py-3 sm:px-5">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Seleccionados</p>
                    <div class="mt-2 flex max-h-24 flex-wrap gap-1.5 overflow-y-auto">
                        @foreach ($cursosSeleccionadosResumen as $chip)
                            <span class="inline-flex max-w-full items-center gap-1 rounded-lg border border-primary-200 bg-white px-2 py-1 text-xs font-medium leading-snug text-neutral-800">
                                <span class="truncate">{{ $chip['label'] }}</span>
                                <button type="button"
                                        wire:click="quitarCurso({{ $chip['id'] }})"
                                        class="shrink-0 text-neutral-400 hover:text-red-600"
                                        title="Quitar"
                                        aria-label="Quitar {{ $chip['label'] }}">×</button>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @error('cursosSeleccionados')
                <p class="px-4 pb-3 text-sm text-red-600 sm:px-5">{{ $message }}</p>
            @enderror

            <div class="flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/60 px-4 py-3 sm:px-5">
                <button type="button"
                        wire:click="continuarACuota"
                        @disabled($cantidadSeleccionados < 1)
                        class="inline-flex items-center rounded-xl bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50">
                    Continuar
                </button>
            </div>
        </div>
    @elseif ($paso === 2)
        <div class="se-card overflow-hidden mb-4">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <p class="text-sm text-neutral-700">Seleccione la plantilla de cuota del año a generar para los cursos elegidos.</p>
            </div>
            <div class="px-4 py-4 sm:px-5">
                <label for="idCuotaMasiva" class="form-label">Cuota</label>
                <select id="idCuotaMasiva"
                        wire:model.live="idCuota"
                        class="form-input max-w-xl @error('idCuota') border-red-400 @enderror">
                    <option value="0">— Seleccione —</option>
                    @foreach ($plantillas as $cuota)
                        <option value="{{ $cuota->id }}">{{ CuotasPlantillaCatalog::etiquetaCuota($cuota) }}</option>
                    @endforeach
                </select>
                @error('idCuota')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex flex-wrap gap-2 border-t border-accent-200 bg-accent-50/60 px-4 py-3 sm:px-5">
                <button type="button"
                        wire:click="armarVistaPrevia"
                        wire:loading.attr="disabled"
                        wire:target="armarVistaPrevia"
                        @disabled($idCuota < 1)
                        class="inline-flex items-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 hover:bg-accent-50 disabled:opacity-50">
                    <span wire:loading.remove wire:target="armarVistaPrevia">Ver alumnos a generar</span>
                    <span wire:loading wire:target="armarVistaPrevia">Calculando…</span>
                </button>
            </div>
        </div>

        @if (! empty($vistaPrevia))
            @php
                $totalPrevio = (int) ($vistaPrevia['total'] ?? 0);
                $totalAlumnosPrevio = (int) ($vistaPrevia['totalAlumnos'] ?? 0);
                $nombreCuota = (string) ($vistaPrevia['cuotaNombre'] ?? '');
            @endphp
            <div class="se-card overflow-hidden">
                <div class="border-b border-accent-200 bg-white px-4 py-3 sm:px-5">
                    <p class="text-sm font-semibold text-neutral-800">
                        Vista previa — cuota «{{ $nombreCuota }}»
                    </p>
                    <p class="mt-1 text-xs text-neutral-600">
                        @if ($totalAlumnosPrevio === 0)
                            No hay estudiantes regulares en los cursos elegidos.
                        @else
                            <span class="font-semibold tabular-nums">{{ $totalAlumnosPrevio }}</span> estudiante(s) regulares
                            · Se generará para <span class="font-semibold tabular-nums">{{ $totalPrevio }}</span>
                        @endif
                    </p>
                </div>

                @if ($totalAlumnosPrevio === 0)
                    <div class="px-4 py-6 text-sm text-neutral-600 sm:px-5">
                        No hay estudiantes regulares en los cursos elegidos.
                    </div>
                @else
                    <div class="max-h-[min(50dvh,28rem)] overflow-y-auto px-4 py-3 sm:px-5">
                        <div class="space-y-4 text-[11px] leading-snug text-neutral-700">
                            @foreach ($vistaPrevia['porCurso'] ?? [] as $idCursoPrev => $bloque)
                                <div wire:key="prev-curso-{{ $idCursoPrev }}">
                                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-primary-800">
                                        {{ $bloque['cursoNombre'] ?? '' }}
                                        <span class="font-normal text-neutral-500">({{ count($bloque['alumnos'] ?? []) }})</span>
                                    </p>
                                    <ul class="list-none space-y-1 pl-0">
                                        @foreach ($bloque['alumnos'] ?? [] as $alumno)
                                            @php
                                                $puedeGenerar = (bool) ($alumno['puedeGenerar'] ?? false);
                                            @endphp
                                            <li wire:key="prev-{{ $alumno['idLegajo'] }}"
                                                class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                                <span class="text-neutral-800">{{ $alumno['etiqueta'] }}</span>
                                                <span class="{{ $puedeGenerar ? 'text-emerald-800' : 'text-amber-900' }}">
                                                    — {{ $alumno['estado'] ?? '' }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/60 px-4 py-3 sm:px-5">
                        <button type="button"
                                wire:loading.attr="disabled"
                                wire:target="generar"
                                @disabled($totalPrevio < 1)
                                x-data
                                x-on:click="
                                    seSwalConfirmar(
                                        @js("Se generará la cuota «{$nombreCuota}» para {$totalPrevio} estudiante(s) regulares."),
                                        '¿Confirma la generación masiva?'
                                    ).then(ok => { if (ok) $wire.generar(); })
                                "
                                class="inline-flex items-center rounded-xl bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="generar">Generar cuotas</span>
                            <span wire:loading wire:target="generar">Generando…</span>
                        </button>
                    </div>
                @endif
            </div>
        @endif
    @else
        @php
            $nombreCuotaRes = (string) ($resultado['cuotaNombre'] ?? '');
            $generadosRes = (int) ($resultado['generados'] ?? 0);
            $noGeneradosRes = (int) ($resultado['noGenerados'] ?? 0);
            $porCursoRes = $resultado['porCurso'] ?? [];
        @endphp

        <div class="se-card overflow-hidden mb-4">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <p class="text-sm font-semibold text-neutral-800">Resultado — cuota «{{ $nombreCuotaRes }}»</p>
                <p class="mt-1 text-xs text-neutral-600">
                    Generación exitosa: <span class="font-semibold text-emerald-800 tabular-nums">{{ $generadosRes }}</span>
                    @if ($noGeneradosRes > 0)
                        · No generados: <span class="font-semibold text-amber-900 tabular-nums">{{ $noGeneradosRes }}</span>
                    @endif
                </p>
            </div>

            @if ($porCursoRes === [])
                <div class="px-4 py-6 text-sm text-neutral-600 sm:px-5">
                    No se procesaron estudiantes.
                </div>
            @else
                <div class="max-h-[min(55dvh,30rem)] overflow-y-auto px-4 py-3 sm:px-5">
                    <div class="space-y-4 text-[11px] leading-snug text-neutral-700">
                        @foreach ($porCursoRes as $idCursoRes => $bloqueRes)
                            <div wire:key="res-curso-{{ $idCursoRes }}">
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-primary-800">
                                    {{ $bloqueRes['cursoNombre'] ?? '' }}
                                    <span class="font-normal text-neutral-500">({{ count($bloqueRes['alumnos'] ?? []) }})</span>
                                </p>
                                <ul class="list-none space-y-1 pl-0">
                                    @foreach ($bloqueRes['alumnos'] ?? [] as $filaRes)
                                        @php
                                            $exitoFila = (bool) ($filaRes['exito'] ?? false);
                                        @endphp
                                        <li wire:key="res-{{ $filaRes['idLegajo'] }}"
                                            class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                            <span class="text-neutral-800">{{ $filaRes['etiqueta'] }}</span>
                                            <span class="{{ $exitoFila ? 'text-emerald-800' : 'text-amber-900' }}">
                                                — {{ $filaRes['estado'] ?? '' }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/60 px-4 py-3 sm:px-5">
                <button type="button"
                        wire:click="volverAlInicio"
                        class="inline-flex items-center rounded-xl bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                    Volver al inicio
                </button>
            </div>
        </div>
    @endif

    @script
    <script>
        (function () {
            function mensajeDeEvento(event, fallback) {
                return event?.mensaje ?? event?.detail?.mensaje ?? fallback;
            }

            $wire.on('se-swal-error', (event) => {
                const mensaje = mensajeDeEvento(event, 'No se pudo completar la operación.');
                if (typeof window.seSwalError === 'function') {
                    window.seSwalError(mensaje);
                }
            });
        })();
    </script>
    @endscript
</div>
