<div>
    <label class="se-label">Buscar alumno</label>
    <input type="search"
           wire:model.live.debounce.400ms="search"
           class="se-input w-full"
           placeholder="Apellido, nombre, DNI o legajo"
           autocomplete="off"
           @disabled($idLegajoActivo)
    >
    @if ($legajos && $legajos->isNotEmpty())
        <ul class="mt-2 divide-y divide-accent-200 rounded-xl border border-accent-200 bg-white">
            @foreach ($legajos as $leg)
                <li class="flex items-center justify-between gap-2 px-3 py-2"
                    wire:key="buscar-legajo-{{ $leg->id }}">
                    <span class="min-w-0 flex-1 text-sm">
                        {{ $leg->apellido }}, {{ $leg->nombre }}
                        <span class="text-neutral-500">DNI {{ $leg->dni }}</span>
                    </span>
                    <button type="button"
                            wire:click="seleccionar({{ (int) $leg->id }})"
                            wire:loading.attr="disabled"
                            wire:target="seleccionar"
                            class="btn-secondary btn-sm shrink-0 cursor-pointer">
                        <span wire:loading.remove wire:target="seleccionar">Seleccionar</span>
                        <span wire:loading wire:target="seleccionar">…</span>
                    </button>
                </li>
            @endforeach
        </ul>
    @elseif (trim($search) !== '')
        <p class="mt-2 text-sm text-neutral-500">No se encontraron alumnos con ese criterio.</p>
    @endif

    @script
    <script>
        $wire.on('se-swal-error', ({ mensaje, titulo }) => window.seSwalError(mensaje, titulo ?? 'Error'));
    </script>
    @endscript
</div>
