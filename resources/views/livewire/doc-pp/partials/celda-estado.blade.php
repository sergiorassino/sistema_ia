@php
    $ruta = route('doc-pp.form', ['id' => $id, 'tipo' => $tipo]);
@endphp

<a href="{{ $ruta }}"
   class="inline-flex h-8 w-8 items-center justify-center rounded-lg transition hover:bg-white/80 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
   title="{{ $estado === 'vacio' ? 'Subir archivo' : 'Editar' }}">
    @if ($estado === 'vacio')
        <span class="inline-flex h-6 w-6 items-center justify-center rounded bg-amber-100 text-amber-700 ring-1 ring-amber-200">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v4m-2-2h4"/>
            </svg>
        </span>
    @elseif ($estado === 'aprobado')
        <svg class="h-6 w-6 text-emerald-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
        </svg>
    @else
        <svg class="h-6 w-6 text-sky-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
        </svg>
    @endif
</a>
