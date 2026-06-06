<div class="se-page max-w-6xl" x-data>
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Aspirantes</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Instancias de registro</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Historial de ventanas de inscripción configuradas para este nivel.
                    Cada instancia tiene su propia URL pública y ciclo lectivo.
                </p>
            </div>
            <a href="{{ route('aspirantes.instancia.create') }}" class="btn-primary shrink-0">
                Nueva instancia
            </a>
        </div>
    </section>

    @if (session('status'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             @class([
                 'se-soft-card flex items-center gap-3 px-4 py-3 text-sm',
                 'border-green-200 bg-green-50 text-green-800' => ! str_contains(session('status'), 'No se puede'),
                 'border-amber-200 bg-amber-50 text-amber-900' => str_contains(session('status'), 'No se puede'),
             ])>
            {{ session('status') }}
        </div>
    @endif

    <div class="se-card overflow-hidden">
        <div class="w-full overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
                <thead class="bg-accent-50">
                    <tr>
                        <th class="table-header w-24">Ciclo</th>
                        <th class="table-header">Título</th>
                        <th class="table-header w-44">Fechas</th>
                        <th class="table-header w-28">Estado</th>
                        <th class="table-header w-24 text-center">Inscriptos</th>
                        <th class="table-header w-40 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-accent-200 bg-white">
                    @forelse ($instancias as $i)
                        <tr wire:key="aspiento-{{ $i->getKey() }}">
                            <td class="table-cell whitespace-nowrap">
                                <span class="font-medium text-neutral-800">{{ $i->anoLectivo() ?? '—' }}</span>
                                @if ((int) $i->idTerlec === (int) $idTerlecActivo)
                                    <span class="mt-0.5 block text-[10px] font-semibold uppercase tracking-wide text-primary-700">Ciclo activo</span>
                                @endif
                            </td>
                            <td class="table-cell min-w-[10rem]">
                                {{ $i->titulo ?: '—' }}
                            </td>
                            <td class="table-cell whitespace-nowrap text-neutral-700">
                                {{ optional($i->fechdesde)->format('d/m/Y') }}
                                –
                                {{ optional($i->fechhasta)->format('d/m/Y') }}
                            </td>
                            <td class="table-cell">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-green-100 text-green-800' => $i->aceptaRegistros(),
                                    'bg-neutral-200 text-neutral-700' => ! $i->aceptaRegistros(),
                                ])>
                                    {{ $i->aceptaRegistros() ? 'Abierta' : ($i->activo ? 'Fuera de fecha' : 'Cerrada') }}
                                </span>
                            </td>
                            <td class="table-cell text-center tabular-nums">
                                {{ $i->aspirantes_count }}
                            </td>
                            <td class="table-cell text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('aspirantes.instancia.edit', ['id' => $i->getKey()]) }}"
                                       class="btn-secondary btn-sm">
                                        Editar
                                    </a>
                                    @if ($i->aspirantes_count > 0)
                                        <button type="button"
                                                disabled
                                                title="Hay aspirantes registrados; no se puede eliminar."
                                                class="btn-secondary btn-sm cursor-not-allowed opacity-50">
                                            Eliminar
                                        </button>
                                    @else
                                        <button type="button"
                                                wire:click="eliminar({{ $i->getKey() }})"
                                                wire:confirm="¿Eliminar esta instancia de registro? La URL pública dejará de funcionar."
                                                class="btn-secondary btn-sm text-red-700 border-red-200 hover:bg-red-50">
                                            Eliminar
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="table-cell py-12 text-center text-neutral-500">
                                Todavía no hay instancias de registro para este nivel.
                                <a href="{{ route('aspirantes.instancia.create') }}" class="mt-2 inline-block text-primary-700 underline">
                                    Crear la primera
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
