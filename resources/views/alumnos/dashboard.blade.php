@extends('layouts.alumno')

@section('pageTitle', 'Inicio')

@section('content')
@php
    $terlecAno = $ctx->terlecAno();
@endphp

<div class="max-w-6xl mx-auto space-y-8">

    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#40848D] via-[#366f76] to-[#333333] text-white shadow-lg shadow-neutral-900/15">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_95%_75%_at_100%_0%,rgba(255,255,255,0.12),transparent_55%)]"
             aria-hidden="true"></div>
        <div class="relative flex flex-col gap-6 p-6 sm:p-8 md:flex-row md:items-center md:justify-between md:gap-8">
            <div class="min-w-0 flex-1 space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/60">Escritorio</p>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight leading-tight truncate" title="{{ $nombreEstudiante }}">
                    Hola, {{ $nombreEstudiante }}
                </h1>
                <p class="text-sm sm:text-base text-white/80 max-w-xl">
                    {{ $nombreInstitucion }} — resumen de su sesión
                    @if ($terlecAno)
                        del ciclo {{ $terlecAno }}.
                    @else
                        .
                    @endif
                </p>
            </div>
            <div class="flex shrink-0 justify-start md:justify-end">
                <div class="rounded-2xl bg-white p-4 shadow-md ring-1 ring-white/20">
                    <img src="{{ $heroLogo }}" alt="" class="h-16 sm:h-20 w-auto max-w-[200px] object-contain">
                </div>
            </div>
        </div>
    </section>

    <section aria-labelledby="alumno-dash-session-heading">
        <h2 id="alumno-dash-session-heading" class="mb-4 text-[11px] font-semibold uppercase tracking-[0.16em] text-neutral-500">
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
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Estudiante</p>
                    <p class="se-dash-session-value text-neutral-900" title="{{ $nombreEstudiante }}">{{ $nombreEstudiante }}</p>
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
                    <p class="se-dash-session-value tabular-nums text-[#40848D]" title="{{ $terlecAno }}">{{ $terlecAno ?? '—' }}</p>
                </div>
            </div>
        </div>
    </section>

    @foreach ($widgets as $widget)
        @include($widget['vista'], $widget['datos'])
    @endforeach

    <section aria-labelledby="alumno-dash-access-heading">
        <h2 id="alumno-dash-access-heading" class="mb-4 text-[11px] font-semibold uppercase tracking-[0.16em] text-neutral-500">
            Accesos rápidos
        </h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($accesos as $acceso)
                <a href="{{ $acceso['url'] }}"
                   @if ($acceso['externo']) target="_blank" rel="noopener noreferrer" @endif
                   class="se-dash-access group">
                    <div class="se-dash-access-icon">
                        @include('alumnos.dashboard.partials.icono-acceso', ['icono' => $acceso['icono']])
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-neutral-900 group-hover:text-primary-700">{{ $acceso['titulo'] }}</p>
                        <p class="mt-1 text-sm text-neutral-600 leading-snug">{{ $acceso['descripcion'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
</div>
@endsection
