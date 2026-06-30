@teleport('body')
<div>
    @if ($modalAlumnosAbierto)
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalAlumnos"></div>
            <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                    <h3 class="text-lg font-semibold text-neutral-900">Buscar alumnos regulares</h3>
                    <input type="search" wire:model.live.debounce.300ms="modalAlumnosFiltro" class="form-input mt-3" placeholder="Apellido, nombre o DNI…" autofocus>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-3">
                    @forelse ($modalAlumnosLista as $a)
                        <label class="flex items-center gap-2 border-b border-accent-50 py-2 text-sm">
                            <input type="checkbox" value="{{ $a['id'] }}" wire:model="modalAlumnosMarcados">
                            <span>{{ $a['label'] }} @if($a['dni'])<span class="text-neutral-500">· DNI {{ $a['dni'] }}</span>@endif</span>
                        </label>
                    @empty
                        <p class="py-6 text-center text-sm text-neutral-500">Escriba al menos un carácter para buscar.</p>
                    @endforelse
                </div>
                <div class="flex shrink-0 justify-end gap-2 border-t border-accent-100 bg-accent-50 px-5 py-4">
                    <button type="button" wire:click="cerrarModalAlumnos" class="rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold">Cancelar</button>
                    <button type="button" wire:click="aplicarModalAlumnos" class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white">Aplicar selección</button>
                </div>
            </div>
        </div>
    @endif

    @if ($modalCursosAbierto)
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalCursos"></div>
            <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                    <h3 class="text-lg font-semibold text-neutral-900">Elegir cursos</h3>
                    <input type="search" wire:model.live.debounce.300ms="modalCursosFiltro" class="form-input mt-3" placeholder="Filtrar curso…">
                    <button type="button" wire:click="modalCursosSeleccionarTodos" class="mt-2 text-xs font-semibold text-primary-700">Seleccionar todos los cursos visibles</button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-3">
                    @forelse ($modalCursosLista as $c)
                        <label class="flex items-center gap-2 border-b border-accent-50 py-2 text-sm">
                            <input type="checkbox" value="{{ $c['id'] }}" wire:model="modalCursosMarcados">
                            {{ $c['label'] }}
                        </label>
                    @empty
                        <p class="py-6 text-center text-sm text-neutral-500">No hay cursos en este ciclo.</p>
                    @endforelse
                </div>
                <div class="flex shrink-0 justify-end gap-2 border-t border-accent-100 bg-accent-50 px-5 py-4">
                    <button type="button" wire:click="cerrarModalCursos" class="rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold">Cancelar</button>
                    <button type="button" wire:click="aplicarModalCursos" class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white">Aplicar selección</button>
                </div>
            </div>
        </div>
    @endif
</div>
@endteleport
