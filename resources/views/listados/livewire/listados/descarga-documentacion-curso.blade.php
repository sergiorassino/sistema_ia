<div class="se-page mx-auto max-w-6xl">
    <section class="se-hero mb-6">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Estudiantes</p>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Descarga de documentación</h1>
                <p class="max-w-2xl text-sm text-white/80">
                    @if (schoolEsAdministracion())
                        Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    @else
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    @endif
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

    <div class="se-toolbar mb-6">
        <div class="min-w-0 flex-1">
            <label for="se-doc-curso" class="form-label">Curso</label>
            <select id="se-doc-curso" wire:model.live="cursoId" class="form-select mt-1.5 w-full max-w-xl">
                <option value="">Seleccione</option>
                @foreach ($cursos as $curso)
                    <option value="{{ (int) $curso->Id }}">
                        {{ $conNivel ? \App\Support\Listados\ListadoCursoConsulta::etiquetaCursoConNivel($curso) : $curso->nombreParaListado() }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div wire:loading.flex wire:target="cursoId" class="se-card mb-6 items-center gap-3 p-5 text-sm text-neutral-600">
        <svg class="h-4 w-4 animate-spin text-primary-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        Buscando documentación
    </div>

    <div wire:loading.remove wire:target="cursoId">
        @if ($cursoId && ! $hayTipos)
            <div class="se-card p-8 text-center">
                <p class="text-sm font-semibold text-neutral-700">No hay tipos de documentación activos</p>
            </div>
        @elseif ($cursoId)
            <section class="se-card overflow-hidden p-0">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-accent-200 bg-accent-50 px-5 py-4">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Documentación del curso</p>
                        @if ($resumen)
                            <p class="mt-1 text-sm text-neutral-700">
                                {{ $resumen['total_archivos'] }} archivo{{ $resumen['total_archivos'] === 1 ? '' : 's' }}
                                · {{ count($resumen['alumnos']) }} estudiante{{ count($resumen['alumnos']) === 1 ? '' : 's' }}
                                @if ($resumen['sin_archivos'] > 0)
                                    · {{ $resumen['sin_archivos'] }} sin archivos
                                @endif
                            </p>
                        @endif
                    </div>
                    @if ($urlZip)
                        <a href="{{ $urlZip }}"
                           class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Descargar ZIP
                        </a>
                    @endif
                </div>

                @if ($resumen && $resumen['alumnos'] !== [])
                    <div class="max-h-[28rem] w-full overflow-auto se-grid-angosta-wrap px-4 py-4 sm:px-5">
                        <table class="se-grid-pocos-campos w-max">
                            <thead>
                                <tr class="border-b border-accent-200">
                                    <th class="pb-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Estudiante</th>
                                    <th class="pb-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Archivos</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($resumen['alumnos'] as $alumno)
                                    <tr class="border-b border-accent-100 last:border-0">
                                        <td class="py-2.5 text-sm text-neutral-800">{{ $alumno['carpeta'] }}</td>
                                        <td class="py-2.5 text-sm tabular-nums text-neutral-700">{{ $alumno['archivos'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-5 py-10 text-center">
                        <p class="text-sm font-semibold text-neutral-700">Este curso no tiene documentación cargada</p>
                    </div>
                @endif
            </section>
        @endif
    </div>
</div>
