@php use App\Support\ComunicacionesRutasGestion; @endphp
<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Comunicaciones</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Nuevo comunicado</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
            </div>

            <a href="{{ ComunicacionesRutasGestion::route('index') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver a la bandeja
            </a>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="se-section-title">Destinatarios y mensaje</p>
            <p class="mt-1 text-sm text-neutral-600">Definí destinatarios y redactá el mensaje. A familias se aplican canales y preferencias; entre docentes, los canales configurados en el sistema.</p>
        </div>

        <div class="space-y-8 border-t border-accent-100 bg-accent-50/30 p-5 sm:p-6">
            <div>
                <label for="destinatario-tipo-com" class="form-label">Destinatario</label>
                <p class="mt-1 text-xs text-neutral-500">Solo se listan destinatarios autorizados por los canales del nivel (permiso <strong class="font-medium text-neutral-700">Iniciar conversación</strong> para su rol).</p>
                @if (empty($opcionesDestinatarios))
                    <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        No hay destinatarios habilitados para iniciar comunicados con su rol en este nivel. Revise la parametrización de canales.
                    </p>
                @else
                    <select id="destinatario-tipo-com"
                            wire:model.live="destinatarioTipo"
                            class="form-input mt-3 max-w-md">
                        <option value="">— Elegir destinatario —</option>
                        @foreach ($opcionesDestinatarios as $op)
                            <option value="{{ $op['value'] }}">{{ $op['label'] }}</option>
                        @endforeach
                    </select>
                @endif
                @error('destinatarioTipo') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            @if ($destinatarioTipo === 'familia')
                <div class="rounded-2xl border border-accent-200 bg-white p-4 shadow-sm sm:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-500">Estudiantes</p>
                    <p class="mt-1 text-sm text-neutral-600">El envío respeta canales y preferencias de cada familia.</p>

                    <div class="mt-4">
                        <span class="form-label">Alcance</span>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach (['alumnos' => 'Uno o varios alumnos', 'cursos' => 'Uno o varios cursos', 'colegio' => 'Todo el colegio'] as $val => $label)
                                <button type="button"
                                        wire:click="$set('tipoDestino', '{{ $val }}')"
                                        @class([
                                            'inline-flex cursor-pointer items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-semibold shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
                                            'border-primary-500 bg-primary-600 text-white' => $tipoDestino === $val,
                                            'border-accent-200 bg-white text-neutral-700 hover:bg-accent-50' => $tipoDestino !== $val,
                                        ])>
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        @error('tipoDestino') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    @if ($tipoDestino === 'colegio')
                        <div class="mt-4 rounded-lg border border-dashed border-accent-300 bg-accent-50/40 px-2.5 py-2">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Control destinatarios</p>
                            <p class="mt-0.5 text-[10px] leading-snug text-neutral-800">Familias con matrícula vigente en este nivel y ciclo lectivo (todo el colegio).</p>
                        </div>
                    @endif

                    @if ($tipoDestino === 'alumnos')
                        <div class="mt-5">
                            <span class="form-label">Alumnos</span>
                            <p class="mt-1 text-xs text-neutral-500">
                                Abrí el listado con el botón, marcá alumnos con las casillas y confirmá con <strong class="font-medium text-neutral-700">Aplicar selección</strong>. Podés filtrar por apellido, nombre o DNI dentro del panel.
                            </p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <button type="button"
                                        wire:click="abrirModalAlumnos"
                                        class="inline-flex items-center justify-center rounded-xl border border-primary-500 bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                    Elegir alumnos…
                                </button>
                                @if (! empty($alumnosSeleccionados))
                                    <span class="text-xs font-medium text-neutral-600">{{ count($alumnosSeleccionados) }} seleccionado(s)</span>
                                @endif
                            </div>

                            @if (! empty($alumnosSeleccionados))
                                <div class="mt-2 rounded-lg border border-accent-200 bg-white px-2 py-1.5">
                                    <p class="text-[9px] font-semibold uppercase tracking-wide text-neutral-500">Control destinatarios</p>
                                    <div class="mt-1 max-h-24 overflow-y-auto text-[10px] leading-snug text-neutral-800">
                                        @foreach ($alumnosSeleccionados as $al)
                                            <span class="mr-2 inline-flex max-w-full items-baseline gap-0.5 align-top">
                                                <span class="break-words">{{ $al['label'] }}</span>
                                                <button type="button"
                                                        wire:click="removeAlumno({{ $al['id'] }})"
                                                        class="shrink-0 text-neutral-400 hover:text-red-600"
                                                        title="Quitar">×</button>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($tipoDestino === 'cursos')
                        <div class="mt-5">
                            <span class="form-label">Cursos</span>
                            <p class="mt-1 text-xs text-neutral-500">
                                Abrí el listado con el botón y marcá uno o varios cursos. Podés filtrar por nombre dentro del panel.
                            </p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <button type="button"
                                        wire:click="abrirModalCursos"
                                        @disabled(empty($cursos))
                                        class="inline-flex items-center justify-center rounded-xl border border-primary-500 bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                    Elegir cursos…
                                </button>
                                @if (empty($cursos))
                                    <span class="text-xs text-neutral-500">No hay cursos en este contexto.</span>
                                @elseif (! empty($cursosSeleccionados))
                                    <span class="text-xs font-medium text-neutral-600">{{ count($cursosSeleccionados) }} curso(s)</span>
                                @endif
                            </div>

                            @if (! empty($cursosSeleccionados))
                                <div class="mt-2 rounded-lg border border-accent-200 bg-white px-2 py-1.5">
                                    <p class="text-[9px] font-semibold uppercase tracking-wide text-neutral-500">Control destinatarios</p>
                                    <div class="mt-1 max-h-24 overflow-y-auto text-[10px] leading-snug text-neutral-800">
                                        @foreach ($cursosSeleccionados as $c)
                                            <span class="mr-2 inline-flex max-w-full items-baseline gap-0.5 align-top">
                                                <span class="break-words">{{ $c['label'] }}</span>
                                                <button type="button"
                                                        wire:click="removeCurso({{ $c['id'] }})"
                                                        class="shrink-0 text-neutral-400 hover:text-red-600"
                                                        title="Quitar">×</button>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @elseif ($destinatarioTipo === 'grupos')
                <div class="rounded-2xl border border-accent-200 bg-white p-4 shadow-sm sm:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-500">Mis grupos</p>
                    <p class="mt-1 text-sm text-neutral-600">
                        El envío llega a estudiantes y personal de los grupos elegidos (según canales y matrícula vigente).
                        <a href="{{ ComunicacionesRutasGestion::route('grupos') }}" class="font-semibold text-primary-700 hover:underline">Administrar grupos</a>
                    </p>
                    <div class="mt-5">
                        <span class="form-label">Grupos</span>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <button type="button"
                                    wire:click="abrirModalGrupos"
                                    class="inline-flex items-center justify-center rounded-xl border border-primary-500 bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                Elegir grupos…
                            </button>
                            @if (! empty($gruposSeleccionados))
                                <span class="text-xs font-medium text-neutral-600">{{ count($gruposSeleccionados) }} grupo(s)</span>
                            @endif
                        </div>
                        @if (! empty($gruposSeleccionados))
                            <div class="mt-2 rounded-lg border border-accent-200 bg-white px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-neutral-500">Control destinatarios</p>
                                <div class="mt-1 max-h-24 overflow-y-auto text-[10px] leading-snug text-neutral-800">
                                    @foreach ($gruposSeleccionados as $g)
                                        <span class="mr-2 inline-flex max-w-full items-baseline gap-0.5 align-top">
                                            <span class="break-words">{{ $g['label'] }}@if (! empty($g['miembros'])) ({{ $g['miembros'] }})@endif</span>
                                            <button type="button"
                                                    wire:click="removeGrupo({{ $g['id'] }})"
                                                    class="shrink-0 text-neutral-400 hover:text-red-600"
                                                    title="Quitar">×</button>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @elseif (str_starts_with($destinatarioTipo, 'tipo:'))
                <div class="rounded-2xl border border-accent-200 bg-white p-4 shadow-sm sm:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-500">{{ $this->etiquetaDestinatarioSeleccionado() }}</p>
                    <p class="mt-1 text-sm text-neutral-600">Personas del nivel con ese rol. Abrí el listado con el botón y confirmá la selección en el panel.</p>

                    <div class="mt-5">
                        <span class="form-label">Destinatarios</span>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <button type="button"
                                    wire:click="abrirModalDocentes"
                                    class="inline-flex items-center justify-center rounded-xl border border-primary-500 bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                Elegir {{ $this->etiquetaDestinatarioSeleccionado() }}…
                            </button>
                            @if (! empty($docentesSeleccionados))
                                <span class="text-xs font-medium text-neutral-600">{{ count($docentesSeleccionados) }} seleccionado(s)</span>
                            @endif
                        </div>

                        @if (! empty($docentesSeleccionados))
                            <div class="mt-2 rounded-lg border border-accent-200 bg-white px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-neutral-500">Control destinatarios</p>
                                <div class="mt-1 max-h-24 overflow-y-auto text-[10px] leading-snug text-neutral-800">
                                    @foreach ($docentesSeleccionados as $d)
                                        <span class="mr-2 inline-flex max-w-full items-baseline gap-0.5 align-top">
                                            <span class="break-words">{{ $d['label'] }}</span>
                                            <button type="button"
                                                    wire:click="removeDocente({{ $d['id'] }})"
                                                    class="shrink-0 text-neutral-400 hover:text-red-600"
                                                    title="Quitar">×</button>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if ($destinatarioTipo !== '')
                <div>
                    <label for="asunto-com" class="form-label">Asunto</label>
                    <input id="asunto-com"
                           type="text"
                           wire:model="asunto"
                           maxlength="{{ $maxAsunto }}"
                           placeholder="Asunto del comunicado"
                           class="form-input" />
                    @error('asunto') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="contenido-com" class="form-label">Mensaje</label>
                    <textarea id="contenido-com"
                              wire:model="contenido"
                              rows="5"
                              maxlength="{{ $maxContenido }}"
                              placeholder="Escriba el comunicado aquí…"
                              class="form-input resize-none leading-relaxed"></textarea>
                    <p class="mt-1 text-right text-xs text-neutral-500 tabular-nums">
                        {{ mb_strlen($contenido) }} / {{ $maxContenido }}
                    </p>
                    @error('contenido') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                @if ($destinatarioTipo === 'familia')
                    <div class="rounded-2xl border border-accent-200 bg-white p-4 shadow-sm">
                        <label class="flex cursor-pointer select-none items-start gap-3">
                            <input type="checkbox"
                                   wire:model="familiaPuedeResponder"
                                   class="mt-0.5 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm text-neutral-800">
                                <span class="font-semibold text-neutral-900">Permitir que la familia responda</span>
                                <span class="mt-1 block text-xs leading-relaxed text-neutral-500">
                                    Si lo desactivás, el comunicado queda <strong class="font-medium text-neutral-700">solo informativo</strong>: podrán leerlo en el cuaderno pero no enviar respuestas.
                                </span>
                            </span>
                        </label>
                    </div>
                @elseif ($destinatarioTipo === 'grupos')
                    <div class="space-y-3">
                        <div class="rounded-2xl border border-accent-200 bg-white p-4 shadow-sm">
                            <label class="flex cursor-pointer select-none items-start gap-3">
                                <input type="checkbox"
                                       wire:model="familiaPuedeResponder"
                                       class="mt-0.5 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                                <span class="text-sm text-neutral-800">
                                    <span class="font-semibold text-neutral-900">Permitir que la familia responda</span>
                                    <span class="mt-1 block text-xs leading-relaxed text-neutral-500">
                                        Aplica a los estudiantes del grupo. Si lo desactivás, el comunicado queda informativo para las familias.
                                    </span>
                                </span>
                            </label>
                        </div>
                        <div class="rounded-2xl border border-accent-200 bg-white p-4 shadow-sm">
                            <label class="flex cursor-pointer select-none items-start gap-3">
                                <input type="checkbox"
                                       wire:model="docentesDestinatariosPuedenResponder"
                                       class="mt-0.5 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                                <span class="text-sm text-neutral-800">
                                    <span class="font-semibold text-neutral-900">Permitir que el personal destinatario responda</span>
                                    <span class="mt-1 block text-xs leading-relaxed text-neutral-500">
                                        Aplica a directivos, preceptores, profesores u otro personal incluido en el grupo.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                @elseif (str_starts_with($destinatarioTipo, 'tipo:'))
                    <div class="rounded-2xl border border-accent-200 bg-white p-4 shadow-sm">
                        <label class="flex cursor-pointer select-none items-start gap-3">
                            <input type="checkbox"
                                   wire:model="docentesDestinatariosPuedenResponder"
                                   class="mt-0.5 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm text-neutral-800">
                                <span class="font-semibold text-neutral-900">Permitir que los destinatarios respondan en el hilo</span>
                                <span class="mt-1 block text-xs leading-relaxed text-neutral-500">
                                    Si lo desactivás, el comunicado queda <strong class="font-medium text-neutral-700">solo informativo</strong> para docentes: podrán leerlo pero no escribir respuestas. Los <strong class="font-medium text-neutral-700">medios de envío</strong> siguen definidos por el canal; esta opción solo afecta el hilo en la bandeja.
                                </span>
                            </span>
                        </label>
                    </div>
                @endif

                <div class="flex justify-end border-t border-accent-200 pt-2">
                    <button type="button"
                            wire:click="enviar"
                            wire:loading.attr="disabled"
                            class="btn-primary disabled:opacity-60">
                        <span wire:loading wire:target="enviar" class="mr-2 inline-flex">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </span>
                        Enviar comunicado
                    </button>
                </div>
            @endif
        </div>
    </div>

    @teleport('body')
        <div>
    @if ($modalAlumnosAbierto)
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4" role="dialog" aria-modal="true"
             aria-labelledby="com-modal-alumnos-titulo">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalAlumnos"></div>

            <div class="relative z-10 my-auto flex w-full max-w-xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),34rem)]">
                <div class="border-b border-accent-200 bg-accent-50/60 px-4 py-2.5 sm:px-5 sm:py-3">
                    <p id="com-modal-alumnos-titulo" class="text-sm font-bold text-neutral-900">Elegir alumnos</p>
                    <p class="mt-0.5 text-[11px] leading-snug text-neutral-600">Marcá los destinatarios. Podés acotar el listado con el filtro; las selecciones fuera de la vista actual se mantienen al confirmar.</p>
                </div>

                <div class="border-b border-accent-100 bg-white px-4 py-2 sm:px-5 sm:py-2.5">
                    <label for="com-modal-alumnos-filtro" class="form-label">Filtrar listado</label>
                    <input id="com-modal-alumnos-filtro"
                           type="text"
                           wire:model.live.debounce.400ms="modalAlumnosFiltro"
                           placeholder="Apellido, nombre o DNI…"
                           class="form-input mt-1.5" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button type="button"
                                wire:click="modalAlumnosSeleccionarTodosVisibles"
                                class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 transition hover:bg-accent-50">
                            Marcar visibles
                        </button>
                        <button type="button"
                                wire:click="modalAlumnosQuitarVisibles"
                                class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-accent-50">
                            Desmarcar visibles
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-1 sm:px-5">
                    @forelse ($modalAlumnosLista as $al)
                        <label wire:key="modal-alumno-{{ $al['id'] }}"
                               class="flex cursor-pointer items-start gap-2 border-b border-accent-100 py-1 last:border-b-0 hover:bg-accent-50/60">
                            <input type="checkbox"
                                   wire:model="modalAlumnosMarcados"
                                   value="{{ $al['id'] }}"
                                   class="mt-0.5 shrink-0 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                            <span class="min-w-0 flex-1 text-sm leading-tight text-neutral-900">
                                <span class="font-semibold">{{ $al['label'] }}</span>
                                @if (! empty($al['dni']))
                                    <span class="ml-1 text-[11px] font-normal leading-tight text-neutral-400">DNI {{ $al['dni'] }}</span>
                                @endif
                            </span>
                        </label>
                    @empty
                        <p class="py-8 text-center text-sm text-neutral-500">No hay alumnos que coincidan con el filtro o con la matrícula en este ciclo.</p>
                    @endforelse
                </div>

                <div class="flex flex-col gap-2 border-t border-accent-200 bg-accent-50/40 px-4 py-2.5 sm:flex-row sm:justify-end sm:px-5 sm:py-3">
                    <button type="button"
                            wire:click="cerrarModalAlumnos"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-800 shadow-sm transition hover:bg-accent-50 sm:w-auto">
                        Cancelar
                    </button>
                    <button type="button"
                            wire:click="aplicarModalAlumnos"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 sm:w-auto">
                        Aplicar selección
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($modalCursosAbierto)
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4" role="dialog" aria-modal="true"
             aria-labelledby="com-modal-cursos-titulo">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalCursos"></div>

            <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),30rem)]">
                <div class="border-b border-accent-200 bg-accent-50/60 px-4 py-2.5 sm:px-5 sm:py-3">
                    <p id="com-modal-cursos-titulo" class="text-sm font-bold text-neutral-900">Elegir cursos</p>
                    <p class="mt-0.5 text-[11px] leading-snug text-neutral-600">Marcá uno o varios cursos del ciclo lectivo actual.</p>
                </div>

                <div class="border-b border-accent-100 bg-white px-4 py-2 sm:px-5 sm:py-2.5">
                    <label for="com-modal-cursos-filtro" class="form-label">Filtrar por nombre</label>
                    <input id="com-modal-cursos-filtro"
                           type="text"
                           wire:model.live.debounce.300ms="modalCursosFiltro"
                           placeholder="Texto del curso…"
                           class="form-input mt-1.5" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button type="button"
                                wire:click="modalCursosSeleccionarTodosVisibles"
                                class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 transition hover:bg-accent-50">
                            Marcar visibles
                        </button>
                        <button type="button"
                                wire:click="modalCursosQuitarVisibles"
                                class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-accent-50">
                            Desmarcar visibles
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-1 sm:px-5">
                    @forelse ($modalCursosLista as $c)
                        <label wire:key="modal-curso-{{ $c['id'] }}"
                               class="flex cursor-pointer items-center gap-2 border-b border-accent-100 py-1 last:border-b-0 hover:bg-accent-50/60">
                            <input type="checkbox"
                                   wire:model="modalCursosMarcados"
                                   value="{{ $c['id'] }}"
                                   class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm font-semibold leading-tight text-neutral-900">{{ $c['label'] }}</span>
                        </label>
                    @empty
                        <p class="py-8 text-center text-sm text-neutral-500">No hay cursos que coincidan con el filtro.</p>
                    @endforelse
                </div>

                <div class="flex flex-col gap-2 border-t border-accent-200 bg-accent-50/40 px-4 py-2.5 sm:flex-row sm:justify-end sm:px-5 sm:py-3">
                    <button type="button"
                            wire:click="cerrarModalCursos"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-800 shadow-sm transition hover:bg-accent-50 sm:w-auto">
                        Cancelar
                    </button>
                    <button type="button"
                            wire:click="aplicarModalCursos"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 sm:w-auto">
                        Aplicar selección
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($modalDocentesAbierto)
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4" role="dialog" aria-modal="true"
             aria-labelledby="com-modal-docentes-titulo">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalDocentes"></div>

            <div class="relative z-10 my-auto flex w-full max-w-xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),34rem)]">
                <div class="border-b border-accent-200 bg-accent-50/60 px-4 py-2.5 sm:px-5 sm:py-3">
                    <p id="com-modal-docentes-titulo" class="text-sm font-bold text-neutral-900">
                        Elegir {{ $this->etiquetaDestinatarioSeleccionado() }}
                    </p>
                    <p class="mt-0.5 text-[11px] leading-snug text-neutral-600">Listado del nivel actual con el rol de cada persona. Podés filtrar por apellido, nombre, DNI o rol.</p>
                </div>

                <div class="border-b border-accent-100 bg-white px-4 py-2 sm:px-5 sm:py-2.5">
                    <label for="com-modal-docentes-filtro" class="form-label">Filtrar listado</label>
                    <input id="com-modal-docentes-filtro"
                           type="text"
                           wire:model.live.debounce.400ms="modalDocentesFiltro"
                           placeholder="Apellido, nombre o DNI…"
                           class="form-input mt-1.5" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button type="button"
                                wire:click="modalDocentesSeleccionarTodosVisibles"
                                class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 transition hover:bg-accent-50">
                            Marcar visibles
                        </button>
                        <button type="button"
                                wire:click="modalDocentesQuitarVisibles"
                                class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-accent-50">
                            Desmarcar visibles
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-1 sm:px-5">
                    @forelse ($modalDocentesLista as $d)
                        <label wire:key="modal-docente-{{ $d['id'] }}"
                               class="flex cursor-pointer items-start gap-2 border-b border-accent-100 py-1 last:border-b-0 hover:bg-accent-50/60">
                            <input type="checkbox"
                                   wire:model="modalDocentesMarcados"
                                   value="{{ $d['id'] }}"
                                   class="mt-0.5 shrink-0 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                            <span class="min-w-0 flex-1 text-sm leading-tight text-neutral-900">
                                <span class="font-semibold">{{ $d['label'] }}</span>
                                @if (! empty($d['rol_label']))
                                    <span class="ml-1 text-[11px] font-medium leading-tight text-primary-700">{{ $d['rol_label'] }}</span>
                                @endif
                                @if (! empty($d['dni']))
                                    <span class="ml-1 text-[11px] font-normal leading-tight text-neutral-400">DNI {{ $d['dni'] }}</span>
                                @endif
                            </span>
                        </label>
                    @empty
                        <p class="py-8 text-center text-sm text-neutral-500">No hay destinatarios que coincidan con el filtro.</p>
                    @endforelse
                </div>

                <div class="flex flex-col gap-2 border-t border-accent-200 bg-accent-50/40 px-4 py-2.5 sm:flex-row sm:justify-end sm:px-5 sm:py-3">
                    <button type="button"
                            wire:click="cerrarModalDocentes"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-800 shadow-sm transition hover:bg-accent-50 sm:w-auto">
                        Cancelar
                    </button>
                    <button type="button"
                            wire:click="aplicarModalDocentes"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 sm:w-auto">
                        Aplicar selección
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($modalGruposAbierto)
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4" role="dialog" aria-modal="true"
             aria-labelledby="com-modal-grupos-titulo">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalGrupos"></div>

            <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),30rem)]">
                <div class="border-b border-accent-200 bg-accent-50/60 px-4 py-2.5 sm:px-5 sm:py-3">
                    <p id="com-modal-grupos-titulo" class="text-sm font-bold text-neutral-900">Elegir grupos</p>
                    <p class="mt-0.5 text-[11px] leading-snug text-neutral-600">Solo se listan los grupos que usted creó en este nivel. Pueden incluir estudiantes y personal juntos.</p>
                </div>

                <div class="border-b border-accent-100 bg-white px-4 py-2 sm:px-5 sm:py-2.5">
                    <label for="com-modal-grupos-filtro" class="form-label">Filtrar por nombre</label>
                    <input id="com-modal-grupos-filtro"
                           type="text"
                           wire:model.live.debounce.300ms="modalGruposFiltro"
                           placeholder="Nombre del grupo…"
                           class="form-input mt-1.5" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button type="button"
                                wire:click="modalGruposSeleccionarTodosVisibles"
                                class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 transition hover:bg-accent-50">
                            Marcar visibles
                        </button>
                        <button type="button"
                                wire:click="modalGruposQuitarVisibles"
                                class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-accent-50">
                            Desmarcar visibles
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-1 sm:px-5">
                    @forelse ($modalGruposLista as $g)
                        <label wire:key="modal-grupo-{{ $g['id'] }}"
                               class="flex cursor-pointer items-center gap-2 border-b border-accent-100 py-1 last:border-b-0 hover:bg-accent-50/60">
                            <input type="checkbox"
                                   wire:model="modalGruposMarcados"
                                   value="{{ $g['id'] }}"
                                   class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm font-semibold leading-tight text-neutral-900">
                                {{ $g['label'] }}
                                <span class="ml-1 text-[11px] font-normal text-neutral-500">{{ (int) ($g['miembros'] ?? 0) }} integrante(s)</span>
                            </span>
                        </label>
                    @empty
                        <p class="py-8 text-center text-sm text-neutral-500">
                            No hay grupos de este tipo en este nivel.
                            <a href="{{ ComunicacionesRutasGestion::route('grupos') }}" class="font-semibold text-primary-700 hover:underline">Crear un grupo</a>
                        </p>
                    @endforelse
                </div>

                <div class="flex flex-col gap-2 border-t border-accent-200 bg-accent-50/40 px-4 py-2.5 sm:flex-row sm:justify-end sm:px-5 sm:py-3">
                    <button type="button"
                            wire:click="cerrarModalGrupos"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-800 shadow-sm transition hover:bg-accent-50 sm:w-auto">
                        Cancelar
                    </button>
                    <button type="button"
                            wire:click="aplicarModalGrupos"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 sm:w-auto">
                        Aplicar selección
                    </button>
                </div>
            </div>
        </div>
    @endif
        </div>
    @endteleport
</div>
