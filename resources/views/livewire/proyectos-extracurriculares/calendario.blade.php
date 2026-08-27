<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Calendario</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Calendario escolar</h2>
                    <p class="text-sm text-white/80">{{ schoolCtx()->nivelNombre() }} · {{ schoolCtx()->terlecAno() }} · Actividades aprobadas</p>
                </div>
                @if ($rutaIndexProyectos)
                    <a href="{{ route($rutaIndexProyectos) }}"
                       class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-50">
                        Proyectos
                    </a>
                @endif
            </div>
        </section>

        @if (! $tablasOk)
            <div class="se-card px-5 py-8 text-center text-sm text-neutral-600">{{ $mensajeTabla }}</div>
        @else
            <div class="se-toolbar flex-wrap">
                <div class="flex flex-wrap gap-2">
                    @foreach (['mes' => 'Mes', 'semana' => 'Semana', 'dia' => 'Día'] as $key => $label)
                        <button type="button" wire:click="$set('vista', '{{ $key }}')"
                                @class([
                                    'rounded-xl px-4 py-2 text-sm font-semibold transition',
                                    'bg-primary-600 text-white shadow-sm' => $vista === $key,
                                    'bg-white text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50' => $vista !== $key,
                                ])>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="anterior"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50"
                            aria-label="Anterior">‹</button>
                    <p class="min-w-[12rem] text-center text-sm font-semibold text-neutral-800">{{ $tituloPeriodo }}</p>
                    <button type="button" wire:click="siguiente"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50"
                            aria-label="Siguiente">›</button>
                    <button type="button" wire:click="irHoy"
                            class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50">
                        Hoy
                    </button>
                </div>
            </div>

            <div class="se-card overflow-hidden p-3 sm:p-5">
                @if ($vista === 'mes')
                    <div class="se-cal-mes">
                        <div class="se-cal-mes-head">
                            @foreach ($nombresDias as $nd)
                                <div>{{ $nd }}</div>
                            @endforeach
                        </div>
                        <div class="se-cal-mes-grid">
                            @foreach ($celdasMes as $celda)
                                <div @class([
                                    'se-cal-celda',
                                    'se-cal-celda--fuera' => $celda['fuera'],
                                    'se-cal-celda--hoy' => $celda['hoy'],
                                ])>
                                    <button type="button"
                                            wire:click="irADia('{{ $celda['ymd'] }}')"
                                            class="se-cal-dia-num">
                                        {{ $celda['dia'] }}
                                    </button>
                                    <div class="se-cal-eventos">
                                        @foreach ($celda['eventos'] as $ev)
                                            <button type="button"
                                                    wire:click="verDetalle({{ $ev['id'] }})"
                                                    class="se-cal-chip"
                                                    title="{{ $ev['nombre'] }}">
                                                @if ($ev['hora'] !== '')
                                                    <span class="se-cal-chip-hora">{{ $ev['hora'] }}</span>
                                                @endif
                                                <span class="truncate">{{ $ev['nombre'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif ($vista === 'semana')
                    <div class="se-cal-semana">
                        @foreach ($diasSemana as $dia)
                            <div @class(['se-cal-semana-col', 'se-cal-celda--hoy' => $dia['hoy']])>
                                <button type="button" wire:click="irADia('{{ $dia['ymd'] }}')" class="se-cal-semana-titulo">
                                    {{ $dia['label'] }}
                                </button>
                                <div class="space-y-2 p-2">
                                    @forelse ($dia['eventos'] as $ev)
                                        <button type="button" wire:click="verDetalle({{ $ev['id'] }})" class="se-cal-chip se-cal-chip--block">
                                            @if ($ev['hora'] !== '')
                                                <span class="se-cal-chip-hora">{{ $ev['hora'] }}</span>
                                            @endif
                                            <span>{{ $ev['nombre'] }}</span>
                                            @if ($ev['lugar'] !== '')
                                                <span class="block text-[10px] font-normal text-primary-800/80">{{ $ev['lugar'] }}</span>
                                            @endif
                                        </button>
                                    @empty
                                        <p class="px-1 py-4 text-center text-xs text-neutral-400">Sin actividades</p>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="space-y-3">
                        @forelse ($eventosDia as $ev)
                            <button type="button" wire:click="verDetalle({{ $ev['id'] }})"
                                    class="flex w-full flex-col gap-1 rounded-2xl border border-accent-200 bg-white p-4 text-left transition hover:border-primary-500 hover:shadow-sm">
                                <p class="text-base font-bold text-neutral-900">{{ $ev['nombre'] }}</p>
                                <p class="text-sm text-neutral-600">
                                    {{ $ev['hora'] !== '' ? $ev['hora'] : 'Horario no informado' }}
                                    @if ($ev['lugar'] !== '')
                                        · {{ $ev['lugar'] }}
                                    @endif
                                </p>
                            </button>
                        @empty
                            <p class="py-10 text-center text-sm text-neutral-500">No hay actividades aprobadas en este día.</p>
                        @endforelse
                    </div>
                @endif
            </div>
        @endif
    </div>

    @teleport('body')
        @if ($detalle)
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog" aria-modal="true" aria-labelledby="ext-cal-detalle-title">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarDetalle"></div>
                <div class="relative z-10 my-auto flex w-full max-w-2xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                    <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                        <h3 id="ext-cal-detalle-title" class="text-lg font-bold text-neutral-900">Detalle del proyecto</h3>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                        @include('livewire.proyectos-extracurriculares.partials.detalle-actividad', ['actividad' => $detalle])
                    </div>
                    <div class="shrink-0 border-t border-accent-200 bg-accent-50 px-5 py-3 text-right">
                        <button type="button" wire:click="cerrarDetalle"
                                class="inline-flex items-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endteleport
</div>
