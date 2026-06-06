{{-- Carga manual de calificaciones (primario): curso → alumno. --}}
<div class="mx-auto w-full max-w-5xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Primario</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Carga de calificaciones</h2>
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
        <div class="min-w-0 flex-1">
            <label for="se-calif-prim-curso" class="form-label">Curso</label>
            <select id="se-calif-prim-curso" wire:model.live="cursoId" class="form-select mt-1.5 w-full max-w-xl">
                <option value="">— Seleccione —</option>
                @foreach ($cursos as $c)
                    <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($cursoId)
        <div class="se-card overflow-hidden p-0">
            <div class="border-b border-accent-200 bg-accent-50 px-5 py-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Estudiantes del curso</p>
                <p class="text-sm text-neutral-600">
                    Elija un alumno para cargar etapas 1 y 2, apreciación final y observaciones (mismo criterio que el sistema anterior).
                </p>
            </div>
            <div class="flex w-full justify-center overflow-x-auto px-4 pb-4 pt-1">
                <table class="w-max max-w-full table-auto divide-y divide-accent-200 text-sm">
                    <thead class="bg-white">
                        <tr>
                            <th scope="col" class="py-3 pl-5 pr-2 text-left text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Apellido y nombre</th>
                            <th scope="col" class="py-3 pl-2 pr-5 text-right text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100 bg-white">
                        @forelse ($matriculas as $mat)
                            <tr class="hover:bg-accent-50/60" wire:key="calif-prim-mat-{{ $mat->id }}">
                                <td class="py-3 pl-5 pr-2 align-middle font-medium text-neutral-800">
                                    {{ trim((string) ($mat->legajo?->nombre_completo ?? '')) === '' ? '—' : $mat->legajo->nombre_completo }}
                                </td>
                                <td class="py-3 pl-2 pr-5 text-right align-middle">
                                    <a href="{{ route('calificacionesPrimario.carga.alumno', ['matricula' => $mat->id, 'curso' => $cursoId]) }}"
                                       class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                        Cargar calificaciones
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-5 py-10 text-center text-sm text-neutral-500">
                                    No hay matrículas regulares en este curso para el ciclo lectivo actual.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
