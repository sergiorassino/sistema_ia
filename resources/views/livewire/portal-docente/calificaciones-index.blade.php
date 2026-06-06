{{-- Menú de Docentes: materias a cargo (ppc) — nivel secundario. --}}
<div class="mx-auto w-full max-w-6xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Portal docente · Secundario</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Calificaciones</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <a href="{{ route('portalDocente.home') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al inicio
            </a>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        @if (count($materias) === 0)
            <div class="px-5 py-10 text-center">
                <p class="text-sm text-neutral-600">
                    No tiene materias asignadas en este ciclo lectivo.
                </p>
                <p class="mt-2 text-xs text-neutral-500">
                    La asignación se gestiona desde Secretaría (profesores por materia).
                </p>
            </div>
        @else
            <style>
                /* ~30 mm entre columnas; tabla centrada en el área de contenido */
                table.se-portal-calif-materias-grid th,
                table.se-portal-calif-materias-grid td {
                    padding-top: 0.5rem;
                    padding-bottom: 0.5rem;
                    padding-left: 0;
                    padding-right: 20mm;
                }
                table.se-portal-calif-materias-grid th:last-child,
                table.se-portal-calif-materias-grid td:last-child {
                    padding-right: 0;
                }
            </style>
            <div class="flex w-full justify-center overflow-x-auto px-3 py-4 sm:px-4">
                <table class="se-portal-calif-materias-grid w-auto table-auto text-sm">
                    <thead class="border-b border-accent-200 bg-accent-50">
                        <tr>
                            <th class="text-left text-[10px] font-semibold uppercase tracking-wider text-neutral-600">Materia</th>
                            <th class="text-left text-[10px] font-semibold uppercase tracking-wider text-neutral-600">Curso</th>
                            <th class="text-left text-[10px] font-semibold uppercase tracking-wider text-neutral-600">Año lectivo</th>
                            <th class="text-right text-[10px] font-semibold uppercase tracking-wider text-neutral-600">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100">
                        @foreach ($materias as $fila)
                            <tr class="transition-colors hover:bg-accent-50/60">
                                <td class="text-neutral-700">
                                    {{ $fila->materia !== '' ? $fila->materia : ('Materia '.$fila->idMateria) }}
                                </td>
                                <td class="whitespace-nowrap font-medium text-neutral-800">
                                    {{ $fila->cursoLabel }}
                                </td>
                                <td class="whitespace-nowrap font-mono text-neutral-700">
                                    {{ $fila->anoLectivo ?? schoolCtx()->terlecAno() ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('portalDocente.calificaciones.carga', ['curso' => $fila->idCurso, 'materia' => $fila->idMateria]) }}"
                                       class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                        Calificaciones
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
