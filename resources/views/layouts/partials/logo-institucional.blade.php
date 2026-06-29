{{--
  Logo institucional — contextos: sidebar | hero | login | preview
  @param string $url
  @param string $context
--}}
@php
    $url = $url ?? (schoolLogoUrl() ?: asset('img/3.png'));
    $context = $context ?? 'sidebar';
    $emblema = schoolLogoEsEmblema();
@endphp

@if ($context === 'sidebar')
    <span @class([
        'se-sidebar-brand bg-white shadow-sm',
        'se-sidebar-brand--emblema' => $emblema,
        'rounded-lg px-2 py-1.5' => ! $emblema,
    ])>
        <img src="{{ $url }}" alt="" class="object-contain block">
    </span>
@elseif ($context === 'hero')
    <div @class([
        'inline-flex items-center justify-center overflow-hidden bg-white ring-1 ring-white/20',
        'se-logo-hero--emblema rounded-full p-3' => $emblema,
        'rounded-2xl p-4 shadow-md' => ! $emblema,
    ])>
        <img src="{{ $url }}" alt="" @class([
            'object-contain block',
            'h-16 w-16 sm:h-20 sm:w-20 rounded-full' => $emblema,
            'h-16 sm:h-20 w-auto max-w-[200px]' => ! $emblema,
        ])>
    </div>
@elseif ($context === 'login')
    <span @class([
        'inline-flex bg-white shadow-md overflow-hidden',
        'se-logo-login--emblema rounded-full p-2.5 xl:p-3' => $emblema,
        'rounded-2xl px-3 py-2.5 xl:px-3 xl:py-2.5' => ! $emblema,
    ])>
        <img src="{{ $url }}" alt="" @class([
            'object-contain block',
            'h-24 w-24 xl:h-28 xl:w-28' => $emblema,
            'w-auto h-28 xl:h-32 max-w-[min(100%,14rem)]' => ! $emblema,
        ])>
    </span>
@elseif ($context === 'login-mobile')
    <span @class([
        'inline-flex bg-white shadow-md overflow-hidden',
        'se-logo-login--emblema rounded-full p-3' => $emblema,
        'rounded-2xl px-4 py-3' => ! $emblema,
    ])>
        <img src="{{ $url }}" alt="" @class([
            'object-contain block',
            'h-[7.5rem] w-[7.5rem]' => $emblema,
            'max-h-[148px] w-auto max-w-[min(100%,16rem)]' => ! $emblema,
        ])>
    </span>
@elseif ($context === 'preview')
    <div @class([
        'flex min-h-[120px] items-center justify-center rounded-2xl border border-accent-200 bg-white p-4',
        'se-logo-preview--emblema' => $emblema,
    ])>
        @if ($url)
            <img src="{{ $url }}" alt="Logo" @class([
                'object-contain',
                'h-28 w-28' => $emblema,
                'max-h-28' => ! $emblema,
            ])>
        @else
            <span class="text-xs text-neutral-400">Sin logo</span>
        @endif
    </div>
@endif
