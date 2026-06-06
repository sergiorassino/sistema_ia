{{-- Certificado de asistencia del profesor — listado y modal de emisión. --}}
<div class="se-cierre-anual-fill se-matriz-list-fill"
     x-data
     x-on:certificado-asistencia-profesor-abrir-pdf.window="window.open($event.detail.url, '_blank')">
    <div class="se-cierre-anual-grid se-cierre-anual-grid--matriz-listado">
        <section class="se-hero se-matriz-list-hero min-w-0 shrink-0">
            <div class="se-hero-inner flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-0.5">
                    <p class="se-eyebrow !text-[10px]">Certificados</p>
                    <h2 class="font-bold tracking-tight">Certificado de Asistencia del Profesor</h2>
                    <p class="text-xs text-white/80 truncate">
                        Personal del legajo con rol asignado (excluye «Sin Rol»)
                    </p>
                </div>
                <a href="{{ route('dashboard') }}"
                   class="inline-flex shrink-0 items-center justify-center gap-1 rounded-lg border border-white/25 bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-white/20">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Panel
                </a>
            </div>
        </section>

        @if (session('success'))
            <div class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-2 text-sm text-primary-800" role="status">
                {{ session('success') }}
            </div>
        @endif

        <div class="se-matriz-list-toolbar se-matriz-list-toolbar--angosta">
            <div class="relative min-w-0 flex-1 sm:max-w-sm">
                <svg class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input id="buscar-cert-asist-prof"
                       type="search"
                       wire:model.live.debounce.400ms="buscar"
                       placeholder="Apellido, nombre o DNI…"
                       class="form-input w-full !py-1.5 !pl-8 text-sm"
                       autocomplete="off"
                       aria-label="Buscar profesor">
            </div>
            <p class="shrink-0 text-[11px] font-medium tabular-nums text-neutral-500">
                {{ $profesores->total() }} registros
            </p>
        </div>

        <div class="se-matriz-list-card min-h-0">
            <div class="se-cierre-anual-grilla se-matriz-list-grilla se-matriz-list-grilla--unified">
                <div class="se-cierre-anual-body-wrap se-matriz-list-scroll se-grid-angosta-wrap" tabindex="0" data-se-cierre-body>
                    <table class="se-matriz-list-tabla se-matriz-list-tabla--cert-asist-prof table-fixed">
                        <colgroup>
                            <col style="width:8.5rem">
                            <col style="width:8.5rem">
                            <col style="width:5.25rem">
                            <col style="width:9rem">
                            <col style="width:8.75rem">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col" class="text-left">Apellido</th>
                                <th scope="col" class="text-left">Nombre</th>
                                <th scope="col" class="text-left">DNI</th>
                                <th scope="col" class="text-left">Rol</th>
                                <th scope="col" class="text-left whitespace-nowrap">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($profesores as $p)
                                <tr wire:key="cert-asist-prof-{{ $p['idProfesores'] }}">
                                    <td class="font-medium text-neutral-800">
                                        <span class="se-matriz-list-cell-truncate" title="{{ $p['apellido'] }}">{{ $p['apellido'] }}</span>
                                    </td>
                                    <td class="text-neutral-800">
                                        <span class="se-matriz-list-cell-truncate" title="{{ $p['nombre'] }}">{{ $p['nombre'] }}</span>
                                    </td>
                                    <td class="tabular-nums text-neutral-700 whitespace-nowrap">{{ $p['dni'] !== '' ? $p['dni'] : '—' }}</td>
                                    <td class="text-neutral-700">
                                        <span class="se-matriz-list-cell-truncate" title="{{ $p['rol'] }}">{{ $p['rol'] !== '' ? $p['rol'] : '—' }}</span>
                                    </td>
                                    <td class="!px-1.5 whitespace-nowrap">
                                        <button type="button"
                                                wire:click="abrirModal({{ $p['idProfesores'] }})"
                                                title="Emitir certificado"
                                                class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-2 py-1 text-[10px] font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1">
                                            Emitir cert.
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="!h-auto py-8 text-center text-xs text-neutral-500">
                                        No hay profesores o personal con rol asignado que coincidan con la búsqueda.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($profesores->hasPages())
                <div class="se-matriz-list-footer">
                    {{ $profesores->links('vendor.pagination.se-compact') }}
                </div>
            @endif
        </div>
    </div>

    @if ($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
             role="dialog"
             aria-modal="true"
             aria-labelledby="modal-cert-asist-prof-titulo">
            <div class="absolute inset-0 bg-neutral-900/50 backdrop-blur-sm"
                 wire:click="cerrarModal"
                 aria-hidden="true"></div>

            <div class="relative z-10 w-full max-w-lg rounded-2xl border border-accent-200 bg-white shadow-xl">
                <header class="border-b border-accent-200 px-5 py-4">
                    <h3 id="modal-cert-asist-prof-titulo" class="text-base font-bold text-neutral-800">
                        Certificado de Asistencia del Profesor
                    </h3>
                    <p class="mt-0.5 text-xs text-neutral-500 truncate" title="{{ $profesorModalEtiqueta }}">
                        {{ $profesorModalEtiqueta }}
                    </p>
                </header>

                <form class="px-5 py-4 space-y-4" wire:submit.prevent="emitirPdf">
                    <div>
                        <label for="fecha-cert-asist" class="form-label">Fecha de emisión</label>
                        <input id="fecha-cert-asist" type="date" wire:model="fecha" class="form-input w-full">
                        @error('fecha')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="texto-cert-asist" class="form-label">Texto del certificado</label>
                        <p class="mb-1 text-[10px] text-neutral-500">
                            Completará la frase: «Es PROFESOR/A en este Establecimiento, …»
                        </p>
                        <textarea id="texto-cert-asist"
                                  wire:model="texto"
                                  rows="3"
                                  class="form-input w-full resize-y"
                                  maxlength="200"
                                  placeholder="Ej.: con dedicación exclusiva en las materias …"></textarea>
                        @error('texto')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="parapre-cert-asist" class="form-label">Para ser presentada</label>
                        <p class="mb-1 text-[10px] text-neutral-500">
                            Completará: «… para ser presentada …»
                        </p>
                        <input id="parapre-cert-asist"
                               type="text"
                               wire:model="parapre"
                               class="form-input w-full"
                               maxlength="300"
                               autocomplete="off"
                               placeholder="Ej.: ante la Dirección de Personal">
                        @error('parapre')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-neutral-600 cursor-pointer">
                        <input type="checkbox" wire:model="guardarAlEmitir" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                        Guardar datos al generar el PDF
                    </label>

                    @error('guardar')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-accent-100 -mx-5 px-5 pt-4 mt-2">
                        <button type="button"
                                wire:click="cerrarModal"
                                class="rounded-xl border border-accent-200 bg-white px-3 py-2 text-sm font-semibold text-primary-700 transition hover:bg-accent-50">
                            Cancelar
                        </button>
                        <button type="button"
                                wire:click="guardarDatos"
                                wire:loading.attr="disabled"
                                class="rounded-xl border border-accent-200 bg-white px-3 py-2 text-sm font-semibold text-neutral-700 transition hover:bg-accent-50 disabled:opacity-60">
                            <span wire:loading.remove wire:target="guardarDatos">Solo guardar</span>
                            <span wire:loading wire:target="guardarDatos">Guardando…</span>
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="rounded-xl bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="emitirPdf">Generar PDF</span>
                            <span wire:loading wire:target="emitirPdf">Generando…</span>
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    @endif
</div>
