<div class="se-page max-w-6xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Becas</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Asignación de Becas</h1>
                <p class="text-xs text-white/75">
                    Ciclo lectivo {{ $ano }} · La beca se guarda al cambiar el tipo en cada fila
                </p>
            </div>
        </div>
    </section>

    @if ($opcionesCurso === [])
        <div class="se-card p-6 text-sm text-neutral-600">
            No hay cursos cargados para el ciclo lectivo activo.
        </div>
    @else
        <form wire:submit="cargarAlumnos" class="se-card overflow-hidden mb-4">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <p class="text-sm text-neutral-700">
                    Use <strong>sala / grado / curso</strong> <em>o</em> <strong>buscar por estudiante</strong> (no ambos a la vez) y pulse <strong>Cargar alumnos</strong>.
                </p>
            </div>

            <div class="border-b border-accent-200 bg-white px-4 py-3 sm:px-5">
                <label for="cursos-beca-select" class="form-label">Sala / grado / curso</label>
                <select id="cursos-beca-select"
                        wire:model.live="idCurso"
                        class="form-input max-w-xl @error('idCurso') border-red-400 @enderror">
                    <option value="0">Seleccione</option>
                    @foreach ($opcionesCurso as $opcion)
                        <option value="{{ $opcion['id'] }}">{{ $opcion['etiqueta'] }}</option>
                    @endforeach
                </select>
                @error('idCurso')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="border-b border-accent-200 bg-white px-4 py-3 sm:px-5">
                <label for="buscar-alumno-beca" class="form-label">Buscar por estudiante</label>
                <div class="relative max-w-xl">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input id="buscar-alumno-beca"
                           type="search"
                           wire:model.live.debounce.300ms="searchAlumno"
                           x-on:focus="$el.select()"
                           placeholder="Apellido y nombre, apellido, nombre o DNI…"
                           class="form-input pl-9 @error('searchAlumno') border-red-400 @enderror"
                           autocomplete="off" />
                </div>
                @error('searchAlumno')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/60 px-4 py-3 sm:px-5">
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="cargarAlumnos"
                        class="inline-flex items-center rounded-xl bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="cargarAlumnos">Cargar alumnos</span>
                    <span wire:loading wire:target="cargarAlumnos">Cargando…</span>
                </button>
            </div>
        </form>

        @if ($cargado)
            <div class="se-card overflow-hidden">
                <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                    <p class="text-sm text-neutral-700">
                        @if ($filas === [])
                            No hay alumnos para mostrar.
                        @else
                            <span class="font-semibold tabular-nums">{{ count($filas) }}</span>
                            {{ count($filas) === 1 ? 'alumno cargado' : 'alumnos cargados' }}
                        @endif
                    </p>
                </div>

                @if ($filas !== [])
                    <div class="w-full overflow-x-auto se-grid-angosta-wrap">
                        <div class="gf min-w-[40rem]">
                            <div class="gf-head">
                                <div class="gf-th flex-1 min-w-[14rem]">Apellido y nombre</div>
                                <div class="gf-th w-28">DNI</div>
                                <div class="gf-th w-36">Curso</div>
                                <div class="gf-th w-52">Tipo de beca</div>
                            </div>

                            @foreach ($filas as $idx => $fila)
                                <div wire:key="beca-fila-{{ $fila['idMatricula'] }}"
                                     class="gf-row gf-row-hover">
                                    <div class="gf-td flex-1 min-w-[14rem] font-medium truncate" title="{{ $fila['alumno'] }}">
                                        {{ $fila['alumno'] }}
                                    </div>
                                    <div class="gf-td w-28 tabular-nums whitespace-nowrap">
                                        {{ $fila['dni'] }}
                                    </div>
                                    <div class="gf-td w-36 truncate" title="{{ $fila['cursoLabel'] }}">
                                        {{ $fila['cursoLabel'] }}
                                    </div>
                                    <div class="gf-td w-52">
                                        <select wire:change="actualizarBeca({{ (int) $fila['idMatricula'] }}, $event.target.value)"
                                                wire:loading.attr="disabled"
                                                wire:target="actualizarBeca"
                                                class="form-input w-full py-1.5 text-sm disabled:opacity-60">
                                            @foreach ($becas as $beca)
                                                @php
                                                    $nombreBeca = trim((string) ($beca->nombreBeca ?? ''));
                                                    $pct = rtrim(rtrim(number_format((float) ($beca->porcentaje ?? 0), 2, '.', ''), '0'), '.');
                                                    $etiqueta = $nombreBeca !== '' ? $nombreBeca : 'Tipo '.$beca->id;
                                                    if ($pct !== '' && (float) $pct > 0) {
                                                        $etiqueta .= ' ('.$pct.'%)';
                                                    }
                                                @endphp
                                                <option value="{{ $beca->id }}" @selected((int) ($fila['idCuotasbecas'] ?? 0) === (int) $beca->id)>{{ $etiqueta }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif
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
