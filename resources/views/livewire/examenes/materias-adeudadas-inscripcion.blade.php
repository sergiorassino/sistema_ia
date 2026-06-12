<div class="se-page max-w-6xl">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Exámenes · Inscribir</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Inscripción a exámenes</h2>
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
            @include('livewire.examenes.partials.materias-adeudadas-volver-listado')
        </div>
    </section>

    @if (session('success'))
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
            {{ session('success') }}
        </div>
    @endif

    @error('inscripcion')
        <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            {{ $message }}
        </div>
    @enderror

    <div class="se-card mt-6 overflow-hidden p-0">
        <div class="border-b border-accent-200 bg-accent-50 px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Materias adeudadas</p>
            <p class="mt-1 text-sm text-neutral-600">
                Registros en <code class="rounded bg-white px-1 text-xs">calificaciones</code> con
                <code class="rounded bg-white px-1 text-xs">apro = 1</code>.
                Podés ajustar la condición (<code class="text-xs">condAdeuda</code>) e indicar inscripción
                (<code class="text-xs">inscri</code> = 1 o 0).
            </p>
        </div>

        @if ($totalFilas === 0)
            <div class="px-6 py-10 text-center">
                <p class="text-sm text-neutral-600">El alumno no tiene materias marcadas como adeudadas.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-accent-200 text-sm">
                    <thead class="bg-white">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Asignatura</th>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Curso</th>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Año de cursado</th>
                            <th scope="col" class="min-w-[7rem] px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Condición</th>
                            <th scope="col" class="min-w-[6rem] px-3 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Inscribir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100 bg-white">
                        @foreach ($filas as $fila)
                            @php
                                $inscripto = (int) $fila['inscri'] === 1;
                            @endphp
                            <tr class="hover:bg-accent-50/80" wire:key="ma-inscripcion-{{ $fila['id'] }}">
                                <td class="px-4 py-2.5 font-medium text-neutral-800">{{ $fila['materia'] }}</td>
                                <td class="px-4 py-2.5 text-neutral-700">{{ $fila['curso'] !== '' ? $fila['curso'] : '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-neutral-700">{{ $fila['ano_lectivo'] }}</td>
                                <td class="px-4 py-2.5">
                                    <label class="sr-only" for="cond-{{ $fila['id'] }}">Condición de {{ $fila['materia'] }}</label>
                                    <select id="cond-{{ $fila['id'] }}"
                                            class="form-input w-full min-w-[5.5rem] py-1.5 text-sm"
                                            wire:change="cambiarCondicion({{ $fila['id'] }}, $event.target.value)"
                                            wire:loading.attr="disabled"
                                            wire:target="cambiarCondicion({{ $fila['id'] }}, $event.target.value)">
                                        @if ($fila['condicion'] === '')
                                            <option value="" disabled selected>—</option>
                                        @endif
                                        @foreach ($condiciones as $c)
                                            <option value="{{ $c }}" @selected($fila['condicion'] === $c)>{{ $c }}</option>
                                        @endforeach
                                        @if ($fila['condicion'] !== '' && ! in_array($fila['condicion'], $condiciones, true))
                                            <option value="{{ $fila['condicion'] }}" selected>{{ $fila['condicion'] }}</option>
                                        @endif
                                    </select>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <label class="inline-flex cursor-pointer items-center justify-center gap-2">
                                        <span class="sr-only">Inscribir {{ $fila['materia'] }} a examen</span>
                                        <input type="checkbox"
                                               class="h-4 w-4 rounded border-accent-300 text-primary-600 focus:ring-primary-500/40"
                                               @checked($inscripto)
                                               wire:change="cambiarInscripcion({{ $fila['id'] }}, $event.target.checked)"
                                               wire:loading.attr="disabled"
                                               wire:target="cambiarInscripcion({{ $fila['id'] }}, $event.target.checked)">
                                        <span class="text-xs font-medium text-neutral-600" aria-hidden="true">
                                            {{ $inscripto ? 'Sí' : 'No' }}
                                        </span>
                                    </label>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-accent-200 bg-white px-5 py-3">
                <p class="text-xs text-neutral-500">
                    {{ $totalFilas }} materia{{ $totalFilas === 1 ? '' : 's' }} adeudada{{ $totalFilas === 1 ? '' : 's' }}.
                    Los cambios se guardan al modificar cada campo.
                </p>
            </div>
        @endif
    </div>
</div>
