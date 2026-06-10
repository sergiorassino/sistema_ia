<div>
    @if ($anio === null)
        {{-- Paso 1: elegir año lectivo --}}
        <div class="rounded-3xl bg-white border border-[#C1D7DA] shadow-sm p-6 md:p-8">
            <div class="mb-6">
                <h2 class="text-base font-bold text-neutral-900">Elegí el año lectivo</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    Seleccioná un año para ver los programas de examen disponibles para descargar.
                </p>
            </div>

            @if (count($anios) === 0)
                <div class="rounded-2xl bg-[#F4F8F9] border border-[#C1D7DA] px-4 py-8 text-center">
                    <p class="text-sm text-neutral-500">No hay años disponibles en este momento.</p>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    @foreach ($anios as $a)
                        <button
                            type="button"
                            wire:click="elegirAnio({{ $a }})"
                            class="group flex flex-col items-center justify-center gap-1 rounded-2xl border border-[#C1D7DA] bg-white px-4 py-6 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-[#40848D] hover:shadow-md focus:outline-none focus:ring-2 focus:ring-[#40848D]/40"
                        >
                            <span class="text-2xl font-bold text-[#40848D]">{{ $a }}</span>
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Año lectivo</span>
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="mt-8 flex justify-end">
                <button
                    type="button"
                    onclick="window.close()"
                    class="inline-flex items-center gap-2 rounded-xl border border-[#C1D7DA] bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-[#F4F8F9] focus:outline-none focus:ring-2 focus:ring-[#40848D]/40"
                >
                    Cerrar
                </button>
            </div>
        </div>
    @else
        {{-- Paso 2: grilla de programas del año elegido --}}
        <div class="rounded-3xl bg-white border border-[#C1D7DA] shadow-sm p-4 md:p-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-neutral-900">Programas de examen {{ $anio }}</h2>
                    <p class="mt-1 text-sm text-neutral-500">Hacé clic en el programa para descargarlo.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="volver"
                        class="inline-flex items-center gap-2 rounded-xl border border-[#C1D7DA] bg-white px-4 py-2 text-sm font-semibold text-[#40848D] shadow-sm transition hover:bg-[#F4F8F9] focus:outline-none focus:ring-2 focus:ring-[#40848D]/40"
                    >
                        Volver
                    </button>
                    <button
                        type="button"
                        onclick="window.close()"
                        class="inline-flex items-center gap-2 rounded-xl border border-[#C1D7DA] bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-[#F4F8F9] focus:outline-none focus:ring-2 focus:ring-[#40848D]/40"
                    >
                        Cerrar
                    </button>
                </div>
            </div>

            @if ($programas->isEmpty())
                <div class="rounded-2xl bg-[#F4F8F9] border border-[#C1D7DA] px-4 py-10 text-center">
                    <p class="text-sm text-neutral-500">No hay programas cargados para el año {{ $anio }}.</p>
                </div>
            @else
                <div class="w-full overflow-x-auto">
                    <table class="w-full min-w-[40rem] border-collapse text-sm">
                        <thead>
                            <tr class="bg-[#F4F8F9] text-left text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
                                <th class="px-3 py-2 border-b border-[#C1D7DA]">Año</th>
                                <th class="px-3 py-2 border-b border-[#C1D7DA]">Materia</th>
                                <th class="px-3 py-2 border-b border-[#C1D7DA] text-center">Cur.</th>
                                <th class="px-3 py-2 border-b border-[#C1D7DA] text-center">Sec.</th>
                                <th class="px-3 py-2 border-b border-[#C1D7DA]">Programa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($programas as $p)
                                <tr class="border-b border-[#E7EEF0] hover:bg-[#F4F8F9]">
                                    <td class="px-3 py-2 text-neutral-600 whitespace-nowrap">{{ $anio }}</td>
                                    <td class="px-3 py-2 font-medium text-neutral-800">{{ $p->nombreMateria }}</td>
                                    <td class="px-3 py-2 text-center text-neutral-600 whitespace-nowrap">{{ $p->curso }}</td>
                                    <td class="px-3 py-2 text-center text-neutral-600 whitespace-nowrap">{{ $p->seccion }}</td>
                                    <td class="px-3 py-2">
                                        @if ($p->tiene_programa)
                                            <a
                                                href="{{ $p->url_programa }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center gap-1.5 font-semibold text-[#40848D] hover:text-[#2f636a] hover:underline"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M10 3a1 1 0 0 1 1 1v7.586l2.293-2.293a1 1 0 1 1 1.414 1.414l-4 4a1 1 0 0 1-1.414 0l-4-4a1 1 0 1 1 1.414-1.414L9 11.586V4a1 1 0 0 1 1-1Zm-6 12a1 1 0 0 1 1-1h10a1 1 0 1 1 0 2H5a1 1 0 0 1-1-1Z" clip-rule="evenodd" />
                                                </svg>
                                                <span>{{ $p->texto_programa }}</span>
                                            </a>
                                        @else
                                            <span class="text-neutral-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
