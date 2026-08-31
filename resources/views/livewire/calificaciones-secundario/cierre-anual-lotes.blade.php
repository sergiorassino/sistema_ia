{{-- Lotes persistidos del cierre anual: listado o detalle de un lote. --}}
<div class="se-cierre-anual-fill se-matriz-list-fill">
    <div class="se-cierre-anual-grid se-cierre-anual-grid--matriz-listado">
        <section class="se-hero se-matriz-list-hero min-w-0 shrink-0">
            <div class="se-hero-inner flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-0.5">
                    <p class="se-eyebrow !text-[10px]">Calificaciones · Cierre anual</p>
                    <h2 class="font-bold tracking-tight">
                        @if ($lote)
                            Lote {{ (int) $lote->id }} · {{ \App\Support\CalificacionesSecundario\CierreAnualJournal::etiquetaOperacion((string) $lote->operacion) }}
                        @else
                            Lotes de cierre
                        @endif
                    </h2>
                    <p class="text-xs text-white/80 truncate">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo {{ schoolCtx()->terlecAno() }}
                        @if ($lote)
                            · {{ \App\Support\CalificacionesSecundario\CierreAnualJournal::formatearFecha((string) $lote->created_at) }}
                        @endif
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @if ($lote)
                        <button type="button"
                                wire:click="volverListado"
                                class="inline-flex items-center justify-center gap-1 rounded-lg border border-white/25 bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-white/20">
                            Todos los lotes
                        </button>
                    @endif
                    <a href="{{ route('calificacionesSecundario.cierreAnual') }}"
                       wire:navigate
                       class="inline-flex items-center justify-center gap-1 rounded-lg border border-white/25 bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-white/20">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Cierre anual
                    </a>
                </div>
            </div>
        </section>

        @if (! $journalListo)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">
                Faltan las tablas de registro del cierre. Aplique el SQL de esquema (<span class="font-semibold">cierre_anual_lotes</span>).
            </div>
        @elseif ($lote && $informe)
            <div class="se-card min-w-0 shrink-0 p-4">
                @include('livewire.calificaciones-secundario.partials.cierre-anual-informe', [
                    'informe' => $informe,
                    'mostrarVerFilas' => false,
                    'mostrarCerrar' => false,
                ])

                @php
                    $estado = (string) $lote->estado;
                    $puedeRevertir = $estado !== \App\Support\CalificacionesSecundario\CierreAnualJournal::ESTADO_REVERTIDO
                        && (int) $lote->actualizados > 0;
                @endphp

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <span @class([
                            'se-pill !py-1 text-[10px]',
                            '!bg-emerald-100 !text-emerald-900' => $estado === 'aplicado',
                            '!bg-amber-100 !text-amber-900' => $estado === 'revertido_parcial',
                            '!bg-neutral-200 !text-neutral-700' => $estado === 'revertido',
                        ])>{{ \App\Support\CalificacionesSecundario\CierreAnualJournal::etiquetaEstado($estado) }}</span>
                        @if ($lote->revertido_at)
                            <p class="text-xs text-neutral-600">
                                Reversión: {{ \App\Support\CalificacionesSecundario\CierreAnualJournal::formatearFecha((string) $lote->revertido_at) }}
                                @if (trim((string) ($lote->nombre_profesor_reverso ?? '')) !== '')
                                    · {{ $lote->nombre_profesor_reverso }}
                                @endif
                                · restauradas {{ (int) $lote->revertidos_ok }}
                                · sin tocar {{ (int) $lote->revertidos_omitidos }}
                            </p>
                        @endif
                    </div>
                    @if ($puedeRevertir)
                        <button type="button"
                                class="btn-secondary border-amber-300 text-amber-950 hover:bg-amber-50"
                                wire:loading.attr="disabled"
                                wire:target="revertirLote"
                                x-on:click="window.seSwalConfirmar(
                                    'Se restaurarán las calificaciones de este lote solo si siguen igual a como las dejó el cierre. Las que se editaron después (libro matriz, exámenes) no se tocan.',
                                    'Revertir este cierre',
                                    { confirmButtonText: 'Sí, revertir', icon: 'warning' }
                                ).then(ok => ok && $wire.revertirLote())">
                            <span wire:loading.remove wire:target="revertirLote">Revertir este lote</span>
                            <span wire:loading wire:target="revertirLote">Revirtiendo…</span>
                        </button>
                    @endif
                </div>

                @if ($hayPosterior)
                    <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                        Hay un cierre posterior en este ciclo. Conviene revertir el más reciente primero.
                    </p>
                @endif

                @if (! empty($informeReverso))
                    <p class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-950" role="status">
                        Última reversión: {{ (int) $informeReverso['ok'] }} restaurada(s),
                        {{ (int) $informeReverso['omitidos'] }} sin tocar.
                    </p>
                @endif
            </div>

            <div class="se-matriz-list-toolbar">
                <div class="relative min-w-0 flex-1 sm:max-w-sm">
                    <label for="se-cierre-lote-buscar" class="sr-only">Buscar en el lote</label>
                    <input id="se-cierre-lote-buscar"
                           type="search"
                           wire:model.live.debounce.350ms="buscar"
                           class="form-input w-full !py-1.5 text-sm"
                           placeholder="Apellido, DNI o materia…"
                           autocomplete="off">
                </div>
                <label for="se-cierre-lote-tipo" class="sr-only">Tipo</label>
                <select id="se-cierre-lote-tipo" wire:model.live="filtroTipo" class="form-input !w-auto !py-1.5 text-sm">
                    <option value="">Todos los tipos</option>
                    <option value="matriz">Matriz</option>
                    <option value="previa">Previa</option>
                </select>
                <p class="se-pill tabular-nums shrink-0 !py-1 text-[10px]">{{ $filas?->total() ?? 0 }}</p>
            </div>

            <div class="se-card flex min-h-0 min-w-0 flex-col p-0">
                <div class="se-cierre-anual-grilla se-matriz-list-grilla">
                    <div class="se-cierre-anual-head-wrap" data-se-cierre-head>
                        <div class="se-cierre-anual-lote-tabla-wide">
                            <table class="se-cierre-anual-tabla table-fixed">
                                <colgroup>
                                    <col style="width:12rem">
                                    <col style="width:6rem">
                                    <col style="width:7rem">
                                    <col style="width:10rem">
                                    <col style="width:5rem">
                                    <col style="width:5.5rem">
                                    <col style="width:7rem">
                                    <col style="width:5.5rem">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th scope="col" class="text-left">Alumno</th>
                                        <th scope="col" class="text-left">DNI</th>
                                        <th scope="col" class="text-left">Curso</th>
                                        <th scope="col" class="text-left">Materia</th>
                                        <th scope="col" class="text-center">Tipo</th>
                                        <th scope="col" class="text-center">Estado fila</th>
                                        <th scope="col" class="text-center">Apro antes →</th>
                                        <th scope="col" class="text-center">Apro después</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                    <div class="se-cierre-anual-body-wrap" tabindex="0" data-se-cierre-body>
                        <div class="se-cierre-anual-lote-tabla-wide">
                            <table class="se-cierre-anual-tabla table-fixed divide-y divide-accent-100">
                                <colgroup>
                                    <col style="width:12rem">
                                    <col style="width:6rem">
                                    <col style="width:7rem">
                                    <col style="width:10rem">
                                    <col style="width:5rem">
                                    <col style="width:5.5rem">
                                    <col style="width:7rem">
                                    <col style="width:5.5rem">
                                </colgroup>
                                <tbody class="bg-white">
                                    @forelse ($filas as $f)
                                        <tr class="hover:bg-accent-50/60" wire:key="cierre-fila-{{ $f->id }}">
                                            <td class="font-medium leading-tight text-neutral-800">{{ $f->apellido }}, {{ $f->nombre }}</td>
                                            <td class="whitespace-nowrap tabular-nums text-neutral-700">{{ trim((string) $f->dni) !== '' ? $f->dni : '—' }}</td>
                                            <td class="text-neutral-700">{{ trim((string) $f->curso) !== '' ? $f->curso : '—' }}</td>
                                            <td class="font-medium text-neutral-800">{{ $f->materia !== '' ? $f->materia : '—' }}</td>
                                            <td class="text-center">
                                                <span @class([
                                                    'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                                    'bg-emerald-100 text-emerald-900' => $f->tipo === 'matriz',
                                                    'bg-amber-100 text-amber-900' => $f->tipo === 'previa',
                                                ])>{{ \App\Support\CalificacionesSecundario\CierreAnualJournal::etiquetaTipo((string) $f->tipo) }}</span>
                                            </td>
                                            <td class="text-center text-[11px] text-neutral-600">
                                                {{ $f->revertida_at ? 'Revertida' : 'Aplicada' }}
                                            </td>
                                            <td class="text-center tabular-nums text-neutral-600">{{ $f->apro_antes }}</td>
                                            <td class="text-center tabular-nums text-neutral-800">{{ $f->apro_despues }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="!px-5 !py-10 text-center text-sm text-neutral-500">
                                                Este lote no tiene filas con el filtro actual.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @if ($filas && $filas->hasPages())
                    <div class="se-matriz-list-footer">
                        {{ $filas->links('vendor.pagination.se-compact') }}
                    </div>
                @endif
            </div>
        @else
            <div class="se-card flex min-h-0 min-w-0 flex-col p-0">
                <div class="se-cierre-anual-grilla se-matriz-list-grilla">
                    <div class="se-cierre-anual-head-wrap" data-se-cierre-head>
                        <table class="se-cierre-anual-tabla w-full table-fixed">
                            <colgroup>
                                <col style="width:22%">
                                <col style="width:14%">
                                <col style="width:22%">
                                <col style="width:12%">
                                <col style="width:16%">
                                <col style="width:8rem">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col" class="text-left">Fecha</th>
                                    <th scope="col" class="text-left">Operación</th>
                                    <th scope="col" class="text-left">Usuario</th>
                                    <th scope="col" class="text-right">Actualizados</th>
                                    <th scope="col" class="text-left">Estado</th>
                                    <th scope="col" class="text-right">Acción</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                    <div class="se-cierre-anual-body-wrap" tabindex="0" data-se-cierre-body>
                        <table class="se-cierre-anual-tabla w-full table-fixed divide-y divide-accent-100">
                            <colgroup>
                                <col style="width:22%">
                                <col style="width:14%">
                                <col style="width:22%">
                                <col style="width:12%">
                                <col style="width:16%">
                                <col style="width:8rem">
                            </colgroup>
                            <tbody class="bg-white">
                                @forelse ($lotes as $item)
                                    <tr class="hover:bg-accent-50/60" wire:key="cierre-lote-{{ $item->id }}">
                                        <td class="whitespace-nowrap tabular-nums text-sm text-neutral-800">
                                            {{ \App\Support\CalificacionesSecundario\CierreAnualJournal::formatearFecha((string) $item->created_at) }}
                                        </td>
                                        <td class="text-sm font-medium text-neutral-800">
                                            {{ \App\Support\CalificacionesSecundario\CierreAnualJournal::etiquetaOperacion((string) $item->operacion) }}
                                        </td>
                                        <td class="text-sm text-neutral-700">{{ $item->nombre_profesor !== '' ? $item->nombre_profesor : '—' }}</td>
                                        <td class="text-right tabular-nums text-sm text-neutral-800">{{ (int) $item->actualizados }}</td>
                                        <td>
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                                'bg-emerald-100 text-emerald-900' => $item->estado === 'aplicado',
                                                'bg-amber-100 text-amber-900' => $item->estado === 'revertido_parcial',
                                                'bg-neutral-200 text-neutral-700' => $item->estado === 'revertido',
                                            ])>{{ \App\Support\CalificacionesSecundario\CierreAnualJournal::etiquetaEstado((string) $item->estado) }}</span>
                                        </td>
                                        <td class="text-right">
                                            <button type="button"
                                                    wire:click="abrirLote({{ (int) $item->id }})"
                                                    class="btn-secondary btn-sm !px-2 !py-1 text-[11px]">
                                                Ver
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="!px-5 !py-10 text-center text-sm text-neutral-500">
                                            Todavía no hay cierres registrados en este ciclo lectivo.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($lotes && $lotes->hasPages())
                    <div class="se-matriz-list-footer">
                        {{ $lotes->links('vendor.pagination.se-compact') }}
                    </div>
                @endif
            </div>
        @endif
    </div>
    @script
    <script>
        $wire.on('se-swal-exito', (event) => {
            const mensaje = event?.mensaje ?? event?.detail?.mensaje ?? 'Listo.';
            if (typeof window.seSwalExito === 'function') window.seSwalExito(mensaje);
        });
        $wire.on('se-swal-error', (event) => {
            const mensaje = event?.mensaje ?? event?.detail?.mensaje ?? 'No se pudo completar la acción.';
            if (typeof window.seSwalError === 'function') window.seSwalError(mensaje);
        });
    </script>
    @endscript
</div>
