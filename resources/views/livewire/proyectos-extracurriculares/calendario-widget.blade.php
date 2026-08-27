<div>
@if ($visible)
<section class="se-dash-cal-panel" aria-labelledby="dash-cal-heading">
    <div class="flex flex-col gap-4 border-b border-[#C1D7DA]/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div class="min-w-0">
            <h2 id="dash-cal-heading" class="text-lg font-bold tracking-tight text-neutral-900">
                Calendario escolar
            </h2>
            <p class="mt-0.5 text-sm text-neutral-600">
                Próximas actividades extracurriculares aprobadas
            </p>
        </div>
        <a href="{{ route($rutaCalendario) }}"
           class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#40848D] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Abrir calendario
        </a>
    </div>

    <div class="p-5 sm:p-6">
        @if (! $tablasOk || $eventos->isEmpty())
            <p class="py-4 text-center text-sm text-neutral-500">
                No hay actividades próximas en el calendario.
            </p>
        @else
            <ul class="divide-y divide-accent-200/80">
                @foreach ($eventos as $f)
                    @php
                        $act = $f->actividad;
                        $d = $f->fecha?->format('d/m/Y') ?? '—';
                        $ini = \App\Support\ProyectosExtracurriculares\ExtActividadesService::formatearHora((string) ($f->hora_inicio ?? ''));
                        $fin = \App\Support\ProyectosExtracurriculares\ExtActividadesService::formatearHora((string) ($f->hora_fin ?? ''));
                        $hora = trim($ini.($ini !== '' && $fin !== '' ? '–' : '').$fin);
                    @endphp
                    <li class="flex flex-col gap-0.5 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-neutral-900">{{ $act?->nombre ?? 'Actividad' }}</p>
                            <p class="text-xs text-neutral-500">{{ $act?->lugar ?: 'Lugar no informado' }}</p>
                        </div>
                        <p class="shrink-0 text-sm font-semibold tabular-nums text-primary-700">
                            {{ $d }}{{ $hora !== '' ? ' · '.$hora : '' }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
@endif
</div>
