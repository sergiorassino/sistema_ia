<div class="mx-auto w-full max-w-4xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Horarios</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Configuración de horarios</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    Turnos activos, días de clase y horario reloj por hora cátedra ({{ schoolCtx()->nivelNombre() }}).
                </p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                Volver al panel
            </a>
        </div>
    </section>

    @if (session('horarios_ok'))
        <div class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-900">
            {{ session('horarios_ok') }}
        </div>
    @endif

    <div class="se-card px-5 py-5 space-y-5">
        <div>
            <p class="se-section-title">Turnos del establecimiento</p>
            <p class="mt-1 text-sm text-neutral-600">
                Marque las jornadas del establecimiento (mañana, tarde, vespertino, noche, etc.).
                El turno compuesto «Mañana/Tarde» no se lista aquí: se asigna solo al curso en <strong>ABM → Cursos</strong>.
                Cada turno activo tiene 10 horas de clase en el reloj.
            </p>
            <div class="mt-3 flex flex-wrap gap-4">
                @foreach ($turnosClaseEstablecimiento as $turno)
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-neutral-800"
                           wire:key="hc-turno-{{ $turno->id }}">
                        <input type="checkbox" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                               wire:model.live="turnosMarcados.{{ $turno->id }}">
                        {{ $turno->nombre }}
                    </label>
                @endforeach
            </div>
            @error('turnosMarcados') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <p class="se-section-title">Días de clase</p>
            <div class="mt-3 flex flex-wrap gap-3">
                @foreach ($dias as $id => $label)
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-neutral-800"
                           wire:key="hc-dia-{{ $id }}">
                        <input type="checkbox" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                               wire:model.live="diasMarcados.{{ $id }}">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('diasMarcados') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="button" wire:click="guardarConfig" wire:loading.attr="disabled"
                class="inline-flex items-center rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
            Guardar turnos y días
        </button>
    </div>

    <div class="se-card px-5 py-5 space-y-4">
        <p class="se-section-title">Horario reloj (primera columna del impreso)</p>
        <p class="text-sm text-neutral-600">
            En <code class="rounded bg-accent-100 px-1">reloj</code> cada turno tiene sus filas con
            <code class="rounded bg-accent-100 px-1">orden</code> de <strong>1 a 10</strong> y el turno se indica con
            <code class="rounded bg-accent-100 px-1">idTurnoClase</code> (no se usan órdenes 11–20 / 21–30 en filas nuevas).
            La carga en <code class="rounded bg-accent-100 px-1">horarios26</code> usa <code class="rounded bg-accent-100 px-1">idHora</code> 1–10 y <code class="rounded bg-accent-100 px-1">idTurnoClase</code> (como <code class="rounded bg-accent-100 px-1">reloj</code> y <code class="rounded bg-accent-100 px-1">cursos</code>).
        </p>
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label for="turno-reloj" class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Turno</label>
                <select id="turno-reloj" wire:model.live="turnoReloj" class="form-select mt-1">
                    @foreach ($turnosActivos as $tid)
                        <option value="{{ $tid }}">{{ \App\Support\HorariosProfesores::nombreTurnoClase($tid) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[320px] text-sm">
                <thead>
                    <tr class="border-b border-accent-200 text-left text-[10px] font-semibold uppercase text-neutral-500">
                        <th class="py-2 pr-4">Hora</th>
                        <th class="py-2">Desde — hasta</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($h = 1; $h <= \App\Support\HorariosProfesores::HORAS_POR_TURNO; $h++)
                        <tr class="border-b border-accent-100">
                            <td class="py-2 pr-4 font-semibold tabular-nums">{{ $h }}º</td>
                            <td class="py-1">
                                <input type="text" maxlength="13"
                                       wire:model="relojHoras.{{ $h }}"
                                       placeholder="08:00-08:40"
                                       class="w-full max-w-xs rounded-lg border border-accent-200 px-2 py-1.5 text-sm">
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        @error('relojHoras') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button type="button" wire:click="guardarReloj" wire:loading.attr="disabled"
                class="inline-flex items-center rounded-xl border border-accent-200 bg-white px-5 py-2.5 text-sm font-semibold text-primary-700 shadow-sm hover:bg-accent-50">
            Guardar horario reloj
        </button>
    </div>

    @if ($mostrarPanelSqlDepuracion)
        <div class="se-card overflow-hidden border-dashed border-primary-400/40 bg-accent-50/60">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-accent-200 px-4 py-2.5">
                <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-600">
                    Consultas SQL · configuración de horarios (referencia)
                </h3>
                <button type="button" wire:click="$set('mostrarPanelSqlDepuracion', false)"
                        class="rounded-lg border border-accent-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-neutral-600 hover:bg-accent-50">
                    Ocultar
                </button>
            </div>
            <pre class="max-h-[28rem] overflow-auto whitespace-pre-wrap break-words px-4 py-3 font-mono text-[10px] leading-relaxed text-neutral-800 sm:text-[11px]">{{ $consultaSqlDepuracion ?? '' }}</pre>
        </div>
    @else
        <div class="flex justify-center">
            <button type="button" wire:click="$set('mostrarPanelSqlDepuracion', true)"
                    class="text-sm font-semibold text-primary-700 hover:underline">
                Mostrar consultas SQL (referencia)
            </button>
        </div>
    @endif

    <div class="flex flex-wrap gap-3">
        @if (tienePermiso(\App\Support\PermisosIaCatalog::HORARIOS))
            <a href="{{ route('horarios.carga') }}" class="text-sm font-semibold text-primary-700 hover:underline">Carga de horarios →</a>
        @endif
        <a href="{{ route('horarios.impresion') }}" class="text-sm font-semibold text-primary-700 hover:underline">Impresión →</a>
    </div>
</div>
