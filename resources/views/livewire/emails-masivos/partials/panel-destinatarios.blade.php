<div class="border-b border-accent-200 bg-white px-5 py-4">
    <p class="se-section-title">Destinatarios</p>
    <p class="mt-1 text-sm text-neutral-600">Solo matriculados regulares del ciclo {{ schoolCtx()->terlecAno() }}.</p>
</div>
<div class="space-y-6 p-5 sm:p-6">
    <div>
        <span class="form-label">Alcance</span>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach (['cursos' => 'Por curso(s)', 'alumnos' => 'Por alumno(s)'] as $val => $label)
                <button type="button" wire:click="$set('tipoDestino', '{{ $val }}')"
                        @class([
                            'rounded-xl border px-4 py-2 text-sm font-semibold',
                            'border-primary-500 bg-primary-600 text-white' => $tipoDestino === $val,
                            'border-accent-200 bg-white' => $tipoDestino !== $val,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @if ($tipoDestino === 'cursos')
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="abrirModalCursos" class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white">Elegir cursos…</button>
            @if (! empty($cursosSeleccionados))
                <span class="self-center text-xs text-neutral-600">{{ count($cursosSeleccionados) }} curso(s)</span>
            @endif
        </div>
    @else
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="abrirModalAlumnos" class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white">Buscar alumnos…</button>
        </div>
    @endif

    <div class="rounded-xl border border-accent-200 bg-accent-50/30 p-4">
        <p class="text-[11px] font-semibold uppercase text-neutral-500">Contacto</p>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach (['multiple' => 'Tipos seleccionados', 'prioridad' => 'Uno (madre → padre → tutor)'] as $val => $label)
                <button type="button" wire:click="$set('modoContacto', '{{ $val }}')"
                        @class([
                            'rounded-lg border px-3 py-1.5 text-xs font-semibold',
                            'border-primary-500 bg-primary-600 text-white' => $modoContacto === $val,
                            'border-accent-200 bg-white' => $modoContacto !== $val,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
        @if ($modoContacto === 'multiple')
            <div class="mt-3 flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.live="incluirMadre" class="rounded"> Madre</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.live="incluirPadre" class="rounded"> Padre</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.live="incluirTutor" class="rounded"> Tutor</label>
            </div>
        @endif
        @error('modoContacto') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    @if (! empty($lineasAlumnos))
        <div class="max-h-56 overflow-y-auto rounded-xl border border-accent-200">
            @if ($tipoDestino === 'cursos')
                @foreach ($lineasPorCurso as $idCurso => $lineas)
                    <div class="border-b border-accent-100 px-3 py-2">
                        <div class="flex justify-between text-xs font-semibold uppercase text-neutral-600">
                            <span>{{ $lineas->first()['cursoLabel'] ?? 'Curso' }}</span>
                            <span>
                                <button type="button" wire:click="marcarTodosLineasCurso({{ (int) $idCurso }}, true)" class="text-primary-700">Todos</button>
                                ·
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
                    <label class="flex items-center gap-2 border-b border-accent-50 px-3 py-2 text-sm">
                        <input type="checkbox" wire:click.prevent="toggleLineaAlumno('{{ $linea['key'] }}')" @checked(! empty($linea['marcado']))>
                        {{ $linea['label'] }}
                    </label>
                @endforeach
            @endif
        </div>
    @endif
    @error('lineasAlumnos') <p class="form-error">{{ $message }}</p> @enderror
</div>
