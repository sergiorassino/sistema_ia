@extends('layouts.alumno')

@section('pageTitle', 'Ficha de matrícula')

@section('content')
<div class="se-page max-w-3xl mx-auto">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Legajo del estudiante</p>
                <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Imprimir ficha de matrícula</h2>
            </div>
        </div>
    </section>

    @if ($bloqueada)
        <section class="se-card p-5">
            <p class="text-sm leading-relaxed text-neutral-800">{{ $mensaje }}</p>
        </section>
    @else
        <section class="se-card p-4 sm:p-5" aria-labelledby="ficha-elegir-anio">
            <p id="ficha-elegir-anio" class="se-section-title mb-4">Ciclo lectivo</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($opciones as $opcion)
                    <a href="{{ se_route_url('alumnos.ficha-matricula', ['ciclo' => $opcion['ciclo']]) }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="flex flex-col items-center justify-center rounded-2xl border border-accent-200 bg-white px-4 py-6 text-center shadow-sm transition hover:border-primary-500 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">{{ $opcion['etiqueta'] }}</span>
                        <span class="mt-1 text-2xl font-bold tabular-nums text-primary-700">{{ $opcion['anio'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
