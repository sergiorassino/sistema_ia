{{-- Constancia de documentos — listado y modal de emisión. --}}
<div class="se-cierre-anual-fill se-matriz-list-fill"
     x-data
     x-on:constancia-documentos-abrir-pdf.window="window.open($event.detail.url, '_blank')">
    <div class="se-cierre-anual-grid se-cierre-anual-grid--matriz-listado">
        <section class="se-hero se-matriz-list-hero min-w-0 shrink-0">
            <div class="se-hero-inner flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-0.5">
                    <p class="se-eyebrow !text-[10px]">Certificados</p>
                    <h2 class="font-bold tracking-tight">Constancia de Documentos</h2>
                    <p class="text-xs text-white/80 truncate">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo {{ $anoLectivo }} · Matriculados activos
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input id="buscar-const-docu"
                       type="search"
                       wire:model.live.debounce.400ms="buscar"
                       placeholder="Apellido, nombre o DNI…"
                       class="form-input w-full !py-1.5 !pl-8 text-sm"
                       autocomplete="off"
                       aria-label="Buscar alumno">
            </div>
            <p class="shrink-0 text-[11px] font-medium tabular-nums text-neutral-500">
                {{ $alumnos->total() }} alumnos
            </p>
        </div>

        <div class="se-matriz-list-card min-h-0">
            <div class="se-cierre-anual-grilla se-matriz-list-grilla se-matriz-list-grilla--unified">
                <div class="se-cierre-anual-body-wrap se-matriz-list-scroll se-grid-angosta-wrap" tabindex="0" data-se-cierre-body>
                    <table class="se-matriz-list-tabla se-matriz-list-tabla--cert-alu-reg table-fixed">
                        <colgroup>
                            <col style="width:8.5rem">
                            <col style="width:8.5rem">
                            <col style="width:5.25rem">
                            <col style="width:11rem">
                            <col style="width:9.5rem">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col" class="text-left">Apellido</th>
                                <th scope="col" class="text-left">Nombre</th>
                                <th scope="col" class="text-left">DNI</th>
                                <th scope="col" class="text-left">Curso</th>
                                <th scope="col" class="text-left whitespace-nowrap">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($alumnos as $a)
                                <tr wire:key="const-docu-{{ $a['idLegajos'] }}">
                                    <td class="font-medium text-neutral-800">
                                        <span class="se-matriz-list-cell-truncate" title="{{ $a['apellido'] }}">{{ $a['apellido'] }}</span>
                                    </td>
                                    <td class="text-neutral-800">
                                        <span class="se-matriz-list-cell-truncate" title="{{ $a['nombre'] }}">{{ $a['nombre'] }}</span>
                                    </td>
                                    <td class="tabular-nums text-neutral-700 whitespace-nowrap">{{ $a['dni'] !== '' ? $a['dni'] : '—' }}</td>
                                    <td class="text-neutral-700">
                                        <span class="se-matriz-list-cell-truncate" title="{{ $a['curso'] }}">{{ $a['curso'] !== '' ? $a['curso'] : '—' }}</span>
                                    </td>
                                    <td class="!px-1.5 whitespace-nowrap">
                                        <button type="button"
                                                wire:click="abrirModal({{ $a['idLegajos'] }})"
                                                title="Emitir certificado"
                                                class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-2 py-1 text-[10px] font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1">
                                            Emitir Certificado
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="!h-auto py-8 text-center text-xs text-neutral-500">
                                        No hay alumnos matriculados en el ciclo lectivo activo que coincidan con la búsqueda.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($alumnos->hasPages())
                <div class="se-matriz-list-footer">
                    {{ $alumnos->links('vendor.pagination.se-compact') }}
                </div>
            @endif
        </div>
    </div>

    @if ($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="modal-const-docu-titulo">
            <div class="absolute inset-0 bg-neutral-900/50 backdrop-blur-sm"
                 wire:click="cerrarModal"
                 aria-hidden="true"></div>

            <div class="relative z-10 flex w-full max-w-lg max-h-[calc(100dvh-1.5rem)] flex-col overflow-hidden rounded-xl border border-accent-200 bg-white shadow-xl">
                <header class="shrink-0 border-b border-accent-200 px-3.5 py-2.5">
                    <h3 id="modal-const-docu-titulo" class="text-sm font-bold leading-snug text-neutral-800">
                        Constancia de Documentos
                    </h3>
                    <p class="mt-0.5 truncate text-[11px] text-neutral-500" title="{{ $alumnoModalEtiqueta }}">
                        {{ $alumnoModalEtiqueta }}
                    </p>
                </header>

                <form class="flex min-h-0 flex-1 flex-col" wire:submit.prevent="emitirPdf">
                    <div class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3.5 py-2.5">
                        <div class="grid grid-cols-2 gap-2">
                            <div class="min-w-0">
                                <label class="form-label !mb-0.5">D.N.I.</label>
                                <input type="text"
                                       value="{{ $dniModal }}"
                                       class="form-input w-full !py-1.5 !text-sm bg-accent-50 text-neutral-600"
                                       readonly
                                       tabindex="-1"
                                       aria-readonly="true">
                            </div>
                            <div class="min-w-0">
                                <label for="fechemis" class="form-label !mb-0.5">Fecha emisión</label>
                                <input id="fechemis"
                                       type="date"
                                       wire:model="fechemis"
                                       class="form-input w-full !py-1.5 !text-sm">
                                @error('fechemis')
                                    <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="certifde" class="form-label !mb-0.5">Certificado de grado</label>
                            <input id="certifde"
                                   type="text"
                                   wire:model="certifde"
                                   class="form-input w-full !py-1.5 !text-sm"
                                   maxlength="300"
                                   placeholder="Ej. PRIMARIO, SECUNDARIO"
                                   autocomplete="off">
                            @error('certifde')
                                <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="otorpor" class="form-label !mb-0.5">Otorgado por (certificado)</label>
                            <input id="otorpor"
                                   type="text"
                                   wire:model="otorpor"
                                   class="form-input w-full !py-1.5 !text-sm"
                                   maxlength="300"
                                   autocomplete="off">
                            @error('otorpor')
                                <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="fechotor" class="form-label !mb-0.5">Fecha otorgamiento certificado</label>
                            <input id="fechotor"
                                   type="date"
                                   wire:model="fechotor"
                                   class="form-input w-full !py-1.5 !text-sm">
                            @error('fechotor')
                                <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="parnacop" class="form-label !mb-0.5">Partida de nacimiento otorgada por</label>
                            <input id="parnacop"
                                   type="text"
                                   wire:model="parnacop"
                                   class="form-input w-full !py-1.5 !text-sm"
                                   maxlength="300"
                                   autocomplete="off">
                            @error('parnacop')
                                <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="parapre" class="form-label !mb-0.5">Presentado a</label>
                            <input id="parapre"
                                   type="text"
                                   wire:model="parapre"
                                   class="form-input w-full !py-1.5 !text-sm"
                                   maxlength="300"
                                   autocomplete="off">
                            @error('parapre')
                                <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="inline-flex cursor-pointer items-center gap-1.5 text-[11px] text-neutral-600">
                            <input type="checkbox"
                                   wire:model="guardarAlEmitir"
                                   class="h-3.5 w-3.5 rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                            Guardar en constdocu al generar PDF
                        </label>

                        @error('guardar')
                            <p class="text-[11px] text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <footer class="flex shrink-0 flex-wrap items-center justify-end gap-1.5 border-t border-accent-100 bg-accent-50/50 px-3.5 py-2">
                        <button type="button"
                                wire:click="cerrarModal"
                                class="rounded-lg border border-accent-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-primary-700 transition hover:bg-accent-50">
                            Cancelar
                        </button>
                        <button type="button"
                                wire:click="guardarDatos"
                                wire:loading.attr="disabled"
                                class="rounded-lg border border-accent-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-accent-50 disabled:opacity-60">
                            <span wire:loading.remove wire:target="guardarDatos">Guardar</span>
                            <span wire:loading wire:target="guardarDatos">…</span>
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="rounded-lg bg-primary-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="emitirPdf">Generar PDF</span>
                            <span wire:loading wire:target="emitirPdf">…</span>
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    @endif
</div>
