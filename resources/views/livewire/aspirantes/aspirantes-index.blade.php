<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Aspirantes</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Aspirantes registrados</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <a href="{{ route('aspirantes.instancia') }}" class="btn-secondary">Instancias de registro</a>
            </div>
        </div>
    </section>

    @if (! $instancia)
        <div class="se-card p-8 text-center">
            <p class="text-sm text-neutral-600">
                Todavía no hay una instancia de registro creada para este nivel y ciclo lectivo.
            </p>
            <a href="{{ route('aspirantes.instancia.create') }}" class="btn-primary mt-4">Crear instancia</a>
        </div>
    @else
        <div class="se-card overflow-hidden">
            <div class="se-toolbar flex flex-col gap-3 border-b border-accent-200 bg-accent-50/60 px-5 py-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0 flex-1 sm:max-w-sm">
                    <label class="se-label">Buscar</label>
                    <input type="search" wire:model.live.debounce.300ms="buscar"
                           placeholder="Apellido, nombre o DNI"
                           class="form-input w-full">
                </div>
                <div class="text-xs text-neutral-500">
                    @if ($instancia->titulo)
                        <span class="font-medium text-neutral-700">{{ $instancia->titulo }}</span>
                        ·
                    @endif
                    Instancia
                    <span @class([
                        'inline-block rounded-md px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide',
                        'bg-green-100 text-green-800' => $instancia->aceptaRegistros(),
                        'bg-neutral-200 text-neutral-700' => ! $instancia->aceptaRegistros(),
                    ])>
                        {{ $instancia->aceptaRegistros() ? 'Activa' : 'Cerrada' }}
                    </span>
                    · {{ optional($instancia->fechdesde)->format('d/m/Y') }} – {{ optional($instancia->fechhasta)->format('d/m/Y') }}
                </div>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="bg-accent-50">
                        <tr>
                            <th class="table-header">Fecha</th>
                            <th class="table-header">Curso solicitado</th>
                            @foreach ($columnas as $etiqueta => $col)
                                <th class="table-header">{{ $etiqueta ?: $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-200 bg-white">
                        @forelse ($aspirantes as $a)
                            <tr wire:key="asp-{{ $a->id }}">
                                <td class="table-cell">{{ optional($a->created_at)->format('d/m/Y H:i') }}</td>
                                <td class="table-cell">
                                    {{ $cursosModelo[$a->idCursoModelo]?->nombre ?? '—' }}
                                </td>
                                @foreach ($columnas as $etiqueta => $col)
                                    <td class="table-cell">{{ $a->{$col} }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 2 + $columnas->count() }}" class="table-cell py-10 text-center text-neutral-500">
                                    Aún no hay aspirantes registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($aspirantes->count() > 0)
                <div class="px-5 py-3">{{ $aspirantes->links() }}</div>
            @endif
        </div>
    @endif
</div>
