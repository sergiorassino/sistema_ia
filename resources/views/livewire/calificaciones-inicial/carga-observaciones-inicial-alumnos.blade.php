{{-- Carga de observaciones (inicial): alumnos del curso del espacio elegido. --}}
<div class="mx-auto w-full max-w-5xl space-y-6">
    @if (session('success'))
        <div class="flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Inicial</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Carga de observaciones</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    <span class="font-semibold text-white">{{ $materiaNombre }}</span>
                    <span class="block sm:inline sm:before:content-['·'] sm:before:mx-2">{{ $cursoLabel }}</span>
                    <span class="block sm:inline sm:before:content-['·'] sm:before:mx-2">Ciclo {{ schoolCtx()->terlecAno() }}</span>
                </p>
            </div>
            <a href="{{ \App\Support\PortalDocente\CalificacionesInicialPortalDocente::route('observaciones') }}"
               wire:navigate
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver a espacios curriculares
            </a>
        </div>
    </section>

    <div class="se-card overflow-hidden p-0">
        <div class="border-b border-accent-200 bg-accent-50 px-5 py-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Estudiantes del curso</p>
            <p class="text-sm text-neutral-600">
                Elija un alumno para cargar las observaciones de las etapas 1 y 2 en este espacio curricular.
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
                        <tr class="hover:bg-accent-50/60" wire:key="obs-inicial-matricula-{{ $mat->id }}">
                            <td class="py-3 pl-5 pr-2 align-middle font-medium text-neutral-800">
                                {{ trim((string) ($mat->legajo?->nombre_completo ?? '')) === '' ? '—' : $mat->legajo->nombre_completo }}
                            </td>
                            <td class="py-3 pl-2 pr-5 text-right align-middle">
                                <a href="{{ \App\Support\PortalDocente\CalificacionesInicialPortalDocente::route('observaciones.carga', ['materia' => $idMateria, 'matricula' => $mat->id]) }}"
                                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    Cargar observaciones
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
</div>
