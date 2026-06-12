<div class="se-page max-w-5xl">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Exámenes · Carga manual</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Materias adeudadas</h2>
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

    @if (session('info'))
        <div class="mt-6 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" role="status">
            {{ session('info') }}
        </div>
    @endif

    @error('carga')
        <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            {{ $message }}
        </div>
    @enderror

    <div class="se-card mt-6 overflow-hidden p-0">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-accent-200 bg-accent-50 px-5 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Materias adeudadas actuales</p>
                <p class="mt-1 text-sm text-neutral-600">
                    Registros en <code class="rounded bg-white px-1 text-xs">calificaciones</code> con <code class="rounded bg-white px-1 text-xs">apro = 1</code>.
                </p>
            </div>
            <span class="se-pill tabular-nums">{{ $totalAdeudadas }} adeudada{{ $totalAdeudadas === 1 ? '' : 's' }}</span>
        </div>

        @if ($totalAdeudadas === 0)
            <div class="px-6 py-10 text-center">
                <p class="text-sm text-neutral-600">El alumno no tiene materias marcadas como adeudadas.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-accent-200 text-sm">
                    <thead class="bg-white">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Materia</th>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Curso</th>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Año lectivo</th>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Condición</th>
                            <th scope="col" class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Estado</th>
                            <th scope="col" class="min-w-[8rem] px-3 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100 bg-white">
                        @foreach ($adeudadas as $fila)
                            <tr class="bg-amber-50/40 hover:bg-amber-50/70" wire:key="ma-adeudada-{{ $fila['id'] }}">
                                <td class="px-4 py-2.5 font-medium text-neutral-800">{{ $fila['materia'] }}</td>
                                <td class="px-4 py-2.5 text-neutral-700">{{ $fila['curso'] !== '' ? $fila['curso'] : '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-neutral-700">{{ $fila['ano_lectivo'] }}</td>
                                <td class="px-4 py-2.5 text-neutral-600">{{ $fila['condicion'] !== '' ? $fila['condicion'] : '—' }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900">Adeudada</span>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <button type="button"
                                            wire:click="quitarAdeudada({{ $fila['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="quitarAdeudada({{ $fila['id'] }})"
                                            class="btn-secondary btn-sm text-red-700 hover:border-red-200 hover:bg-red-50">
                                        <span wire:loading.remove wire:target="quitarAdeudada({{ $fila['id'] }})">Quitar adeudo</span>
                                        <span wire:loading wire:target="quitarAdeudada({{ $fila['id'] }})">…</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="border-t border-accent-200 bg-white px-5 py-4">
            @if (! $mostrarAgregar)
                <button type="button"
                        wire:click="abrirAgregar"
                        class="btn-primary">
                    Agregar materia adeudada
                </button>
            @else
                <button type="button"
                        wire:click="cerrarAgregar"
                        class="btn-secondary">
                    Ocultar listado de materias
                </button>
            @endif
        </div>
    </div>

    @if ($mostrarAgregar)
        <div class="se-card mt-6 overflow-hidden p-0">
            <div class="border-b border-accent-200 bg-white px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Todas las materias del alumno</p>
                <p class="mt-1 text-sm text-neutral-600">
                    Calificaciones del legajo en este nivel ({{ $totalMaterias }} registro{{ $totalMaterias === 1 ? '' : 's' }}).
                    Las filas resaltadas ya están adeudadas; las demás pueden registrarse con el botón de la derecha.
                </p>
            </div>

            @if ($totalMaterias === 0)
                <div class="px-6 py-10 text-center">
                    <p class="text-sm text-neutral-600">No hay registros de calificaciones para este alumno en el nivel actual.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-accent-200 text-sm">
                        <thead class="bg-accent-50/80">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Materia</th>
                                <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Curso</th>
                                <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Año lectivo</th>
                                <th scope="col" class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Estado</th>
                                <th scope="col" class="min-w-[11rem] px-3 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-100 bg-white">
                            @foreach ($todas as $fila)
                                <tr @class([
                                    'hover:bg-accent-50/80' => ! $fila['esAdeudada'],
                                    'bg-emerald-50/50 hover:bg-emerald-50/80' => $fila['esAdeudada'],
                                ]) wire:key="ma-todas-{{ $fila['id'] }}">
                                    <td class="px-4 py-2.5 font-medium text-neutral-800">{{ $fila['materia'] }}</td>
                                    <td class="px-4 py-2.5 text-neutral-700">{{ $fila['curso'] !== '' ? $fila['curso'] : '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-neutral-700">{{ $fila['ano_lectivo'] }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        @if ($fila['esAdeudada'])
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-900">Adeudada</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-neutral-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-600">Sin adeudo</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-right">
                                        @if ($fila['esAdeudada'])
                                            <span class="text-xs font-medium text-neutral-400">Ya registrada</span>
                                        @else
                                            <button type="button"
                                                    wire:click="registrarAdeudada({{ $fila['id'] }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="registrarAdeudada({{ $fila['id'] }})"
                                                    class="btn-primary btn-sm whitespace-nowrap">
                                                <span wire:loading.remove wire:target="registrarAdeudada({{ $fila['id'] }})">Registrar adeudada</span>
                                                <span wire:loading wire:target="registrarAdeudada({{ $fila['id'] }})">…</span>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
