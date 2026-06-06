{{-- Libro matriz / pase / analítico — listado compacto de legajos. --}}
<div class="se-cierre-anual-fill se-matriz-list-fill">
    <div class="se-cierre-anual-grid se-cierre-anual-grid--matriz-listado">
        <section class="se-hero se-matriz-list-hero min-w-0 shrink-0">
            <div class="se-hero-inner flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-0.5">
                    <p class="se-eyebrow !text-[10px]">Matríz y analíticos</p>
                    <h2 class="font-bold tracking-tight">Libro Matriz / Pase / Analítico</h2>
                    <p class="text-xs text-white/80 truncate">
                        {{ schoolCtx()->nivelNombre() }} · Última inscripción por legajo
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

        <div class="se-matriz-list-toolbar">
            <div class="relative min-w-0 flex-1 sm:max-w-sm">
                <svg class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input id="buscar-matriz"
                       type="search"
                       wire:model.live.debounce.400ms="buscar"
                       placeholder="Apellido, nombre o DNI…"
                       class="form-input w-full !py-1.5 !pl-8 text-sm"
                       autocomplete="off"
                       aria-label="Buscar alumno">
            </div>
            <p class="shrink-0 text-[11px] font-medium tabular-nums text-neutral-500">
                {{ $alumnos->total() }} legajos
            </p>
        </div>

        <div class="se-matriz-list-card min-h-0">
            <div class="se-cierre-anual-grilla se-matriz-list-grilla se-matriz-list-grilla--unified">
                <div class="se-cierre-anual-body-wrap se-matriz-list-scroll" tabindex="0" data-se-cierre-body>
                    <table class="se-matriz-list-tabla table-fixed min-w-[48rem] w-full">
                        <colgroup>
                            <col style="width:14%">
                            <col style="width:14%">
                            <col style="width:10%">
                            <col style="width:22%">
                            <col style="width:14%">
                            <col style="width:3rem">
                            <col style="width:3rem">
                            <col style="width:3rem">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col" class="text-left">Apellido</th>
                                <th scope="col" class="text-left">Nombre</th>
                                <th scope="col" class="text-left">DNI</th>
                                <th scope="col" class="text-left">Curso</th>
                                <th scope="col" class="text-left">Nivel</th>
                                <th scope="col" class="text-center" title="Editar">Ed.</th>
                                <th scope="col" class="text-center" title="PDF frente">Fr.</th>
                                <th scope="col" class="text-center" title="PDF reverso">Rev.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($alumnos as $a)
                                <tr wire:key="libro-matriz-{{ $a['idLegajos'] }}">
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
                                    <td class="text-neutral-600">
                                        <span class="se-matriz-list-cell-truncate" title="{{ $a['nivel'] }}">{{ $a['nivel'] !== '' ? $a['nivel'] : '—' }}</span>
                                    </td>
                                    <td class="text-center !px-1">
                                        <x-nav-contexto-estudiante
                                            destino="matrizAnaliticos.libroMatriz.editar"
                                            :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::MATRIZ_ANALITICOS"
                                            :id-legajos="$a['idLegajos']"
                                            :buscar="$buscar"
                                            class="inline">
                                            <span class="se-matriz-list-iconbtn" title="Editar calificaciones en matriz">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                                <span class="sr-only">Editar</span>
                                            </span>
                                        </x-nav-contexto-estudiante>
                                    </td>
                                    <td class="text-center !px-1">
                                        <x-pdf-post
                                            :action="route('matrizAnaliticos.libroMatriz.pdfFrente')"
                                            :fields="['idLegajos' => $a['idLegajos']]"
                                            button-class="se-matriz-list-iconbtn">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="sr-only">PDF frente</span>
                                        </x-pdf-post>
                                    </td>
                                    <td class="text-center !px-1">
                                        <x-pdf-post
                                            :action="route('matrizAnaliticos.libroMatriz.pdfReverso')"
                                            :fields="['idLegajos' => $a['idLegajos']]"
                                            button-class="se-matriz-list-iconbtn">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="sr-only">PDF reverso</span>
                                        </x-pdf-post>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="!h-auto py-8 text-center text-xs text-neutral-500">
                                        No hay legajos que coincidan con la búsqueda.
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
</div>
