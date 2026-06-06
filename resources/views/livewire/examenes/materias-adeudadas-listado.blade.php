<div class="se-page max-w-6xl">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Exámenes</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Listado de materias adeudadas</h2>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Nivel activo
                </p>
            </div>
        </div>
    </section>

    <livewire:examenes.materias-adeudadas-preparacion-panel
        modulo="listado"
        wire:key="prep-panel-listado" />

    @if ($preparacionLista ?? false)
    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="text-sm text-neutral-700">
                Materias con adeudo registrado en calificaciones (<code class="text-xs">apro = 1</code>).
                Podés filtrar por condición e inscripción, agrupar la vista y generar el mismo listado en PDF.
            </p>
        </div>

        <div class="bg-white px-5 py-5 space-y-6">
            <div>
                <p class="se-section-title">Agrupación del listado</p>
                <div class="mt-3 flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm font-medium">
                        <input type="radio"
                               wire:model.live="agrupar"
                               value="estudiante"
                               class="text-primary-600 focus:ring-primary-500">
                        Por estudiante
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-medium">
                        <input type="radio"
                               wire:model.live="agrupar"
                               value="materia_curso"
                               class="text-primary-600 focus:ring-primary-500">
                        Por materia y curso
                    </label>
                </div>
            </div>

            <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="filtro-condicion" class="form-label">Condición</label>
                    <select id="filtro-condicion"
                            wire:model.live="filtroCondicion"
                            class="form-select mt-1.5 w-full">
                        <option value="">— Todas —</option>
                        @foreach (\App\Support\Examenes\MateriasAdeudadasFiltros::CONDICIONES as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filtro-inscri" class="form-label">Inscripto</label>
                    <select id="filtro-inscri"
                            wire:model.live="filtroInscri"
                            class="form-select mt-1.5 w-full">
                        <option value="">— Todos —</option>
                        <option value="si">Sí</option>
                        <option value="no">No</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <span class="se-pill tabular-nums">{{ $totalFilas }} registro{{ $totalFilas === 1 ? '' : 's' }}</span>
                <a href="{{ $pdfUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center justify-center gap-2 rounded-xl border border-primary-500 bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Generar PDF
                </a>
            </div>
        </div>
    </div>

    @if ($totalFilas === 0)
        <div class="se-card mt-6 px-6 py-12 text-center">
            <p class="text-sm text-neutral-600">No hay materias adeudadas con los filtros seleccionados.</p>
        </div>
    @else
        @php
            $porMateriaCurso = $agrupar === \App\Support\Examenes\MateriasAdeudadasFiltros::AGRUPAR_MATERIA_CURSO;
            $etiquetaCantidadGrupo = static function (int $n) use ($porMateriaCurso): string {
                if ($porMateriaCurso) {
                    return $n === 1 ? 'alumno' : 'alumnos';
                }

                return $n === 1 ? 'materia' : 'materias';
            };
        @endphp
        <div class="mt-6 space-y-4">
            @foreach ($bloques as $bloque)
                @php $cantidadGrupo = count($bloque['filas']); @endphp
                <div class="se-card overflow-hidden">
                    <div class="border-b border-accent-200 bg-accent-50 px-4 py-2.5 flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-sm font-semibold text-neutral-800">{{ $bloque['titulo'] }}</h3>
                        <span class="se-pill tabular-nums">
                            {{ $cantidadGrupo }} {{ $etiquetaCantidadGrupo($cantidadGrupo) }}
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-accent-200 text-sm">
                            <thead class="bg-white">
                                <tr>
                                    <th class="w-12 px-3 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Nº</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Apellido</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Nombre</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Año lectivo</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Curso</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Materia</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Condición</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Inscripto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-accent-100 bg-white">
                                @foreach ($bloque['filas'] as $fila)
                                    <tr class="hover:bg-accent-50/80">
                                        <td class="whitespace-nowrap px-3 py-2 text-center tabular-nums text-neutral-600">{{ $loop->iteration }}</td>
                                        <td class="whitespace-nowrap px-4 py-2 text-neutral-800">{{ $fila['apellido'] }}</td>
                                        <td class="whitespace-nowrap px-4 py-2 text-neutral-800">{{ $fila['nombre'] }}</td>
                                        <td class="whitespace-nowrap px-4 py-2 text-neutral-800">{{ $fila['ano_lectivo'] }}</td>
                                        <td class="px-4 py-2 text-neutral-800">{{ $fila['curso'] }}</td>
                                        <td class="px-4 py-2 text-neutral-800">{{ $fila['materia'] }}</td>
                                        <td class="whitespace-nowrap px-4 py-2 text-neutral-700">{{ $fila['condicion'] !== '' ? $fila['condicion'] : '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-2 text-neutral-700">{{ $fila['inscripto'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    @endif
</div>
