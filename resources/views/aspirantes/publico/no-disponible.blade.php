@extends('layouts.aspirantes-publico', ['pageTitle' => $titulo])

@section('content')
    <div class="se-card p-8 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-accent-50 text-primary-700">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h2 class="text-xl font-bold text-neutral-900">{{ $titulo }}</h2>
        <p class="mt-2 text-sm text-neutral-600">{{ $mensaje }}</p>
    </div>
@endsection
