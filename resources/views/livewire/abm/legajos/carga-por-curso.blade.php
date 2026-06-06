<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Gestión académica · Legajos</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Carga de datos por curso</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    @if ($cargaPorCursoGrillaVisible && ($cursoCargaPorCursoLabel ?? null))
                        · <span class="font-semibold text-white">{{ $cursoCargaPorCursoLabel }}</span>
                    @endif
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2 self-start">
                <a href="{{ route('abm.legajos') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Volver al listado
                </a>
            </div>
        </div>
    </section>

    <div class="se-card p-4 md:p-5">
        <p class="se-section-title">Configuración</p>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="space-y-4">
                <div>
                    <label for="legajo-carga-curso" class="form-label">Curso</label>
                    <select id="legajo-carga-curso"
                            wire:key="legajo-carga-curso-select-{{ count($cargaPorCursoCursosOpciones) }}"
                            wire:model.live="cargaPorCursoId"
                            class="form-select @error('cargaPorCursoId') border-red-400 @enderror">
                        <option value="">Seleccione curso…</option>
                        @foreach ($cargaPorCursoCursosOpciones as $opt)
                            <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                    @error('cargaPorCursoId') <p class="form-error">{{ $message }}</p> @enderror
                    @if ($cargaPorCursoCursosOpciones === [])
                        <p class="mt-1 text-xs text-amber-700">No hay cursos cargados para el nivel y ciclo lectivo activos.</p>
                    @else
                        <p class="mt-1 text-xs text-neutral-500">{{ count($cargaPorCursoCursosOpciones) }} curso(s) disponible(s).</p>
                    @endif
                </div>

                <div>
                    <p class="form-label">Condición de matrícula</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['regulares' => 'Regulares', 'salidos' => 'Salidos', 'todos' => 'Todas'] as $val => $lbl)
                            <label @class([
                                'inline-flex cursor-pointer items-center rounded-xl border px-3 py-2 text-sm font-semibold transition',
                                'border-primary-500 bg-primary-600 text-white' => $cargaPorCursoFiltroCondicion === $val,
                                'border-accent-200 bg-white text-neutral-700 hover:bg-accent-50' => $cargaPorCursoFiltroCondicion !== $val,
                            ])>
                                <input type="radio" name="legajo-carga-cond" value="{{ $val }}" class="sr-only" wire:model.live="cargaPorCursoFiltroCondicion">
                                {{ $lbl }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between gap-2">
                    <p class="form-label !mb-0">Campos a cargar</p>
                    <button type="button"
                            wire:click="$set('cargaPorCursoCampos', [])"
                            class="text-xs font-semibold text-primary-700 hover:underline">
                        Limpiar
                    </button>
                </div>
                @error('cargaPorCursoCampos') <p class="form-error mt-1">{{ $message }}</p> @enderror
                <div class="mt-2 max-h-52 space-y-3 overflow-y-auto rounded-xl border border-accent-200 bg-accent-50/50 p-3">
                    @forelse ($bloquesCamposCargaPorCurso as $bloque)
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">{{ $bloque['titulo'] }}</p>
                            <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1.5">
                                @foreach ($bloque['items'] as $item)
                                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-neutral-700">
                                        <input type="checkbox"
                                               wire:model.live="cargaPorCursoCampos"
                                               value="{{ $item['key'] }}"
                                               class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                        <span>{{ $item['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-neutral-500">No hay campos parametrizados en campos_legajo con solapa asignada.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button"
                    wire:click="cargarGrillaCargaPorCurso"
                    wire:loading.attr="disabled"
                    wire:target="cargarGrillaCargaPorCurso"
                    class="btn-primary btn-sm">
                <span wire:loading.remove wire:target="cargarGrillaCargaPorCurso">Mostrar alumnos</span>
                <span wire:loading wire:target="cargarGrillaCargaPorCurso">Cargando…</span>
            </button>
            @if ($cargaPorCursoGrillaVisible)
                <span class="se-pill self-center">{{ count($cargaPorCursoRows) }} alumno(s)</span>
            @endif
        </div>
    </div>

    @if ($cargaPorCursoGrillaVisible)
        @if ($cargaPorCursoRows === [])
            <div class="se-card p-8 text-center">
                <p class="text-sm font-semibold text-neutral-700">No hay alumnos en este curso con la condición seleccionada.</p>
            </div>
        @else
            <div class="se-card overflow-hidden">
                <div class="border-b border-accent-200 bg-white px-4 py-3">
                    <p class="text-sm text-neutral-600">
                        Edite las celdas y salga del campo para guardar. Los cambios se aplican al legajo de cada alumno.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-collapse text-sm">
                        <thead class="bg-accent-50">
                            <tr>
                                <th class="table-header sticky left-0 z-10 min-w-[10rem] bg-accent-50">Estudiante</th>
                                <th class="table-header w-24">DNI</th>
                                @foreach ($cargaPorCursoColumnasMeta as $meta)
                                    <th class="table-header min-w-[8rem] whitespace-nowrap">{{ $meta['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-200 bg-white">
                            @foreach ($cargaPorCursoRows as $row)
                                <tr wire:key="legajo-carga-{{ $row['id'] }}" class="hover:bg-accent-50/60">
                                    <td class="table-cell sticky left-0 z-[1] bg-white font-medium text-neutral-900">
                                        {{ $row['apellido'] }}, {{ $row['nombre'] }}
                                    </td>
                                    <td class="table-cell font-mono text-neutral-700">{{ $row['dni'] }}</td>
                                    @foreach ($cargaPorCursoColumnasMeta as $meta)
                                        @php
                                            $col = $meta['column'];
                                            $type = $meta['type'];
                                            $val = $row[$col] ?? '';
                                            $errKey = 'cargaCell.'.$row['id'].'.'.$col;
                                        @endphp
                                        <td class="table-cell align-top py-1.5">
                                            @switch($type)
                                                @case('date')
                                                    <input type="date"
                                                           value="{{ $val }}"
                                                           wire:change="saveCargaPorCursoCell({{ $row['id'] }}, '{{ $col }}', $event.target.value)"
                                                           class="form-input !py-1.5 text-sm @error($errKey) border-red-400 @enderror">
                                                    @break
                                                @case('email')
                                                    <input type="email"
                                                           value="{{ $val }}"
                                                           wire:blur="saveCargaPorCursoCell({{ $row['id'] }}, '{{ $col }}', $event.target.value)"
                                                           class="form-input !py-1.5 text-sm @error($errKey) border-red-400 @enderror">
                                                    @break
                                                @case('sexo')
                                                    <select wire:change="saveCargaPorCursoCell({{ $row['id'] }}, '{{ $col }}', $event.target.value)"
                                                            class="form-select !py-1.5 text-sm">
                                                        <option value="0" @selected($val === '' || $val === '0')>—</option>
                                                        @foreach ($sexosCargaPorCurso as $sx)
                                                            <option value="{{ $sx->id }}" @selected((string) $val === (string) $sx->id)>{{ $sx->sexo }}</option>
                                                        @endforeach
                                                    </select>
                                                    @break
                                                @case('familia')
                                                    @if ($puedeGestionarFamilias ?? false)
                                                        <select wire:change="saveCargaPorCursoCell({{ $row['id'] }}, '{{ $col }}', $event.target.value)"
                                                                class="form-select !py-1.5 text-sm max-w-[12rem]">
                                                            @foreach ($familiasCargaPorCurso as $f)
                                                                <option value="{{ $f->id }}" @selected((string) $val === (string) $f->id)>
                                                                    {{ $f->apellido }}{{ $f->responsable ? ' – '.$f->responsable : '' }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        @php
                                                            $familiaLabel = collect($familiasCargaPorCurso)->firstWhere('id', (int) $val);
                                                        @endphp
                                                        <span class="text-sm text-neutral-700">
                                                            {{ $familiaLabel ? $familiaLabel->apellido.($familiaLabel->responsable ? ' – '.$familiaLabel->responsable : '') : '—' }}
                                                        </span>
                                                    @endif
                                                    @break
                                                @case('si_no')
                                                    <select wire:change="saveCargaPorCursoCell({{ $row['id'] }}, '{{ $col }}', $event.target.value)"
                                                            class="form-select !py-1.5 text-sm">
                                                        <option value="" @selected($val === '')>—</option>
                                                        <option value="si" @selected($val === 'si')>Sí</option>
                                                        <option value="no" @selected($val === 'no')>No</option>
                                                    </select>
                                                    @break
                                                @case('textarea')
                                                    <textarea rows="2"
                                                              wire:blur="saveCargaPorCursoCell({{ $row['id'] }}, '{{ $col }}', $event.target.value)"
                                                              class="form-input resize-y !py-1.5 text-sm min-w-[10rem]">{{ $val }}</textarea>
                                                    @break
                                                @case('number')
                                                    <input type="number"
                                                           value="{{ $val }}"
                                                           wire:blur="saveCargaPorCursoCell({{ $row['id'] }}, '{{ $col }}', $event.target.value)"
                                                           class="form-input !py-1.5 text-sm w-20">
                                                    @break
                                                @default
                                                    <input type="text"
                                                           value="{{ $val }}"
                                                           wire:blur="saveCargaPorCursoCell({{ $row['id'] }}, '{{ $col }}', $event.target.value)"
                                                           class="form-input !py-1.5 text-sm min-w-[8rem] @error($errKey) border-red-400 @enderror">
                                            @endswitch
                                            @error($errKey) <p class="form-error mt-0.5">{{ $message }}</p> @enderror
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
