<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Comunicaciones</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Bandeja de comunicados</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ studentCtx()->nivelNombre() }} · Ciclo lectivo {{ studentCtx()->terlecAno() }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <span class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white/85">
                    <span class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">En esta vista</span>
                    <span class="text-xl font-bold tabular-nums">{{ $hilos->count() }}</span>
                </span>
                <a href="{{ route('alumnos.comunicaciones.nuevo') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100 focus:outline-none focus:ring-2 focus:ring-white/60">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nuevo comunicado
                </a>
            </div>
        </div>
    </section>

    @if (session('success'))
        <div class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="se-toolbar space-y-5">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-neutral-500">Estado</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach (['todos' => 'Todos', 'no_leidos' => 'No leídos'] as $val => $label)
                    <button type="button"
                            wire:click="$set('filtro', '{{ $val }}')"
                            @class([
                                'inline-flex cursor-pointer items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-semibold shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
                                'border-primary-500 bg-primary-600 text-white' => $filtro === $val,
                                'border-accent-200 bg-white text-neutral-700 hover:bg-accent-50' => $filtro !== $val,
                            ])>
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="space-y-3">
        @forelse ($hilos as $hilo)
            @php
                $noLeidos = (int) $hilo->no_leidos;
                $tieneNoLeidos = $noLeidos > 0;
                $esEnviados = ((string) ($hilo->direccion ?? '')) === 'enviado';
                $tieneConversacion = ((int) ($hilo->mensajes_count ?? 0)) > 1;
                $maxNombresLista = 24;
                $nombresProf = $esEnviados
                    ? array_values(array_filter(explode('||', (string) ($hilo->destinatarios_prof_nombres_concat ?? ''))))
                    : [];
                $cntProf = $esEnviados ? (int) ($hilo->destinatarios_prof_count ?? 0) : 0;

                $deNombre = trim((string) ($hilo->cuerpo_inicial_nombre ?? ''));
                $deVinculo = trim((string) ($hilo->cuerpo_inicial_vinculo ?? ''));
                $deLabel = $deNombre !== '' ? $deNombre : ((string) ($hilo->cuerpo_inicial_tipo ?? '') === 'profesor' ? 'Personal escolar' : 'Familia');
                if ($deVinculo !== '' && $deNombre !== '' && ($hilo->cuerpo_inicial_tipo ?? '') === 'familia') {
                    $lblVin = \App\Models\ComMensaje::etiquetasVinculo()[$deVinculo] ?? $deVinculo;
                    $deLabel .= ' ('.$lblVin.')';
                }

                $lecturaInicialTotal = $esEnviados ? (int) ($hilo->destinatarios_mensaje_inicial_count ?? 0) : 0;
                $lecturaInicialLeidos = $esEnviados ? (int) ($hilo->destinatarios_mensaje_inicial_leidos ?? 0) : 0;
                $lecturaInicialPendientes = max(0, $lecturaInicialTotal - $lecturaInicialLeidos);
                $lecturaInicialEstado = match (true) {
                    $lecturaInicialTotal <= 0 => '',
                    $lecturaInicialPendientes === 0 => 'leido',
                    $lecturaInicialLeidos === 0 => 'no_leido',
                    default => 'parcial',
                };
                $lecturaInicialEtiqueta = match ($lecturaInicialEstado) {
                    'leido' => $lecturaInicialTotal === 1 ? 'Leído' : "Leído ({$lecturaInicialLeidos}/{$lecturaInicialTotal})",
                    'no_leido' => $lecturaInicialTotal === 1 ? 'Sin leer' : "Sin leer ({$lecturaInicialPendientes}/{$lecturaInicialTotal})",
                    'parcial' => "{$lecturaInicialLeidos}/{$lecturaInicialTotal} leídos",
                    default => '',
                };

                $paraLabel = '';
                if ($esEnviados) {
                    $paraLabel = count($nombresProf) > 0
                        ? implode(' · ', array_slice($nombresProf, 0, $maxNombresLista))
                        : ($cntProf > 0 ? ($cntProf.' '.($cntProf === 1 ? 'destinatario' : 'destinatarios')) : '—');
                    if (count($nombresProf) > $maxNombresLista) {
                        $paraLabel .= ' · …';
                    }
                }
            @endphp
            <button type="button"
                    wire:click="abrirHilo({{ (int) $hilo->id }})"
                    wire:key="hilo-fam-{{ (int) $hilo->id }}"
               @class([
                   'se-card block w-full p-4 text-left transition hover:shadow-md sm:p-5 cursor-pointer',
                   'border-l-4 border-l-primary-600 bg-primary-50/20' => $esEnviados && ! $tieneNoLeidos,
                   'border-l-4 border-l-primary-600 bg-pink-100' => $esEnviados && $tieneNoLeidos,
                   'border-r-4 border-r-accent-300 bg-white' => ! $esEnviados && ! $tieneNoLeidos,
                   'border-r-4 border-r-pink-400 bg-pink-100' => ! $esEnviados && $tieneNoLeidos,
               ])>
                <div class="block w-full min-w-0">
                        <div @class([
                            'flex w-full min-w-0 flex-wrap items-center gap-2',
                            'justify-start' => $esEnviados,
                            'justify-end text-right' => ! $esEnviados,
                        ])>
                            <span class="shrink-0 text-xs text-neutral-400 tabular-nums">
                                {{ $hilo->ultimo_mensaje_at ? \Carbon\Carbon::parse($hilo->ultimo_mensaje_at)->format('d/m/Y H:i') : '' }}
                            </span>
                            <span @class([
                                'inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                                'border-primary-200 bg-primary-50 text-primary-800' => $esEnviados,
                                'border-accent-200 bg-accent-50 text-neutral-700' => ! $esEnviados,
                            ])>
                                {{ $esEnviados ? 'Enviado' : 'Recibido' }}
                            </span>
                            @if ($tieneConversacion)
                                <span class="inline-flex shrink-0 items-center rounded-full border border-primary-200 bg-primary-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary-800">
                                    Ver conversación
                                </span>
                            @endif
                            @if ($tieneNoLeidos)
                                <span class="inline-flex shrink-0 items-center rounded-full border border-pink-400 bg-pink-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-pink-950">
                                    {{ $noLeidos }} no leído{{ $noLeidos > 1 ? 's' : '' }}
                                </span>
                            @endif
                            @if ($esEnviados && $lecturaInicialEtiqueta !== '')
                                <span @class([
                                    'inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                                    'border-primary-200 bg-primary-50 text-primary-800' => $lecturaInicialEstado === 'leido',
                                    'border-amber-200 bg-amber-50 text-amber-900' => $lecturaInicialEstado === 'parcial',
                                    'border-neutral-300 bg-neutral-100 text-neutral-600' => $lecturaInicialEstado === 'no_leido',
                                ])>
                                    {{ $lecturaInicialEtiqueta }}
                                </span>
                            @endif
                            @if ($hilo->creado_por_tipo === 'profesor' && ! ($hilo->familia_puede_responder ?? true))
                                <span class="inline-flex shrink-0 items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900">
                                    Solo informativo
                                </span>
                            @endif
                        </div>
                        <div class="mt-1 grid min-w-0 grid-cols-1 items-center gap-x-3 gap-y-1 text-sm sm:grid-cols-[16rem,1fr]">
                            <div @class([
                                'flex min-w-0 items-center gap-2',
                                'justify-end text-right' => ! $esEnviados,
                            ])>
                                @if ($esEnviados)
                                    <span class="shrink-0 text-xs font-semibold uppercase tracking-[0.12em] text-neutral-500">Para:</span>
                                    <span class="min-w-0 truncate text-neutral-700">{{ $paraLabel }}</span>
                                @else
                                    <span class="shrink-0 text-xs font-semibold uppercase tracking-[0.12em] text-neutral-500">De:</span>
                                    <span class="min-w-0 truncate text-neutral-700">{{ $deLabel }}</span>
                                @endif
                            </div>
                            <div @class([
                                'flex min-w-0 flex-wrap items-center gap-2',
                                'justify-end text-right sm:flex-nowrap' => ! $esEnviados,
                            ])>
                                <span class="hidden shrink-0 text-neutral-400 sm:inline">·</span>
                                <span class="min-w-0 flex-1 truncate font-semibold text-neutral-900">{{ $hilo->asunto }}</span>
                                @if ($hilo->estado === 'cerrado')
                                    <span class="shrink-0 text-xs text-neutral-400">· Cerrado</span>
                                @endif
                            </div>
                        </div>
                </div>
            </button>
        @empty
            <div class="se-card p-10">
                <div class="flex flex-col items-center justify-center gap-3 text-center sm:flex-row sm:text-left">
                    <div class="se-icon-badge h-14 w-14">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-neutral-800">Bandeja vacía</p>
                        <p class="mt-1 text-sm text-neutral-600">
                            No hay comunicados con este filtro.
                        </p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</div>
