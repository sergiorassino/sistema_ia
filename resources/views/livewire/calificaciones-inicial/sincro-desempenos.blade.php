{{-- Descargar Desempeños desde GE (inicial) --}}
<div class="mx-auto w-full max-w-5xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Inicial</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Descargar Desempeños desde GE</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    Desempeño e inasistencias de etapa exportados desde <strong>GE</strong> (nivel inicial) ·
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al panel
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
            {{ session('success') }}
        </div>
    @endif
    @if (session('warning'))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">
            {{ session('warning') }}
        </div>
    @endif

    <div class="se-card space-y-5 p-5 sm:p-6">
        <div class="space-y-2 text-sm text-neutral-600">
            <p>Suba el archivo <strong>CSV</strong> de desempeños exportado desde <strong>GE</strong> (separador <strong>punto y coma</strong>).</p>
            <ul class="list-disc space-y-1 pl-5">
                <li>Elija si corresponde a <strong>Primera etapa</strong> o <strong>Segunda etapa</strong> antes de descargar.</li>
                <li>Primera etapa actualiza <code class="text-xs">obs1</code>, <code class="text-xs">just1</code> e <code class="text-xs">inju1</code> en <code class="text-xs">matricula</code>.</li>
                <li>Segunda etapa actualiza <code class="text-xs">obs2</code>, <code class="text-xs">just2</code> e <code class="text-xs">inju2</code>.</li>
                <li>La sala se reconoce como <em>SALA DE 3/4/5 AÑOS</em> (o equivalentes). El alumno se identifica por <strong>DNI</strong> y debe estar matriculado en el curso/división del archivo para el ciclo lectivo activo.</li>
                <li>Si el texto de desempeño contiene «;», el sistema reconstruye la columna automáticamente (como en el importador legacy).</li>
            </ul>
        </div>

        <form wire:submit.prevent="importar" class="space-y-4">
            <div>
                <span class="form-label">Etapa</span>
                <div class="mt-2 flex flex-wrap gap-4">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold transition has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:text-primary-900">
                        <input type="radio" name="etapa" value="1" wire:model.live="etapa" class="text-primary-600 focus:ring-primary-500">
                        Primera etapa
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold transition has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:text-primary-900">
                        <input type="radio" name="etapa" value="2" wire:model.live="etapa" class="text-primary-600 focus:ring-primary-500">
                        Segunda etapa
                    </label>
                </div>
                @error('etapa')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <span class="form-label">Archivo CSV</span>

                <label for="sincro-desempenos-inicial-csv"
                       class="mt-1.5 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-4 py-8 transition-colors
                              @error('archivoCsv') border-red-300 bg-red-50/50 @else border-accent-200 bg-accent-50/40 hover:border-primary-400 hover:bg-primary-50/30 @enderror">
                    <svg class="mb-3 h-10 w-10 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <span class="text-sm font-semibold text-primary-800">Haga clic para elegir el CSV</span>
                    <span class="mt-1 text-xs text-neutral-500">o arrástrelo aquí · máx. 15 MB · extensión .csv</span>
                    <input id="sincro-desempenos-inicial-csv"
                           type="file"
                           class="sr-only"
                           wire:model.live="archivoCsv"
                           accept=".csv,text/csv,text/plain">
                </label>

                <div wire:loading wire:target="archivoCsv" class="mt-2 flex items-center gap-2 text-xs text-neutral-600">
                    <svg class="h-4 w-4 animate-spin text-primary-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Subiendo archivo al servidor…
                </div>

                @if ($archivoNombre && $encabezadoValido)
                    <div class="mt-3 flex flex-wrap items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                        <svg class="h-5 w-5 shrink-0 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="min-w-0 flex-1 truncate font-medium" title="{{ $archivoNombre }}">{{ $archivoNombre }}</span>
                        @if ($archivoTamanioKb)
                            <span class="se-pill text-emerald-800">{{ $archivoTamanioKb }} KB</span>
                        @endif
                        <span class="text-xs text-emerald-800">Formato GE de desempeños reconocido</span>
                        <button type="button"
                                wire:click="quitarArchivo"
                                wire:loading.attr="disabled"
                                wire:target="quitarArchivo,importar"
                                class="ml-auto text-xs font-semibold text-emerald-900 underline hover:no-underline">
                            Quitar
                        </button>
                    </div>
                @endif

                @error('archivoCsv')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="importar,archivoCsv"
                        @disabled(! $encabezadoValido)
                        class="btn-primary disabled:cursor-not-allowed disabled:opacity-50">
                    <span wire:loading.remove wire:target="importar,archivoCsv">Descargar desempeños</span>
                    <span wire:loading wire:target="archivoCsv">Espere, subiendo archivo…</span>
                    <span wire:loading wire:target="importar">Procesando importación…</span>
                </button>
                @if ($ultimoResultado)
                    <button type="button" wire:click="limpiarResultado" class="btn-secondary btn-sm">
                        Ocultar detalle
                    </button>
                @endif
            </div>
        </form>
    </div>

    @if ($ultimoResultado)
        @php
            $r = $ultimoResultado;
            $issues = $r['issues'] ?? [];
            $etapaTxt = ($r['etapa'] ?? 1) == 2 ? 'Segunda etapa' : 'Primera etapa';
        @endphp
        <div class="se-card space-y-4 p-5 sm:p-6">
            <h3 class="text-lg font-semibold text-neutral-900">Resultado de la descarga ({{ $etapaTxt }})</h3>
            <p class="text-sm text-neutral-700">{{ $r['message'] ?? '' }}</p>

            <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                <div class="rounded-xl bg-accent-50 px-3 py-2">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Filas leídas</dt>
                    <dd class="text-lg font-bold text-neutral-900">{{ $r['totalDataRows'] ?? 0 }}</dd>
                </div>
                <div class="rounded-xl bg-emerald-50 px-3 py-2">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-emerald-800">Actualizadas</dt>
                    <dd class="text-lg font-bold text-emerald-900">{{ $r['updatedRows'] ?? 0 }}</dd>
                </div>
                <div class="rounded-xl bg-amber-50 px-3 py-2">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-amber-800">Omitidas</dt>
                    <dd class="text-lg font-bold text-amber-950">{{ $r['skippedRows'] ?? 0 }}</dd>
                </div>
            </dl>

            @if (! ($r['committed'] ?? false))
                <p class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                    No se aplicaron cambios en la base de datos (ninguna fila válida para actualizar o se revirtió la operación).
                </p>
            @endif

            @if (count($issues) > 0)
                <div class="space-y-2">
                    <p class="text-sm font-semibold text-neutral-800">
                        Detalle de problemas ({{ count($issues) }}@if ($r['issuesTruncated'] ?? false), se muestran las primeras {{ count($issues) }}@endif)
                    </p>
                    <div class="max-h-96 overflow-auto rounded-xl border border-accent-200">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 bg-accent-50 text-[10px] font-semibold uppercase tracking-wide text-neutral-600">
                                <tr>
                                    <th class="px-3 py-2">Línea</th>
                                    <th class="px-3 py-2">Tipo</th>
                                    <th class="px-3 py-2">Mensaje</th>
                                    <th class="px-3 py-2">Contexto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-accent-100">
                                @foreach ($issues as $issue)
                                    <tr class="hover:bg-accent-50/80">
                                        <td class="whitespace-nowrap px-3 py-2 font-mono">{{ $issue['line'] ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2">{{ $issue['code'] ?? '' }}</td>
                                        <td class="px-3 py-2">{{ $issue['message'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-neutral-600">{{ $issue['detail'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
