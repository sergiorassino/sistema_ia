<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ingresar — {{ $nombre }}</title>
    @include('layouts.partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full antialiased text-neutral-800 bg-[#F4F8F9]">
<div class="min-h-full flex flex-col items-center justify-center px-4 py-10">
    <div class="mb-8 w-full max-w-md flex justify-center">
        @include('layouts.partials.logo-institucional', ['url' => $logoUrl, 'context' => 'login-mobile'])
    </div>

    <div class="se-auth-card w-full max-w-md p-6 sm:p-8">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-neutral-500">{{ $nombre }}</p>
        <h1 class="mt-2 text-xl sm:text-2xl font-bold tracking-tight text-neutral-800">Ingresar</h1>
        <p class="mt-1.5 text-sm text-neutral-600">Elegí el portal de este dispositivo.</p>

        <div class="mt-6 space-y-3">
            <a href="{{ route('login') }}" class="se-auth-btn">Personal de la institución</a>
            <a href="{{ route('alumnos.login') }}"
               class="inline-flex w-full items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition-colors duration-150 hover:bg-accent-50">
                Familias y estudiantes
            </a>
        </div>
    </div>
</div>
</body>
</html>
