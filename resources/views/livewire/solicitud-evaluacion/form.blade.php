<div class="se-page max-w-6xl">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Nueva solicitud de evaluación</h2>
                <p class="text-sm text-white/80">
                    {{ $curso->nombreParaListado() }} · {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                </p>
            </div>
        </div>
    </section>

    <div class="space-y-4">
    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="text-sm font-semibold text-neutral-900">Evaluaciones ya previstas ese día</p>
            <p class="mt-0.5 text-xs text-neutral-500">
                {{ $curso->nombreParaListado() }} · {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                · {{ $evaluaciones->count() }} / {{ $maxPorDia }} permitidas
            </p>
        </div>
        <div class="w-full overflow-x-auto">
            <table class="min-w-[560px] border-collapse sm:min-w-full">
                <thead class="bg-accent-50">
                    <tr>
                        <th class="table-header w-48">Materia</th>
                        <th class="table-header">Temas</th>
                        <th class="table-header w-56">Observaciones</th>
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="table-cell py-6 text-center text-sm text-neutral-500">
                                Todavía no hay evaluaciones registradas para este curso en esa fecha.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <form wire:submit="save" class="se-card space-y-5 p-5 sm:p-6">
        <div>
            <label class="form-label">Curso</label>
            <input type="text" readonly class="form-input mt-1.5 bg-accent-50 text-neutral-600"
                   value="{{ $curso->nombreParaListado() }}">
        </div>

        <div>
            <label class="form-label">Fecha de evaluación</label>
            <input type="text" readonly class="form-input mt-1.5 bg-accent-50 text-neutral-600 font-mono"
                   value="{{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}">
        </div>

        <div>
            <label for="se-sol-eval-materia" class="form-label">Materia</label>
            <select id="se-sol-eval-materia" wire:model="idMateria" class="form-select mt-1.5">
                <option value="">— Seleccione —</option>
                @foreach ($materias as $m)
                    @php
                        $nombre = trim((string) ($m->materia ?? ''));
                        $abrev = trim((string) ($m->abrev ?? ''));
                        $label = $abrev !== '' ? $abrev.' — '.$nombre : $nombre;
                    @endphp
                    <option value="{{ $m->id }}">{{ $label !== '' ? $label : 'Materia #'.$m->id }}</option>
                @endforeach
            </select>
            @error('idMateria')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @if ($materias->isEmpty())
                <p class="mt-1.5 text-xs text-amber-800">No hay materias cargadas para este curso en el año lectivo actual.</p>
            @endif
        </div>

        <div>
            <label for="se-sol-eval-temas" class="form-label">Temas</label>
            <input id="se-sol-eval-temas" type="text" wire:model="temas" maxlength="200"
                   class="form-input mt-1.5" placeholder="Contenidos o temas a evaluar">
            @error('temas')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="se-sol-eval-obs" class="form-label">Observaciones</label>
            <textarea id="se-sol-eval-obs" wire:model="obs" maxlength="255" rows="3"
                      class="form-input mt-1.5 leading-relaxed" placeholder="Opcional"></textarea>
            @error('obs')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap gap-2 border-t border-accent-200 pt-4">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Guardar solicitud</span>
                <span wire:loading wire:target="save">Guardando…</span>
            </button>
            <a href="{{ route($rutaVolver, $volverConFiltros) }}" class="btn-secondary">Volver</a>
        </div>
    </form>
    </div>
</div>
