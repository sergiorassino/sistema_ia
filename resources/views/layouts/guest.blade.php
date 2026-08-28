<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — {{ ($guestPortal ?? 'staff') === 'alumno' ? 'Estudiantes' : 'Acceso' }}</title>
    @include('layouts.partials.favicon-guest')
    @include('layouts.partials.pwa', ['guestPortal' => $guestPortal ?? 'staff'])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full antialiased text-neutral-800">
@php
    $guestBrandLogo = entoInstitutionalLogoUrlFallback() ?: asset('img/3.png');
@endphp

<div class="min-h-screen flex flex-col md:flex-row">

    {{-- Franja superior móvil (marca) --}}
    <div class="md:hidden h-3 shrink-0 bg-gradient-to-r from-[#333333] via-[#40848D] to-[#C1D7DA]"
         aria-hidden="true"></div>

    {{-- Panel editorial — solo desktop --}}
    <aside class="relative hidden md:flex md:w-[46%] lg:w-[48%] min-h-screen flex-col justify-between px-10 xl:px-14 py-12 text-white overflow-hidden bg-gradient-to-br from-[#40848D] via-[#366f76] to-[#333333]">
        <div class="pointer-events-none absolute inset-0 opacity-95 bg-[radial-gradient(ellipse_120%_80%_at_90%_0%,rgba(255,255,255,0.08),transparent_50%),radial-gradient(ellipse_90%_55%_at_10%_100%,rgba(51,51,51,0.35),transparent_52%)]"
             aria-hidden="true"></div>

        <div class="relative z-10 flex w-full flex-col gap-8">
            <div class="flex w-full justify-center">
                @include('layouts.partials.logo-institucional', ['url' => $guestBrandLogo, 'context' => 'login'])
            </div>

            <div class="max-w-lg w-full mx-auto md:mx-0">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-white/65">{{ config('app.name') }}</p>
                @if (($guestPortal ?? 'staff') === 'alumno')
                    <h1 class="mt-3 text-3xl xl:text-[2rem] font-bold leading-tight tracking-tight">
                        Portal de estudiantes
                    </h1>
                    <p class="mt-4 text-base leading-relaxed text-white/80">
                        Consultá calificaciones, comunicaciones y avisos de la institución.
                    </p>
                    <p class="mt-3 text-sm leading-relaxed text-white/70">
                        Ingresá con tu DNI y contraseña.
                    </p>
                @else
                    <h1 class="mt-3 text-3xl xl:text-[2rem] font-bold leading-tight tracking-tight">
                        Portal del personal
                    </h1>
                    <p class="mt-4 text-base leading-relaxed text-white/80">
                        Acceso para docentes, secretarios, directivos y demás personal de la institución.
                    </p>
                    <p class="mt-3 text-sm leading-relaxed text-white/70">
                        Ingrese para gestionar el nivel y el ciclo lectivo activos.
                    </p>
                @endif
            </div>
        </div>

        <div class="relative z-10 flex gap-3 pt-10 border-t border-white/10">
            <span class="h-2 flex-1 max-w-[4.5rem] rounded-full bg-[#40848D]" aria-hidden="true"></span>
            <span class="h-2 flex-1 max-w-[3rem] rounded-full bg-[#739FA5]" aria-hidden="true"></span>
            <span class="h-2 flex-1 max-w-[5rem] rounded-full bg-[#C1D7DA]/85" aria-hidden="true"></span>
        </div>
    </aside>

    {{-- Área del formulario --}}
    <div class="flex flex-1 flex-col bg-[#F4F8F9] md:bg-white">

        <div class="flex flex-1 flex-col items-center justify-center px-4 py-8 sm:px-8 md:py-14">

            {{-- Logo móvil (panel editorial oculto) --}}
            <div class="mb-7 md:hidden w-full flex justify-center">
                @include('layouts.partials.logo-institucional', ['url' => $guestBrandLogo, 'context' => 'login-mobile'])
            </div>

            <div class="w-full max-w-md">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>

@include('layouts.partials.livewire-scripts')
<script>
    if (window.seSidebarPurgeLegacyGroupsStorage) {
        window.seSidebarPurgeLegacyGroupsStorage();
    } else {
        try { localStorage.removeItem('sidebarGroups'); } catch (e) {}
    }
</script>
</body>
</html>
