<!DOCTYPE html>
<html lang="es" class="h-full bg-[#F4F8F9]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $programasColegioNombre = schoolNombre();
        $programasColegioLogo = schoolLogoUrl() ?: entoInstitutionalLogoUrlFallback() ?: asset('img/3.png');
    @endphp
    <title>{{ $pageTitle ?? 'Programas de examen' }} — {{ $programasColegioNombre }}</title>
    @include('layouts.partials.favicon')
    @include('layouts.partials.pwa', ['incluirManifiestoPwa' => false])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full">

<div class="min-h-screen flex flex-col">
    <header class="bg-white border-b border-[#C1D7DA]">
        <div class="mx-auto max-w-5xl px-4 py-4 flex items-center gap-4">
            <img src="{{ $programasColegioLogo }}" alt="{{ $programasColegioNombre }}"
                 width="56" height="56" class="h-14 w-14 shrink-0 object-contain">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Programas de examen</p>
                <h1 class="text-lg font-bold text-neutral-900 truncate">{{ $programasColegioNombre }}</h1>
            </div>
        </div>
    </header>

    <main class="flex-1 px-4 py-8">
        <div class="mx-auto max-w-5xl">
            {{ $slot }}
        </div>
    </main>

    <footer class="border-t border-[#C1D7DA] bg-white">
        <div class="mx-auto max-w-5xl px-4 py-4 text-xs text-neutral-500">
            Para consultas, comunicate con el colegio.
        </div>
    </footer>
</div>

@include('layouts.partials.livewire-scripts')
</body>
</html>
