@props([
    'maxWidth' => 'max-w-5xl',
])

<div {{ $attributes->merge(['class' => trim("w-full mx-auto {$maxWidth}")]) }}>
    {{ $slot }}
</div>
