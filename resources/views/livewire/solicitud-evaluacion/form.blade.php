<div class="se-page max-w-3xl">
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
