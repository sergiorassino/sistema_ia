@php
    use App\Support\Examenes\MateriasAdeudadasAlumnosListado;

    $urlVolverListado = MateriasAdeudadasAlumnosListado::urlListadoGestion(
        MateriasAdeudadasAlumnosListado::buscarRetornoListado(),
        MateriasAdeudadasAlumnosListado::ambitoRetornoListado(),
    );
@endphp
<a href="{{ $urlVolverListado }}"
   wire:navigate
   class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
    </svg>
    Volver al listado
</a>
