<div class="se-page max-w-4xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Resúmenes</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Listado de estudiantes por cuota</h1>
                <p class="text-xs text-white/75">
                    Ciclo lectivo {{ $ano }} · Cuotas generadas ordenadas por cuota, nivel y apellido-nombre
                </p>
            </div>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
            <p class="text-sm text-neutral-700">
                Ajuste los filtros y genere un PDF para imprimir. Sin filtros se listan todas las cuotas generadas del alcance institucional.
            </p>
        </div>

        <div class="grid gap-4 px-4 py-4 sm:grid-cols-2 sm:px-5">
            <div class="sm:col-span-2">
                <span class="form-label">Año lectivo de la cuota</span>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <select wire:model.live="anoOp"
                            class="form-input w-full min-w-[9rem] max-w-[11rem] shrink-0"
                            aria-label="Comparador año lectivo">
                        @foreach ($opcionesComparador as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="idTerlecCuota"
                            class="form-input w-full min-w-[6rem] flex-1"
                            @disabled($anoOp === '')
                            aria-label="Año lectivo de la cuota">
                        <option value="0">—</option>
                        @foreach ($terlecs as $terlec)
                            <option value="{{ (int) $terlec->id }}">{{ (int) $terlec->ano }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="nivel-estudiantes-cuota" class="form-label">Nivel</label>
                <select id="nivel-estudiantes-cuota"
                        wire:model.live="idNivel"
                        class="form-input">
                    <option value="0">Todos</option>
                    @foreach ($niveles as $nivel)
                        <option value="{{ (int) $nivel['id'] }}">{{ $nivel['nombre'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="curso-estudiantes-cuota" class="form-label">Curso del año actual</label>
                <select id="curso-estudiantes-cuota"
                        wire:model.live="idCurso"
                        class="form-input">
                    <option value="0">Todos</option>
                    @foreach ($cursos as $curso)
                        <option value="{{ (int) $curso->Id }}">{{ $etiquetaCurso($curso) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="cuota-estudiantes-cuota" class="form-label">Cuota</label>
                <select id="cuota-estudiantes-cuota"
                        wire:model.live="idCuota"
                        class="form-input">
                    <option value="0">Todas</option>
                    @foreach ($cuotas as $cuota)
                        @php
                            $anoCuota = (int) ($cuota->terlec_ano ?? 0);
                            $nombreCuota = trim((string) ($cuota->nombre ?? ''));
                        @endphp
                        <option value="{{ (int) $cuota->id }}">
                            {{ $anoCuota > 0 ? $anoCuota.' — '.$nombreCuota : $nombreCuota }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <span class="form-label">Importe</span>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <select wire:model.live="importeOp"
                            class="form-input w-full min-w-[9rem] max-w-[11rem] shrink-0"
                            aria-label="Comparador importe">
                        @foreach ($opcionesComparador as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <input type="text"
                           inputmode="decimal"
                           placeholder="0,00"
                           wire:model.live="importeValor"
                           class="form-input w-full min-w-[6rem] flex-1 tabular-nums"
                           @disabled($importeOp === '')
                           aria-label="Importe" />
                </div>
            </div>

            <div>
                <span class="form-label">Pagado</span>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <select wire:model.live="pagadoOp"
                            class="form-input w-full min-w-[9rem] max-w-[11rem] shrink-0"
                            aria-label="Comparador pagado">
                        @foreach ($opcionesComparador as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <input type="text"
                           inputmode="decimal"
                           placeholder="0,00"
                           wire:model.live="pagadoValor"
                           class="form-input w-full min-w-[6rem] flex-1 tabular-nums"
                           @disabled($pagadoOp === '')
                           aria-label="Pagado" />
                </div>
            </div>
        </div>

        @if ($pdfUrl !== '#')
            <div class="border-t border-accent-200 bg-accent-50/60 px-4 py-4 sm:px-5">
                <p class="mb-3 text-sm text-neutral-600">
                    Se generará un PDF apaisado con el listado según los filtros elegidos.
                </p>
                <a href="{{ $pdfUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir listado (PDF)
                </a>
            </div>
        @else
            <div class="border-t border-accent-200 px-4 py-6 sm:px-5">
                <p class="text-center text-sm text-neutral-600 sm:text-left">
                    Complete los comparadores seleccionados: año lectivo, importe o pagado.
                </p>
            </div>
        @endif
    </div>
</div>
