@extends($layout ?? 'layouts.app')

@php
    $modoPortalDocente = $modoPortalDocente ?? false;
    $rutaComunicacionesIndex = $modoPortalDocente ? 'portalDocente.comunicaciones.index' : 'comunicaciones.index';
@endphp

@section('pageTitle', schoolNombre())

@section('content')
@php
    $heroLogo = schoolLogoUrl() ?: asset('img/3.png');
    $tenantNombre = schoolNombre();
    $ctx = schoolCtx();
@endphp

<div class="max-w-6xl mx-auto space-y-8">

    {{-- Hero --}}
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#40848D] via-[#366f76] to-[#333333] text-white shadow-lg shadow-neutral-900/15">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_95%_75%_at_100%_0%,rgba(255,255,255,0.12),transparent_55%)]"
             aria-hidden="true"></div>
        <div class="relative flex flex-col gap-6 p-6 sm:p-8 md:flex-row md:items-center md:justify-between md:gap-8">
            <div class="min-w-0 flex-1 space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/60">Panel de inicio</p>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight leading-tight truncate" title="{{ $nombreUsuario }}">
                    Hola, {{ $nombreUsuario }}
                </h1>
                <p class="text-sm sm:text-base text-white/80 max-w-xl">
                    {{ $tenantNombre }} — resumen de su sesión y comunicaciones del ciclo activo.
                </p>
            </div>
            <div class="flex shrink-0 justify-start md:justify-end">
                @include('layouts.partials.logo-institucional', ['url' => $heroLogo, 'context' => 'hero'])
            </div>
        </div>
    </section>

    {{-- Datos de sesión --}}
    <section aria-labelledby="dash-session-heading">
        <h2 id="dash-session-heading" class="mb-4 text-[11px] font-semibold uppercase tracking-[0.16em] text-neutral-500">
            Datos de la sesión
        </h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,0.72fr)]">
            <div class="se-dash-session-card">
                <div class="se-dash-session-icon">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div class="se-dash-session-body">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Usuario</p>
                    <div class="mt-1 space-y-0.5">
                        <p class="se-dash-session-value text-neutral-900" title="{{ $nombreUsuario }}">{{ $nombreUsuario }}</p>
                        <p class="truncate text-sm font-semibold tabular-nums text-neutral-700" title="DNI {{ $dniUsuario }}">DNI {{ $dniUsuario }}</p>
                    </div>
                </div>
            </div>
            <div class="se-dash-session-card">
                <div class="se-dash-session-icon">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/>
                    </svg>
                </div>
                <div class="se-dash-session-body">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Nivel</p>
                    <p class="se-dash-session-value text-neutral-900" title="{{ $ctx->nivelNombre() }}">{{ $ctx->nivelNombre() }}</p>
                </div>
            </div>
            <div class="se-dash-session-card">
                <div class="se-dash-session-icon">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="se-dash-session-body">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Año lectivo</p>
                    <p class="se-dash-session-value tabular-nums text-[#40848D]" title="{{ $ctx->terlecAno() }}">{{ $ctx->terlecAno() }}</p>
                </div>
            </div>
        </div>
    </section>

    @if ($bandeja !== null)
        @php
            $urgenteRecibidos = (int) ($bandeja['mensajes_no_leidos'] ?? 0) > 0;
            $urgenteEnviados = (int) ($bandeja['destinatarios_sin_leer'] ?? 0) > 0;
        @endphp
        <section class="se-dash-mail-panel" aria-labelledby="dash-mail-heading">
            <div class="flex flex-col gap-4 border-b border-[#C1D7DA]/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="min-w-0">
                    <h2 id="dash-mail-heading" class="text-lg font-bold tracking-tight text-neutral-900">
                        Mi bandeja de comunicados
                    </h2>
                    <p class="mt-0.5 text-sm text-neutral-600">
                        Ciclo {{ $ctx->terlecAno() }} · {{ (int) ($bandeja['hilos_total'] ?? 0) }} hilos en bandeja
                    </p>
                </div>
                <a href="{{ route($rutaComunicacionesIndex) }}"
                   class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#40848D] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Abrir bandeja
                </a>
            </div>

            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-4">
                <a href="{{ route($rutaComunicacionesIndex, ['filtro' => 'no_leidos']) }}"
                   @class([
                       'se-dash-mail-stat group',
                       'se-dash-mail-stat--urgent' => $urgenteRecibidos,
                   ])>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 group-hover:text-neutral-600">
                        Mensajes no leídos
                    </p>
                    <p @class([
                        'mt-2 text-3xl font-bold tabular-nums',
                        'text-pink-700' => $urgenteRecibidos,
                        'text-[#40848D]' => ! $urgenteRecibidos,
                    ])>{{ (int) ($bandeja['mensajes_no_leidos'] ?? 0) }}</p>
                    <p class="mt-1 text-xs text-neutral-600">
                        {{ (int) ($bandeja['hilos_con_no_leidos'] ?? 0) }} hilo{{ (int) ($bandeja['hilos_con_no_leidos'] ?? 0) === 1 ? '' : 's' }} con pendientes
                    </p>
                    @if ($urgenteRecibidos)
                        <span class="se-dash-urgent-badge mt-3">Requiere atención</span>
                    @endif
                </a>

                <a href="{{ route($rutaComunicacionesIndex) }}"
                   @class([
                       'se-dash-mail-stat group',
                       'se-dash-mail-stat--warn' => $urgenteEnviados,
                   ])>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 group-hover:text-neutral-600">
                        Sin lectura del destinatario
                    </p>
                    <p @class([
                        'mt-2 text-3xl font-bold tabular-nums',
                        'text-amber-800' => $urgenteEnviados,
                        'text-[#40848D]' => ! $urgenteEnviados,
                    ])>{{ (int) ($bandeja['destinatarios_sin_leer'] ?? 0) }}</p>
                    <p class="mt-1 text-xs text-neutral-600">
                        Destinatarios que aún no abrieron sus envíos
                    </p>
                    @if ($urgenteEnviados)
                        <span class="se-dash-warn-badge mt-3">Seguimiento sugerido</span>
                    @endif
                </a>

                <div class="se-dash-mail-stat">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Envíos pendientes</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-neutral-800">
                        {{ (int) ($bandeja['hilos_enviados_pendientes_lectura'] ?? 0) }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-600">Hilos enviados con lecturas pendientes</p>
                </div>

                <div class="se-dash-mail-stat se-dash-mail-stat--calm">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Total en bandeja</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-neutral-800">{{ (int) ($bandeja['hilos_total'] ?? 0) }}</p>
                    <p class="mt-1 text-xs text-neutral-600">Recibidos y enviados del año lectivo</p>
                </div>
            </div>
        </section>
    @endif
</div>
@endsection
