{{-- Coloquios Dic/Feb: solo alumnos regulares con módulos desaprobados o TEA. Guardado vía `saveCell` + delegación focusout en tbody (app.js). --}}
<div class="mx-auto w-full max-w-5xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Secundario</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Coloquios Dic / Feb</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al panel
            </a>
        </div>
    </section>

    <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
        <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <span class="form-label">Período de coloquio</span>
                <div class="mt-2 flex flex-wrap gap-2">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition
                        {{ $campoActivo === 'dic' ? 'border-primary-500 bg-primary-600 text-white' : 'border-accent-200 bg-white text-neutral-700 hover:bg-accent-50' }}">
                        <input type="radio" class="sr-only" name="se-coloquio-periodo" value="dic" wire:model.live="periodo">
                        Diciembre (Dic)
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition
                        {{ $campoActivo === 'feb' ? 'border-primary-500 bg-primary-600 text-white' : 'border-accent-200 bg-white text-neutral-700 hover:bg-accent-50' }}">
                        <input type="radio" class="sr-only" name="se-coloquio-periodo" value="feb" wire:model.live="periodo">
                        Febrero (Feb)
                    </label>
                </div>
            </div>
            <div>
                <label for="se-coloquio-curso" class="form-label">Curso</label>
                <select id="se-coloquio-curso" wire:model.live="cursoId" class="form-select mt-1.5 w-full">
                    <option value="">— Seleccione —</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="se-coloquio-materia" class="form-label">Materia</label>
                <select id="se-coloquio-materia" wire:model.live="materiaId" class="form-select mt-1.5 w-full" @disabled(! $cursoId)>
                    <option value="">— Seleccione —</option>
                    @forelse ($materias as $m)
                        <option value="{{ $m->id }}">{{ trim((string) ($m->materia ?? '')) !== '' ? $m->materia : ('ID ' . $m->id) }}</option>
                    @empty
                        @if ($cursoId)
                            <option value="" disabled>Sin materias con alumnos de coloquio en {{ $etiquetaPeriodo }}</option>
                        @endif
                    @endforelse
                </select>
            </div>
        </div>
    </div>

    @if ($cursoId && $materiaId)
        <div class="se-card px-5 py-3">
            <p class="text-sm text-neutral-600">
                <span class="font-semibold text-neutral-800">{{ $cursoLabel ?? '—' }}</span>
                <span class="mx-1.5 text-neutral-400">·</span>
                <span class="font-semibold text-neutral-800">{{ $materiaLabel ?? '—' }}</span>
                <span class="mx-1.5 text-neutral-400">·</span>
                <span class="font-semibold text-primary-700">{{ $etiquetaPeriodo }}</span>
            </p>
            <p class="mt-2 text-xs text-neutral-500">
                Solo alumnos <strong>regulares</strong> con algún módulo desaprobado o con <strong>TEA</strong> (inasistencias — recuperan todas las materias).
                Se edita la columna <strong>{{ strtoupper($campoActivo) }}</strong>. Los datos se guardan al salir de cada celda.
                Si la nota de coloquio es <strong>≥ 7</strong>, se traslada al <strong>promedio anual</strong>.
                Quien aprueba en <strong>diciembre</strong> no puede cargar febrero.
            </p>
            @if ($notasPermitidasActiva)
                <p class="mt-1 text-xs text-neutral-500">
                    Notas permitidas: {{ implode(', ', $notasPermitidasLista) }}
                </p>
            @endif
        </div>

        <div class="se-card overflow-hidden p-2 sm:p-4">
            <div class="w-full overflow-x-auto">
                <table class="w-full min-w-[32rem] border-collapse text-sm">
                    <thead>
                        <tr class="bg-accent-50 text-left">
                            <th class="border border-accent-200 px-2 py-2 text-[10px] font-semibold uppercase tracking-wide text-neutral-600 w-10">#</th>
                            <th class="border border-accent-200 px-3 py-2 text-[10px] font-semibold uppercase tracking-wide text-neutral-600">Estudiante</th>
                            <th class="border border-accent-200 px-2 py-2 text-[10px] font-semibold uppercase tracking-wide text-neutral-600 w-36">Motivo</th>
                            <th class="border border-accent-200 px-2 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-600 w-20">Dic</th>
                            <th class="border border-accent-200 px-2 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-600 w-20">Feb</th>
                            <th class="border border-accent-200 px-2 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-600 w-24">Pr. anual</th>
                        </tr>
                    </thead>
                    <tbody
                        data-se-calif-tbody
                        data-se-calif-activa="{{ $notasPermitidasActiva ? '1' : '0' }}"
                        data-se-calif-allowed='@json($notasPermitidasLista)'
                    >
                        @forelse ($rows as $row)
                            @php
                                $febInhabilitado = (bool) ($row['feb_inhabilitado'] ?? false);
                            @endphp
                            <tr class="hover:bg-accent-50/60" wire:key="coloquio-row-{{ (int) $materiaId }}-{{ (int) $row['id'] }}">
                                <td class="border border-accent-200 px-2 py-1.5 text-center text-xs text-neutral-500">
                                    {{ $row['ord'] ?? '' }}
                                </td>
                                <td class="border border-accent-200 px-3 py-1.5 font-medium text-neutral-800">
                                    {{ $row['alumno'] }}
                                </td>
                                <td class="border border-accent-200 px-2 py-1.5">
                                    <span class="se-pill text-[10px] {{ ($row['motivo'] ?? '') === 'TEA' ? 'bg-amber-50 text-amber-900 border-amber-200' : 'bg-red-50 text-red-900 border-red-200' }}">
                                        {{ $row['motivo'] ?? '—' }}
                                    </span>
                                </td>
                                <td class="border border-accent-200 px-1 py-1 {{ $campoActivo === 'dic' ? 'bg-primary-50/40' : 'bg-accent-50/30' }}">
                                    @if ($campoActivo === 'dic')
                                        <input
                                            id="se-calif-{{ (int) $row['id'] }}-dic"
                                            class="w-full rounded border border-accent-200 px-1 py-1 text-center text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                            maxlength="2"
                                            value="{{ $row['dic'] ?? '' }}"
                                            wire:key="cell-{{ (int) $materiaId }}-{{ (int) $row['id'] }}-dic"
                                        />
                                    @else
                                        <span class="block py-1 text-center text-sm text-neutral-600">{{ $row['dic'] !== '' ? $row['dic'] : '—' }}</span>
                                    @endif
                                </td>
                                <td class="border border-accent-200 px-1 py-1 {{ $campoActivo === 'feb' ? 'bg-primary-50/40' : 'bg-accent-50/30' }} {{ $febInhabilitado ? 'bg-accent-100/80' : '' }}">
                                    @if ($campoActivo === 'feb' && ! $febInhabilitado)
                                        <input
                                            id="se-calif-{{ (int) $row['id'] }}-feb"
                                            class="w-full rounded border border-accent-200 px-1 py-1 text-center text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                            maxlength="2"
                                            value="{{ $row['feb'] ?? '' }}"
                                            wire:key="cell-{{ (int) $materiaId }}-{{ (int) $row['id'] }}-feb"
                                        />
                                    @elseif ($campoActivo === 'feb' && $febInhabilitado)
                                        <span
                                            class="block cursor-not-allowed py-1 text-center text-sm text-neutral-500"
                                            title="Aprobado en diciembre ({{ $row['dic'] }})"
                                        >—</span>
                                    @else
                                        @if ($febInhabilitado)
                                            <span
                                                class="block py-1 text-center text-sm text-neutral-500"
                                                title="Aprobado en diciembre ({{ $row['dic'] }})"
                                            >—</span>
                                        @else
                                            <span class="block py-1 text-center text-sm text-neutral-600">{{ $row['feb'] !== '' ? $row['feb'] : '—' }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="border border-accent-200 px-2 py-1.5 text-center text-sm font-semibold text-neutral-800 bg-accent-50/50">
                                    {{ $row['calif'] !== '' ? $row['calif'] : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="border border-accent-200 px-4 py-8 text-center text-sm text-neutral-600">
                                    No hay alumnos regulares con módulos desaprobados o TEA para esta materia.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="se-card px-5 py-8">
            <p class="text-center text-sm text-neutral-600 sm:text-left">
                Elegí el período (diciembre o febrero), el curso y la materia para cargar coloquios.
            </p>
        </div>
    @endif
</div>
