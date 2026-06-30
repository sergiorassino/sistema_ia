<div class="se-card overflow-hidden">
    <div class="border-b border-accent-200 bg-white px-5 py-4">
        <p class="se-section-title">Destinatarios</p>
        <p class="mt-1 text-sm text-neutral-600">Solo alumnos matriculados <strong>regulares</strong> del ciclo {{ schoolCtx()->terlecAno() }}, sin baja.</p>
    </div>
    <div class="space-y-6 p-5 sm:p-6">
        <div>
            <span class="form-label">Alcance</span>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach (['cursos' => 'Por curso(s)', 'alumnos' => 'Por alumno(s)'] as $val => $label)
                    <button type="button" wire:click="$set('tipoDestino', '{{ $val }}')"
                            @class([
                                'rounded-xl border px-4 py-2.5 text-sm font-semibold shadow-sm',
                                'border-primary-500 bg-primary-600 text-white' => $tipoDestino === $val,
                                'border-accent-200 bg-white text-neutral-700 hover:bg-accent-50' => $tipoDestino !== $val,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
        </div>

        @if ($tipoDestino === 'cursos')
            <div>
                <span class="form-label">Cursos</span>
                <p class="mt-1 text-xs text-neutral-500">Al elegir un curso, todos sus alumnos regulares quedan seleccionados. Podés destildar abajo.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" wire:click="abrirModalCursos" class="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                        Elegir cursos…
                    </button>
                    @if (! empty($cursosSeleccionados))
                        <span class="self-center text-xs font-medium text-neutral-600">{{ count($cursosSeleccionados) }} curso(s)</span>
                    @endif
                </div>
            </div>
        @else
            <div>
                <span class="form-label">Alumnos</span>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" wire:click="abrirModalAlumnos" class="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                        Buscar alumnos…
                    </button>
                    @if (! empty($alumnosSeleccionados))
                        <span class="self-center text-xs font-medium text-neutral-600">{{ count($alumnosSeleccionados) }} alumno(s)</span>
                    @endif
                </div>
            </div>
        @endif

        <div class="rounded-2xl border border-accent-200 bg-white p-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Contacto (madre / padre / tutor)</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (['multiple' => 'Tipos seleccionados', 'prioridad' => 'Uno por alumno (madre → padre → tutor)'] as $val => $label)
                    <button type="button" wire:click="$set('modoContacto', '{{ $val }}')"
                            @class([
                                'rounded-xl border px-3 py-2 text-sm font-semibold',
                                'border-primary-500 bg-primary-600 text-white' => $modoContacto === $val,
                                'border-accent-200 bg-white' => $modoContacto !== $val,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
            @if ($modoContacto === 'multiple')
                <div class="mt-4 flex flex-wrap gap-4 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.live="incluirMadre" class="rounded border-accent-300"> Madre</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.live="incluirPadre" class="rounded border-accent-300"> Padre</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.live="incluirTutor" class="rounded border-accent-300"> Tutor</label>
                </div>
            @endif
            @error('modoContacto') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        @if (! empty($lineasAlumnos))
            <div>
                <p class="form-label">Alumnos incluidos ({{ count(array_filter($lineasAlumnos, fn ($l) => ! empty($l['marcado']))) }} tildados)</p>
                <div class="mt-3 max-h-80 overflow-y-auto rounded-xl border border-accent-200">
                    @if ($tipoDestino === 'cursos')
                        @foreach ($lineasPorCurso as $idCurso => $lineas)
                            @php $cursoLabel = $lineas->first()['cursoLabel'] ?? 'Curso'; @endphp
                            <div class="border-b border-accent-100 px-3 py-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-xs font-semibold uppercase text-neutral-600">{{ $cursoLabel }}</span>
                                    <span class="flex gap-2 text-xs">
                                        <button type="button" wire:click="marcarTodosLineasCurso({{ (int) $idCurso }}, true)" class="text-primary-700">Todos</button>
                                        <button type="button" wire:click="marcarTodosLineasCurso({{ (int) $idCurso }}, false)" class="text-neutral-600">Ninguno</button>
                                    </span>
                                </div>
                                @foreach ($lineas as $linea)
                                    <label class="mt-1 flex items-center gap-2 text-sm">
                                        <input type="checkbox" wire:click.prevent="toggleLineaAlumno('{{ $linea['key'] }}')" @checked(! empty($linea['marcado']))>
                                        {{ $linea['label'] }}
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    @else
                        @foreach ($lineasAlumnos as $linea)
                            <label class="flex items-center gap-2 border-b border-accent-50 px-3 py-2 text-sm last:border-0">
                                <input type="checkbox" wire:click.prevent="toggleLineaAlumno('{{ $linea['key'] }}')" @checked(! empty($linea['marcado']))>
                                {{ $linea['label'] }}
                            </label>
                        @endforeach
                    @endif
                </div>
            </div>
        @endif

        @error('lineasAlumnos') <p class="form-error">{{ $message }}</p> @enderror

        <div class="flex flex-wrap justify-between gap-3">
            <button type="button" wire:click="volverARedactar" class="rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-neutral-700">Volver</button>
            <button type="button" wire:click="irAConfirmacion" class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                Siguiente: confirmar
            </button>
        </div>
    </div>
</div>
