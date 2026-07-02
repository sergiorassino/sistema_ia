{{-- SFQ — indicadores por alumno (ic01–ic06). --}}
@php
    $soloLectura = $soloLectura ?? false;
    $mostrarModalNotasOff = $mostrarModalNotasOff ?? false;
    $mensajeNotasOff = $mensajeNotasOff ?? '';
@endphp
<div>
<div class="mx-auto w-full max-w-6xl space-y-6">
    <div class="overflow-hidden rounded-2xl bg-neutral-800 px-5 py-3 text-center">
        <p class="text-sm font-bold uppercase tracking-wide text-white">{{ $alumnoLinea }}</p>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm font-semibold text-neutral-700">
            {{ $etiquetaColumna }} · {{ $cursoLabel }}
            @if ($soloLectura)
                <span class="mt-1 block text-xs font-semibold uppercase tracking-wide text-amber-700">Solo consulta — la carga está deshabilitada</span>
            @endif
        </p>
        <a href="{{ \App\Support\PortalDocente\CalificacionesInicialSfqPortalDocente::route('index', ['curso' => $cursoId]) }}"
           class="inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-5 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-50">
            Volver
        </a>
    </div>

    <div class="se-card overflow-hidden p-0">
        <div class="w-full overflow-x-auto se-grid-angosta-wrap">
            <table class="w-full min-w-[48rem] table-fixed divide-y divide-accent-200 text-sm">
                <thead class="bg-accent-50">
                    <tr>
                        <th class="w-16 px-3 py-3 text-left text-[11px] font-semibold uppercase text-neutral-500">Sala</th>
                        <th class="w-36 px-3 py-3 text-left text-[11px] font-semibold uppercase text-neutral-500">Etapa</th>
                        <th class="w-48 px-3 py-3 text-left text-[11px] font-semibold uppercase text-neutral-500">Id Edani</th>
                        <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase text-neutral-500">Indicador</th>
                        <th class="w-40 px-3 py-3 text-left text-[11px] font-semibold uppercase text-neutral-500">Nota</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-accent-100 bg-white">
                    @forelse ($filas as $idx => $fila)
                        <tr class="hover:bg-accent-50/60" wire:key="sfq-ind-{{ $fila['id'] }}-{{ $idx }}">
                            <td class="px-3 py-2">
                                <input type="text" readonly value="{{ $sala }}"
                                       class="w-full rounded-lg border border-accent-200 bg-accent-50/80 px-2 py-1.5 text-sm text-neutral-700">
                            </td>
                            <td class="px-3 py-2">
                                <input type="text" readonly value="{{ $etiquetaEtapa }}"
                                       class="w-full rounded-lg border border-accent-200 bg-accent-50/80 px-2 py-1.5 text-sm text-neutral-700">
                            </td>
                            <td class="px-3 py-2 align-top text-xs leading-relaxed text-neutral-700">{{ $fila['edani'] ?: '—' }}</td>
                            <td class="px-3 py-2 align-top text-sm text-neutral-800">{{ $fila['indicador'] ?: '—' }}</td>
                            <td class="px-3 py-2">
                                <select wire:model="filas.{{ $idx }}.nota"
                                        @disabled($soloLectura)
                                        @if (! $soloLectura) wire:change="guardar" @endif
                                        class="form-select w-full text-sm">
                                    @foreach ($opcionesNota as $valor => $etiq)
                                        <option value="{{ $valor }}">{{ $etiq }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-neutral-500">
                                No hay indicadores configurados para esta sala y etapa.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

    @include('livewire.partials.modal-carga-notas-off', [
        'modalWireKey' => 'modal-notas-off-ini-ind',
        'modalTituloId' => 'modal-notas-off-ini-ind-titulo',
    ])
</div>

@script
<script>
    $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
</script>
@endscript
