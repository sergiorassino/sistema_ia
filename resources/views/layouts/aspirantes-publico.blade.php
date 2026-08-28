<!DOCTYPE html>
<html lang="es" class="h-full bg-[#F4F8F9]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? 'Registro de aspirante' }} — {{ config('app.name') }}</title>
    @include('layouts.partials.favicon')
    @include('layouts.partials.pwa')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full">

<div class="min-h-screen flex flex-col">
    <header class="bg-white border-b border-[#C1D7DA]">
        <div class="mx-auto max-w-4xl px-4 py-4 flex items-center gap-4">
            <img src="{{ schoolLogoUrl() ?: asset('img/3.png') }}" alt="" width="56" height="56" class="object-contain">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Inscripción de aspirantes</p>
                <h1 class="text-lg font-bold text-neutral-900 truncate">{{ config('app.name') }}</h1>
            </div>
        </div>
    </header>

    <main class="flex-1 px-4 py-8">
        <div class="mx-auto max-w-3xl">
            @if (isset($slot) && ! $slot->isEmpty())
                {{ $slot }}
            @else
                @yield('content')
            @endif
        </div>
    </main>

    <footer class="border-t border-[#C1D7DA] bg-white">
        <div class="mx-auto max-w-4xl px-4 py-4 text-xs text-neutral-500">
            Para consultas, comunicate con el colegio.
        </div>
    </footer>
</div>

@include('layouts.partials.livewire-scripts')
</body>
</html>
