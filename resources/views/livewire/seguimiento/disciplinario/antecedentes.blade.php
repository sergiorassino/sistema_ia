<div class="se-page max-w-6xl">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Seguimiento</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Antecedentes disciplinarios</h2>
                <p class="text-sm text-white/85">
                    {{ $base->legajo?->apellido }}, {{ $base->legajo?->nombre }}
                </p>
                <p class="text-xs text-white/65">{{ schoolCtx()->nivelNombre() }}</p>
            </div>
            <div class="flex shrink-0 flex-col gap-2 sm:flex-row sm:items-center">
                <x-pdf-post
                    :action="route('seguimiento.disciplinario.antecedentes.pdf')"
                    :matricula="$base->id"
                    button-class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                    Imprimir PDF
                </x-pdf-post>
                <x-nav-contexto-estudiante
                    destino="seguimiento.disciplinario"
                    :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO"
                    :curso="$base->idCursos"
                    :matricula="$base->id"
                    class="inline">
                    <span class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                        Volver
                    </span>
                </x-nav-contexto-estudiante>
            </div>
        </div>
    </section>

    @if ($porAno->isEmpty())
        <div class="se-card p-10">
            <p class="text-center text-sm text-neutral-600">Sin antecedentes disciplinarios.</p>
        </div>
    @else
        <div class="space-y-5">
            @foreach ($porAno as $ano => $items)
                <div class="se-card overflow-hidden">
                    <div class="border-b border-accent-200 bg-accent-50/80 px-5 py-3">
                        <p class="text-sm font-semibold text-neutral-800">
                            Año lectivo: {{ $ano > 0 ? $ano : '—' }}
                            <span class="ml-2 text-xs font-normal text-neutral-500">({{ $items->count() }} evento/s)</span>
                        </p>
                    </div>
                    <div class="w-full overflow-x-auto">
                        <div class="flex justify-start">
                            <table class="min-w-[640px] border-collapse sm:min-w-full">
                                <thead class="bg-accent-50">
                                    <tr>
                                        <th class="table-header w-28">Fecha</th>
                                        <th class="table-header w-40">Curso</th>
                                        <th class="table-header w-56">Tipo</th>
                                        <th class="table-header w-24">Cant.</th>
                                        <th class="table-header">Motivo</th>
                                        <th class="table-header w-40">Solicitada por</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-accent-200 bg-white">
                                    @foreach ($items as $s)
                                        <tr class="hover:bg-accent-50/50">
                                            <td class="table-cell font-mono">{{ $s->fecha?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="table-cell">{{ $s->matricula?->curso?->nombreParaListado() ?? '—' }}</td>
                                            <td class="table-cell">{{ $s->tipo?->tipo ?? ('#'.$s->idTipoSancion) }}</td>
                                            <td class="table-cell font-mono">{{ $s->cantidad ?? '—' }}</td>
                                            <td class="table-cell">{{ $s->motivo ?? '—' }}</td>
                                            <td class="table-cell">{{ $s->solipor ?: ($s->profesor?->nombre_completo ?? '—') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
