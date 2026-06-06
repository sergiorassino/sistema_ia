@php use App\Support\ComunicacionesRutasGestion; @endphp
<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Comunicaciones</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Revisión de bandejas</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
            </div>

            <a href="{{ ComunicacionesRutasGestion::route('index') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver a mi bandeja
            </a>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="se-section-title">Usuario y filtros</p>
            <p class="mt-1 text-sm text-neutral-600">
                Por defecto se listan todos los comunicados del colegio en el ciclo elegido (envíos a familias, preceptores y demás).
                Opcionalmente filtre por un usuario para ver solo su bandeja.
            </p>
        </div>

        <div class="space-y-6 border-t border-accent-100 bg-accent-50/30 p-5 sm:p-6">
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <label for="prof-search" class="form-label">Buscar usuario (profesor/a, personal o estudiante)</label>
                    <div class="relative mt-1.5">
                        <input id="prof-search"
                               type="text"
                               wire:model.live.debounce.250ms="profesorSearch"
                               placeholder="Apellido, nombre o DNI…"
                               class="form-input" />
                        @if (! empty($profesorResults))
                            <div class="absolute z-20 mt-2 max-h-48 w-full overflow-y-auto rounded-2xl border border-accent-200 bg-white shadow-lg">
                                @foreach ($profesorResults as $p)
                                    <button type="button"
                                            wire:click="selectUsuario(@js($p['tipo']), {{ (int) $p['id'] }}, @js($p['label']))"
                                            class="block w-full border-b border-accent-100 px-3 py-2.5 text-left text-sm transition last:border-b-0 hover:bg-accent-50">
                                        <span class="font-semibold text-neutral-900">{{ $p['label'] }}</span>
                                        <span class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-neutral-400">
                                            {{ ($p['tipo'] ?? '') === 'estudiante' ? 'Estudiante' : 'Personal' }}
                                        </span>
                                        @if (! empty($p['dni']))
                                            <span class="ml-1 text-xs text-neutral-400">DNI {{ $p['dni'] }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-neutral-500">
                        <span>
                            <span class="font-semibold text-neutral-700">Viendo:</span>
                            @if ($idProfesorObjetivo || $idLegajoObjetivo)
                                {{ $profesorObjetivoLabel ?? ('ID ' . ($idProfesorObjetivo ?: $idLegajoObjetivo)) }}
                                @if ($idLegajoObjetivo)
                                    <span class="text-neutral-400">(estudiante)</span>
                                @endif
                            @else
                                Todos los comunicados institucionales
                            @endif
                        </span>
                        @if ($idProfesorObjetivo || $idLegajoObjetivo)
                            <button type="button"
                                    wire:click="limpiarFiltroProfesor"
                                    class="font-semibold text-primary-700 underline-offset-2 hover:underline">
                                Ver todos
                            </button>
                        @endif
                    </div>
                </div>

                <div>
                    <label for="dir" class="form-label">Dirección</label>
                    <select id="dir" wire:model.live="direccion" class="form-select mt-1.5">
                        <option value="todos">Todos</option>
                        <option value="recibidos">Recibidos</option>
                        <option value="enviados">Enviados</option>
                    </select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="periodo" class="form-label">Año lectivo</label>
                    <select id="periodo" wire:model.live="periodo" class="form-select mt-1.5">
                        <option value="actual">Actual</option>
                        <option value="historico">Toda la historia</option>
                    </select>
                </div>
            </div>

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
    </div>

    <div class="mt-5 space-y-3">
        @forelse ($hilos as $hilo)
            @php
                $noLeidos = (int) $hilo->no_leidos;
                $tieneNoLeidos = $noLeidos > 0;
                $esEnviados = ((string) ($hilo->direccion ?? '')) === 'enviado';
                $tieneConversacion = ((int) ($hilo->mensajes_count ?? 0)) > 1;
                $nombresDest = $esEnviados
                    ? array_values(array_filter(explode('||', (string) ($hilo->destinatarios_nombres_concat ?? ''))))
                    : [];
                $nombresDocDest = $esEnviados
                    ? array_values(array_filter(explode('||', (string) ($hilo->destinatarios_doc_nombres_concat ?? ''))))
                    : [];
                $cursoEnvioLabel = $esEnviados ? trim((string) ($hilo->curso_envio_label ?? '')) : '';
                $cntFamilias = $esEnviados ? (int) ($hilo->destinatarios_familia_count ?? 0) : 0;
                $maxNombresLista = 24;
                $esDocentesInterno = \App\Models\ComHilo::inferirEsComunicacionInternaDocentesDesdeDatos(
                    isset($hilo->scope) ? (string) $hilo->scope : null,
                    (int) ($hilo->cuerpo_inicial_id ?? 0)
                );
                $cursosDest = [];
                if ($esEnviados && in_array($hilo->scope, ['curso', 'varios_cursos'], true)) {
                    $rawCursos = $hilo->cursos_envio ?? null;
                    if ($rawCursos !== null && $rawCursos !== '' && $rawCursos !== 'null') {
                        $decoded = is_string($rawCursos) ? json_decode($rawCursos, true) : $rawCursos;
                        if (is_array($decoded)) {
                            foreach ($decoded as $row) {
                                if (is_array($row) && isset($row['label']) && trim((string) $row['label']) !== '') {
                                    $cursosDest[] = ['label' => trim((string) $row['label'])];
                                }
                            }
                        }
                    }
                    if (count($cursosDest) === 0 && $cursoEnvioLabel !== '') {
                        $cursosDest[] = ['label' => $cursoEnvioLabel];
                    }
                }

                $deNombre = trim((string) ($hilo->cuerpo_inicial_nombre ?? ''));
                $deVinculo = trim((string) ($hilo->cuerpo_inicial_vinculo ?? ''));
                $remitenteInst = trim((string) ($hilo->remitente_institucional ?? ''));
                if ($esEnviados && $remitenteInst !== '') {
                    $deLabel = $remitenteInst;
                } else {
                    $deLabel = $deNombre !== '' ? $deNombre : ((string) ($hilo->cuerpo_inicial_tipo ?? '') === 'profesor' ? 'Personal escolar' : 'Familia');
                }
                if ($deVinculo !== '' && $deNombre !== '') {
                    $deLabel .= ' ('.$deVinculo.')';
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
                    if (in_array($hilo->scope, ['curso', 'varios_cursos'], true)) {
                        $labelsCursos = array_values(array_filter(array_map(fn ($c) => trim((string) ($c['label'] ?? '')), $cursosDest)));
                        $paraLabel = count($labelsCursos) > 0 ? implode(' · ', $labelsCursos) : 'Cursos';
                    } elseif ($hilo->scope === 'colegio') {
                        $paraLabel = 'Todo el colegio';
                    } elseif ($esDocentesInterno) {
                        $paraLabel = count($nombresDocDest) > 0 ? implode(' · ', array_slice($nombresDocDest, 0, $maxNombresLista)) : 'Docentes';
                        if (count($nombresDocDest) > $maxNombresLista) {
                            $paraLabel .= ' · …';
                        }
                    } else {
                        $paraLabel = count($nombresDest) > 0 ? implode(' · ', array_slice($nombresDest, 0, $maxNombresLista)) : ($cntFamilias > 0 ? ($cntFamilias.' '.($cntFamilias === 1 ? 'familia' : 'familias')) : '—');
                        if (count($nombresDest) > $maxNombresLista) {
                            $paraLabel .= ' · …';
                        }
                    }
                }
            @endphp

            <button type="button"
                    wire:click="abrirHilo({{ (int) $hilo->id }})"
                    wire:key="hilo-rev-{{ (int) $hilo->id }}"
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
                        @php
                            $soloInformativoBandeja = ($esDocentesInterno
                                    && isset($hilo->docentes_permite_respuestas)
                                    && $hilo->docentes_permite_respuestas !== null
                                    && (int) $hilo->docentes_permite_respuestas === 0)
                                || (! $esDocentesInterno && ! ($hilo->familia_puede_responder ?? true));
                        @endphp
                        @if ($hilo->creado_por_tipo === 'profesor' && $soloInformativoBandeja)
                            <span class="inline-flex shrink-0 items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900">
                                Solo informativo
                            </span>
                        @endif
                    </div>
                    <div class="mt-1 grid min-w-0 grid-cols-[16rem,1fr] items-center gap-x-3 gap-y-1 text-sm">
                        <div @class([
                            'flex min-w-0 items-center gap-2',
                            'justify-end text-right' => ! $esEnviados,
                        ])>
                            @if ($esEnviados)
                                @if (! $idProfesorObjetivo && ! $idLegajoObjetivo && $deLabel !== '')
                                    <span class="shrink-0 text-xs font-semibold uppercase tracking-[0.12em] text-neutral-500">De:</span>
                                    <span class="min-w-0 max-w-[9rem] truncate text-neutral-600 sm:max-w-[12rem]">{{ $deLabel }}</span>
                                    <span class="shrink-0 text-neutral-400">·</span>
                                @endif
                                <span class="shrink-0 text-xs font-semibold uppercase tracking-[0.12em] text-neutral-500">Para:</span>
                                <span class="min-w-0 truncate text-neutral-700">{{ $paraLabel }}</span>
                            @else
                                <span class="shrink-0 text-xs font-semibold uppercase tracking-[0.12em] text-neutral-500">De:</span>
                                <span class="min-w-0 truncate text-neutral-700">{{ $deLabel }}</span>
                            @endif
                        </div>
                        <div @class([
                            'flex min-w-0 items-center gap-2',
                            'justify-end text-right' => ! $esEnviados,
                        ])>
                            <span class="shrink-0 text-neutral-400">·</span>
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
                        <p class="text-sm font-semibold text-neutral-800">Sin resultados</p>
                        <p class="mt-1 text-sm text-neutral-600">No hay hilos con este filtro.</p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</div>

