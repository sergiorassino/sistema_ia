<div class="se-page max-w-7xl">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Seguimiento · Asistencia</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Toma de asistencia a clase</h2>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Año lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
        </div>
    </section>

    <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
        <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label for="tac-fecha" class="form-label">Fecha *</label>
                <input id="tac-fecha" type="date" wire:model.live="fecha"
                       class="form-input mt-1.5 @error('fecha') border-red-400 @enderror">
                @error('fecha') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="tac-curso" class="form-label">Curso *</label>
                <select id="tac-curso" wire:model.live="idCurso" class="form-select mt-1.5 @error('idCurso') border-red-400 @enderror">
                    <option value="">— Seleccione —</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                    @endforeach
                </select>
                @error('idCurso') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end">
                <button type="button"
                        wire:click="cargarGrilla"
                        wire:loading.attr="disabled"
                        wire:target="cargarGrilla"
                        @disabled(! $puedeCargarGrilla)
                        class="btn-primary w-full disabled:opacity-50">
                    <span wire:loading.remove wire:target="cargarGrilla">Cargar listado</span>
                    <span wire:loading wire:target="cargarGrilla">Cargando…</span>
                </button>
            </div>
        </div>
    </div>

    @if ($grillaCargada && $curso)
        <div class="mb-4 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <span class="se-pill bg-white text-neutral-800">
                    {{ $curso->nombreParaListado() }}
                </span>
                <span class="se-pill bg-white text-neutral-800">
                    {{ \Carbon\Carbon::createFromFormat('Y-m-d', $fecha)->format('d/m/Y') }}
                </span>
                <span class="se-pill inline-flex items-baseline gap-[2mm] border-green-200 bg-green-50 text-green-900">
                    <span>Presentes a clase:</span>
                    <strong class="tabular-nums">{{ $resumen['presentes_clase'] }}/{{ $this->totalAlumnos }}</strong>
                </span>
                <span class="se-pill inline-flex items-baseline gap-[2mm] border-amber-200 bg-amber-50 text-amber-900">
                    <span>Ausentes:</span>
                    <strong class="tabular-nums">{{ $resumen['ausentes'] }}/{{ $this->totalAlumnos }}</strong>
                </span>
                <span class="se-pill inline-flex items-baseline gap-[2mm] border-orange-200 bg-orange-50 text-orange-900">
                    <span>Llegadas tarde:</span>
                    <strong class="tabular-nums">{{ $resumen['llegadas_tarde'] }}/{{ $this->totalAlumnos }}</strong>
                </span>
                <span class="se-pill inline-flex items-baseline gap-[2mm] border-violet-200 bg-violet-50 text-violet-900">
                    <span>Retiros anticipados:</span>
                    <strong class="tabular-nums">{{ $resumen['retiros'] }}/{{ $this->totalAlumnos }}</strong>
                </span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="se-pill inline-flex items-baseline gap-[2mm] border-green-200 bg-green-50 text-green-900">
                    <span>Presentes a Educ. Física:</span>
                    <strong class="tabular-nums">{{ $resumen['presentes_ed_fis'] }}/{{ $this->totalAlumnos }}</strong>
                </span>
                <span class="se-pill inline-flex items-baseline gap-[2mm] border-primary-200 bg-primary-50 text-primary-900">
                    <span>Ausentes ed. física:</span>
                    <strong class="tabular-nums">{{ $resumen['educacion_fisica'] }}/{{ $this->totalAlumnos }}</strong>
                </span>
            </div>
        </div>

        <p class="mb-3 text-xs text-neutral-600">
            Deje «Presente» si asistió. Al elegir un tipo de inasistencia se guarda automáticamente;
            indique si es justificada o injustificada junto a cada select.
            Puede faltar solo a clase, solo a educación física, o a ambas.
        </p>

        <div class="se-card overflow-hidden">
            <table class="w-full text-left text-sm">
                <colgroup>
                    <col class="w-9">
                    <col>
                    <col class="w-[5.5rem]">
                    <col class="w-[19.5rem]">
                    <col class="w-[19.5rem]">
                </colgroup>
                <thead class="border-b border-accent-200 bg-accent-50/80">
                    <tr>
                        <th class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-600">#</th>
                        <th class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-600">Alumno</th>
                        <th class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-600 text-center">Condición</th>
                        <th class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-600">Inasist. a clase</th>
                        <th class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-600">Inasist. educ. física</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($alumnos as $idx => $a)
                        @php
                            $idMat = (int) $a->id;
                            $fila = $asistencia[$idMat] ?? [
                                'clase' => '',
                                'edfis' => '',
                                'just_clase' => 'I',
                                'just_edfis' => 'I',
                            ];
                            $tieneClase = trim((string) ($fila['clase'] ?? '')) !== '';
                            $tieneEdFis = trim((string) ($fila['edfis'] ?? '')) !== '';
                            $teaPendientes = $teaPendientesPorMatricula[$idMat] ?? [];
                        @endphp
                        <tr wire:key="tac-alumno-{{ $idMat }}" @class([
                            'hover:bg-accent-50/60',
                            'border-b border-accent-100' => $teaPendientes === [],
                        ])>
                            <td class="px-3 py-2 tabular-nums text-neutral-500 align-top">{{ $idx + 1 }}</td>
                            <td class="px-3 py-2 align-top">
                                <p class="font-medium text-neutral-900">{{ $a->apellido }}, {{ $a->nombre }}</p>
                                @if ($a->dni)
                                    <p class="text-xs text-neutral-500">DNI {{ $a->dni }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center text-xs text-neutral-700 align-top">
                                {{ ($a->condicion ?? '') !== '' ? $a->condicion : '—' }}
                            </td>
                            <td class="px-3 py-2 align-top">
                                <div class="flex flex-nowrap items-center gap-2">
                                    <select wire:model.live="asistencia.{{ $idMat }}.clase"
                                            class="form-select w-[9rem] shrink-0 text-sm @error('asistencia.'.$idMat.'.clase') border-red-400 @enderror">
                                        <option value="">Presente</option>
                                        @foreach ($tiposClase as $t)
                                            <option value="{{ $t->id }}">{{ $t->concepto }}</option>
                                        @endforeach
                                    </select>
                                    <select wire:model.live="asistencia.{{ $idMat }}.just_clase"
                                            @disabled(! $tieneClase)
                                            class="form-select w-[9.5rem] shrink-0 text-sm disabled:cursor-not-allowed disabled:opacity-50 @error('asistencia.'.$idMat.'.just_clase') border-red-400 @enderror">
                                        <option value="I">Injustificada</option>
                                        <option value="J">Justificada</option>
                                    </select>
                                </div>
                                @error('asistencia.'.$idMat.'.clase')
                                    <p class="form-error mt-0.5">{{ $message }}</p>
                                @enderror
                                @error('asistencia.'.$idMat.'.just_clase')
                                    <p class="form-error mt-0.5">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="px-3 py-2 align-top">
                                <div class="flex flex-nowrap items-center gap-2">
                                    <select wire:model.live="asistencia.{{ $idMat }}.edfis"
                                            class="form-select w-[9rem] shrink-0 text-sm @error('asistencia.'.$idMat.'.edfis') border-red-400 @enderror">
                                        <option value="">Presente</option>
                                        @foreach ($tiposEdFis as $t)
                                            <option value="{{ $t->id }}">{{ $t->concepto }}</option>
                                        @endforeach
                                    </select>
                                    <select wire:model.live="asistencia.{{ $idMat }}.just_edfis"
                                            @disabled(! $tieneEdFis)
                                            class="form-select w-[9.5rem] shrink-0 text-sm disabled:cursor-not-allowed disabled:opacity-50 @error('asistencia.'.$idMat.'.just_edfis') border-red-400 @enderror">
                                        <option value="I">Injustificada</option>
                                        <option value="J">Justificada</option>
                                    </select>
                                </div>
                                @error('asistencia.'.$idMat.'.edfis')
                                    <p class="form-error mt-0.5">{{ $message }}</p>
                                @enderror
                                @error('asistencia.'.$idMat.'.just_edfis')
                                    <p class="form-error mt-0.5">{{ $message }}</p>
                                @enderror
                            </td>
                        </tr>
                        @if ($teaPendientes !== [])
                            <tr wire:key="tac-tea-{{ $idMat }}" class="border-b border-accent-100 hover:bg-accent-50/60">
                                <td class="px-3 pb-2 pt-0"></td>
                                <td colspan="4" class="px-3 pb-2 pt-0">
                                    <x-tea-aviso-pendiente
                                        multilinea
                                        :matricula="$idMat"
                                        :curso="(int) $curso->Id"
                                        :pendientes="$teaPendientes"
                                    />
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-neutral-500">
                                No hay alumnos con condición 1, 2, 3 o 4 en este curso.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($puedeCargarGrilla && ! $grillaCargada)
        <div class="se-card px-6 py-8 text-center text-sm text-neutral-600">
            Elija fecha y curso y pulse <strong>Cargar listado</strong> para ver los alumnos.
        </div>
    @endif
</div>
