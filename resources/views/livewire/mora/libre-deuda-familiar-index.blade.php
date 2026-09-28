@php
    use App\Support\Cuotas\CuotasFormato;
    use App\Support\Mora\EstadoDeudaEstudianteListado;
    use App\Support\Security\OpaqueRouteToken;
@endphp

<div class="se-page max-w-7xl mx-auto">
    <section class="se-hero mb-6">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Administración · Gestión de aranceles</p>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Libre Deuda Familiar</h1>
                <p class="text-sm text-white/80 max-w-2xl">
                    Ciclo lectivo {{ schoolCtx()->terlecAno() }} — constancia del estudiante. Solo se emite si no tiene cuotas con saldo.
                </p>
            </div>
        </div>
    </section>

    <div class="se-toolbar mb-4 sm:items-end" x-data x-init="$nextTick(() => $refs.libreDeudaBuscar?.focus())">
        <div class="flex-1 max-w-xl">
            <label for="libre-deuda-buscar" class="form-label">Búsqueda</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input wire:model.live.debounce.400ms="search"
                       id="libre-deuda-buscar"
                       type="search"
                       x-ref="libreDeudaBuscar"
                       autofocus
                       placeholder="Apellido, nombre o DNI del estudiante; familia o responsable..."
                       class="form-input pl-9"
                       autocomplete="off">
            </div>
        </div>
        <div class="w-full sm:w-56 shrink-0">
            <label for="filtro-nivel-libre-deuda" class="form-label">Nivel</label>
            <select id="filtro-nivel-libre-deuda"
                    wire:model.live="idNivel"
                    class="form-input">
                <option value="">Todos</option>
                @foreach ($niveles as $nivel)
                    <option value="{{ (int) $nivel->id }}">{{ $nivel->nivel }}</option>
                @endforeach
            </select>
        </div>
        <label for="libre-deuda-sin-deuda" class="inline-flex items-center gap-2 cursor-pointer sm:pb-0.5">
            <input id="libre-deuda-sin-deuda"
                   type="checkbox"
                   wire:model.live="soloSinDeuda"
                   class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
            <span class="text-sm font-semibold text-neutral-700">Solo sin deuda</span>
        </label>
    </div>

    @if ($estudiantes->isEmpty())
        <div class="se-card p-8 text-center text-sm text-neutral-600">
            @if (trim($search) !== '' || $idNivel !== '' || $soloSinDeuda)
                No se encontraron estudiantes con ese criterio.
            @else
                No hay estudiantes matriculados en el ciclo lectivo activo.
            @endif
        </div>
    @else
        <div class="se-card overflow-hidden p-0">
            <div class="w-full overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-accent-50 text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Estudiante</th>
                            <th class="px-4 py-3 text-left">DNI</th>
                            <th class="px-4 py-3 text-left">Curso actual</th>
                            <th class="px-4 py-3 text-left">Familia</th>
                            <th class="px-4 py-3 text-right">Deuda</th>
                            <th class="px-4 py-3 text-right">Constancia</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100">
                        @foreach ($estudiantes as $estudiante)
                            @php
                                $apellidoNombre = EstadoDeudaEstudianteListado::apellidoNombre($estudiante);
                                $curso = EstadoDeudaEstudianteListado::cursoCicloActivo($estudiante);
                                $familia = EstadoDeudaEstudianteListado::familiaAsignada($estudiante->familia);
                                $etiquetaFamilia = trim((string) ($familia?->apellido ?? ''));
                                $deudaEstudiante = (float) ($totalesDeuda[$estudiante->id] ?? 0);
                                $conDeuda = isset($idsConDeuda[(int) $estudiante->id]);
                            @endphp
                            <tr class="hover:bg-accent-50/60" wire:key="libre-deuda-{{ $estudiante->id }}">
                                <td class="px-4 py-2.5 font-medium uppercase text-neutral-800">
                                    @if ($apellidoNombre !== '')
                                        {!! CuotasFormato::resaltarTerminoBusqueda($apellidoNombre, $search) !!}
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 tabular-nums text-neutral-700 whitespace-nowrap">
                                    {{ CuotasFormato::formatearDni($estudiante->dni) }}
                                </td>
                                <td class="px-4 py-2.5 uppercase text-neutral-700">
                                    {{ $curso !== '' ? $curso : '—' }}
                                </td>
                                <td class="px-4 py-2.5 uppercase text-neutral-700">
                                    @if ($etiquetaFamilia !== '')
                                        {!! CuotasFormato::resaltarTerminoBusqueda($etiquetaFamilia, $search) !!}
                                    @else
                                        <span class="text-neutral-400 italic font-normal normal-case">Sin familia</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums whitespace-nowrap {{ $deudaEstudiante > 0 ? 'se-mora-deuda se-mora-deuda--positivo' : 'se-mora-deuda' }}">
                                    {{ CuotasFormato::formatearImporte($deudaEstudiante) }}
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    @if ($conDeuda)
                                        <span class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Con deuda</span>
                                    @else
                                        <a href="{{ route('mora.libre-deuda-familiar.pdf', ['ref' => OpaqueRouteToken::forLibreDeudaFamiliar((int) $estudiante->id)]) }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700">
                                            Emitir PDF
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($estudiantes->hasPages())
                <div class="se-matriz-list-footer">
                    {{ $estudiantes->links('vendor.pagination.se-compact') }}
                </div>
            @endif
        </div>
    @endif
</div>
