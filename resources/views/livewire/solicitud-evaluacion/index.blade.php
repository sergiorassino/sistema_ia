<div class="se-page max-w-6xl">
    @if (session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4500)"
             class="se-soft-card flex items-center gap-3 border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ session('error') }}
        </div>
    @endif

    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Solicitud de evaluación</h2>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Año lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
        </div>
    </section>

    <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
        <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label for="se-sol-eval-fecha" class="form-label">Fecha de evaluación</label>
                <input id="se-sol-eval-fecha" type="date" wire:model.live="fecha" class="form-input mt-1.5">
            </div>
            <div>
                <label for="se-sol-eval-curso" class="form-label">Curso</label>
                <select id="se-sol-eval-curso" wire:model.live="idCurso" class="form-select mt-1.5" @disabled(! $fecha)>
                    <option value="">— Seleccione —</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                    @endforeach
                </select>
                @if ($cursos->isEmpty())
                    <p class="mt-1.5 text-xs text-amber-800">No hay cursos disponibles para su usuario en el año actual.</p>
                @endif
                @error('idCurso')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
        @error('fecha')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @if ($curso && $fecha)
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-white px-5 py-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-neutral-900">
                            {{ $curso->nombreParaListado() }}
                        </p>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            Evaluaciones del {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                            · {{ $evaluaciones->count() }} / {{ $maxPorDia }} permitidas por día
                        </p>
                    </div>
                    @if ($puedeNueva)
                        <button type="button"
                                wire:click="irASolicitarNueva"
                                wire:loading.attr="disabled"
                                class="btn-primary btn-sm shrink-0">
                            <span wire:loading.remove wire:target="irASolicitarNueva">Solicitar nueva evaluación</span>
                            <span wire:loading wire:target="irASolicitarNueva">Abriendo…</span>
                        </button>
                    @endif
                </div>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="min-w-[640px] border-collapse sm:min-w-full">
                    <thead class="bg-accent-50">
                        <tr>
                            <th class="table-header w-48">Materia</th>
                            <th class="table-header">Temas</th>
                            <th class="table-header w-56">Observaciones</th>
                            <th class="table-header w-36">Registrado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-200 bg-white">
                        @forelse ($evaluaciones as $e)
                            <tr class="transition-colors hover:bg-accent-50/60">
                                <td class="table-cell">
                                    {{ $etiquetasMateria[(int) $e->idMateria] ?? ('Materia #'.$e->idMateria) }}
                                </td>
                                <td class="table-cell">{{ $e->temas ?: '—' }}</td>
                                <td class="table-cell">{{ $e->obs ?: '—' }}</td>
                                <td class="table-cell font-mono text-xs">
                                    {{ $e->fechregi?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="table-cell py-10 text-center text-sm text-neutral-500">
                                    No hay evaluaciones registradas para este curso en la fecha seleccionada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (! $puedeNueva && $evaluaciones->isNotEmpty())
                <div class="border-t border-accent-200 bg-amber-50/80 px-5 py-3 text-sm text-amber-900">
                    Se alcanzó el máximo de {{ $maxPorDia }} evaluaciones por día para este curso.
                </div>
            @endif
        </div>
    @elseif ($fecha && $idCurso)
        <div class="se-soft-card px-5 py-4 text-sm text-neutral-600">
            El curso seleccionado no está disponible en su contexto.
        </div>
    @endif
</div>
