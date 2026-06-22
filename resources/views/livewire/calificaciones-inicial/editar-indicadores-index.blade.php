{{-- Editar indicadores (inicial): espacios curriculares agrupados por curso/sala. --}}
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
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Editar indicadores</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <a href="{{ \App\Support\PortalDocente\CalificacionesInicialPortalDocente::urlInicio() }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al panel
            </a>
        </div>
    </section>

    <div class="se-card overflow-hidden p-0">
        <div class="border-b border-accent-200 bg-accent-50 px-5 py-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Espacios curriculares</p>
            <p class="text-sm text-neutral-600">
                Elija un espacio curricular para definir o actualizar los indicadores de cada período.
            </p>
        </div>

        @forelse ($grupos as $grupo)
            @php
                $curso = $grupo['curso'];
                $materias = $grupo['materias'];
            @endphp
            <div class="border-b border-accent-100 last:border-b-0" wire:key="indic-inicial-curso-{{ $curso->Id }}">
                <div class="bg-accent-50/80 px-5 py-2.5">
                    <p class="text-sm font-semibold text-primary-800">{{ $curso->nombreParaListado() }}</p>
                </div>
                <div class="flex w-full justify-center overflow-x-auto px-4 py-3 se-grid-angosta-wrap">
                    <table class="se-grid-pocos-campos w-max divide-y divide-accent-200 text-sm">
                        <thead class="bg-white">
                            <tr>
                                <th scope="col" class="py-2 pl-4 pr-3 text-left text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Espacio curricular</th>
                                <th scope="col" class="py-2 pl-3 pr-4 text-right text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-100 bg-white">
                            @foreach ($materias as $m)
                                <tr class="hover:bg-accent-50/60" wire:key="indic-inicial-mat-{{ $m->id }}">
                                    <td class="py-3 pl-4 pr-3 align-middle font-medium text-neutral-800">
                                        {{ $m->materia }}
                                    </td>
                                    <td class="py-3 pl-3 pr-4 text-right align-middle">
                                        <a href="{{ \App\Support\PortalDocente\CalificacionesInicialPortalDocente::route('indicadores.materia', ['materia' => $m->id]) }}"
                                           class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                            Editar indicadores
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <p class="px-5 py-10 text-center text-sm text-neutral-500">
                No hay espacios curriculares cargados para este ciclo lectivo. Defínalos en Gestión de asignaturas del año.
            </p>
        @endforelse
    </div>
</div>
