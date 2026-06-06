{{-- Historial de calificaciones del alumno (consulta). --}}
<div class="se-cierre-anual-fill">
    <div class="se-cierre-anual-grid">
        <section class="se-hero min-w-0">
            <div class="se-hero-inner !gap-3 !p-4 sm:!p-5">
                <div class="min-w-0 flex-1 space-y-1">
                    <p class="se-eyebrow">Calificaciones · Cierre anual</p>
                    <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Historial de calificaciones</h2>
                    @if (! empty($alumno))
                        <p class="text-sm text-white/90">
                            <span class="font-semibold">{{ $alumno['apellido'] }}, {{ $alumno['nombre'] }}</span>
                            @if (($alumno['dni'] ?? '') !== '')
                                · DNI {{ $alumno['dni'] }}
                            @endif
                            @if (($alumno['curso'] ?? '') !== '')
                                · {{ $alumno['curso'] }}
                            @endif
                            <span class="text-white/70"> · {{ schoolCtx()->nivelNombre() }} · {{ schoolCtx()->terlecAno() ?? '—' }}</span>
                        </p>
                    @endif
                </div>
                <a href="{{ route('calificacionesSecundario.cierreAnual') }}"
                   wire:navigate
                   class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Volver
                </a>
            </div>
        </section>

        <div class="se-card flex min-h-0 min-w-0 flex-col p-0">
            <div class="se-cierre-anual-grilla">
                <div class="se-cierre-anual-head-wrap"
                     data-se-cierre-head>
                    <div class="se-cierre-anual-tabla-wide">
                        <table class="se-cierre-anual-tabla table-fixed">
                            <colgroup>
                                <col style="width:11rem">
                                <col style="width:3.5rem">
                                <col style="width:6rem">
                                <col style="width:10rem">
                                <col style="width:4rem">
                                <col style="width:3rem">
                                <col style="width:3rem">
                                <col style="width:3rem">
                                <col style="width:4rem">
                                <col style="width:3.5rem">
                                <col style="width:8rem">
                                <col style="width:5.5rem">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col" class="text-left">Apellido y nombre</th>
                                    <th scope="col" class="text-left">Año</th>
                                    <th scope="col" class="text-left">Curso</th>
                                    <th scope="col" class="text-left">Materia</th>
                                    <th scope="col" class="text-center">Prom. anual</th>
                                    <th scope="col" class="text-center">Dic</th>
                                    <th scope="col" class="text-center">Feb</th>
                                    <th scope="col" class="text-center">Mes</th>
                                    <th scope="col" class="text-center">Año mat.</th>
                                    <th scope="col" class="text-left">Cond.</th>
                                    <th scope="col" class="text-left">Esc. aprob.</th>
                                    <th scope="col" class="text-center">Estado</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
                <div class="se-cierre-anual-body-wrap"
                     tabindex="0"
                     data-se-cierre-body>
                    <div class="se-cierre-anual-tabla-wide">
                        <table class="se-cierre-anual-tabla table-fixed divide-y divide-accent-100">
                            <colgroup>
                                <col style="width:11rem">
                                <col style="width:3.5rem">
                                <col style="width:6rem">
                                <col style="width:10rem">
                                <col style="width:4rem">
                                <col style="width:3rem">
                                <col style="width:3rem">
                                <col style="width:3rem">
                                <col style="width:4rem">
                                <col style="width:3.5rem">
                                <col style="width:8rem">
                                <col style="width:5.5rem">
                            </colgroup>
                            <tbody class="bg-white">
                                @forelse ($filas as $f)
                                    <tr class="hover:bg-accent-50/60" wire:key="hist-calif-{{ $f['id'] }}">
                                        <td class="whitespace-nowrap font-medium text-neutral-800">{{ $f['apellido'] }}, {{ $f['nombre'] }}</td>
                                        <td class="whitespace-nowrap tabular-nums text-neutral-700">{{ $f['ano_lectivo'] }}</td>
                                        <td class="text-neutral-700">{{ $f['curso'] !== '' ? $f['curso'] : '—' }}</td>
                                        <td class="font-medium text-neutral-800">{{ $f['materia'] }}</td>
                                        <td class="text-center tabular-nums">{{ $f['calif'] !== '' ? $f['calif'] : '—' }}</td>
                                        <td class="text-center tabular-nums">{{ $f['dic'] !== '' ? $f['dic'] : '—' }}</td>
                                        <td class="text-center tabular-nums">{{ $f['feb'] !== '' ? $f['feb'] : '—' }}</td>
                                        <td class="text-center tabular-nums">{{ $f['mes'] !== null && $f['mes'] !== '' ? $f['mes'] : '—' }}</td>
                                        <td class="text-center tabular-nums">{{ $f['ano'] !== null && $f['ano'] !== '' ? $f['ano'] : '—' }}</td>
                                        <td class="text-neutral-600">{{ $f['cond'] !== '' ? $f['cond'] : '—' }}</td>
                                        <td class="truncate text-neutral-600" title="{{ $f['escuapro'] }}">{{ $f['escuapro'] !== '' ? $f['escuapro'] : '—' }}</td>
                                        <td class="text-center">
                                            @php
                                                $apro = (int) ($f['apro'] ?? 0);
                                            @endphp
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                                'bg-emerald-100 text-emerald-900' => $apro === 2,
                                                'bg-amber-100 text-amber-900' => $apro === 1,
                                                'bg-accent-100 text-neutral-700' => $apro === 0,
                                            ])>{{ $f['apro_etiqueta'] }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="!px-5 !py-10 text-center text-sm text-neutral-500">
                                            No hay calificaciones registradas para este alumno en el nivel.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
