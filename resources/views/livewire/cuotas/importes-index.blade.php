<div class="se-page max-w-6xl mx-auto">

    <section class="se-hero mb-6">

        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

            <div class="min-w-0 space-y-1">

                <p class="se-eyebrow">Gestión masiva</p>

                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl uppercase">

                    Importes por curso — Año {{ $ano }}

                </h1>

                <p class="text-sm text-white/80 max-w-xl">

                    Elija una cuota del año lectivo activo para editar importes y bonificaciones o intereses por sala, grado o curso.

                </p>

            </div>

        </div>

    </section>



    <div class="se-toolbar se-toolbar-pocos-campos mb-4" x-data x-init="$nextTick(() => $refs.cuotasImportesBuscar?.focus())">

        <div class="relative w-full max-w-xs">

            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">

                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>

            </svg>

            <input wire:model.live.debounce.300ms="search"

                   type="search"

                   x-ref="cuotasImportesBuscar"

                   placeholder="Búsqueda por nombre"

                   class="form-input pl-9 text-sm"

                   autocomplete="off">

        </div>

    </div>



    <div class="se-card overflow-hidden p-2 sm:p-3">

        <div class="se-cuotas-importes-lista-scroll">

            <table class="se-cuotas-importes-lista-tabla">

                <thead>

                    <tr>

                        <th scope="col" class="se-cil-th-nombre">Nombre de la cuota</th>

                        <th scope="col" class="se-cil-th-venc">Venc 1</th>

                        <th scope="col" class="se-cil-th-venc">Venc 2</th>

                        <th scope="col" class="se-cil-th-accion"></th>

                    </tr>

                </thead>

                <tbody>

                    @forelse ($cuotas as $cuota)

                        <tr wire:key="cuota-importes-{{ $cuota->id }}">

                            <td class="se-cil-td-nombre">{{ $cuota->nombre }}</td>

                            <td class="se-cil-td-venc tabular-nums">

                                {{ \App\Support\Cuotas\CuotasFormato::formatearFecha($cuota->venc1) ?: '—' }}

                            </td>

                            <td class="se-cil-td-venc tabular-nums">

                                {{ \App\Support\Cuotas\CuotasFormato::formatearFecha($cuota->venc2) ?: '—' }}

                            </td>

                            <td class="se-cil-td-accion">

                                <button type="button"
                                        wire:click="abrirEditor({{ $cuota->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="abrirEditor"
                                        class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3 py-1.5 text-[10px] font-semibold text-white shadow-sm transition hover:bg-primary-700 whitespace-nowrap disabled:opacity-60">
                                    Editar importes
                                </button>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="4" class="py-10 text-center text-sm text-neutral-500">

                                @if (trim($search) !== '')

                                    No hay cuotas que coincidan con la búsqueda.

                                @else

                                    No hay cuotas del año {{ $ano }}. Cree plantillas en «Crear / Editar Cuotas».

                                @endif

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>


