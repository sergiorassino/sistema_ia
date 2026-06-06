<div class="se-page max-w-3xl">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ $esEdicion ? 'Editar evaluación' : 'Registrar evaluación' }}
                </h2>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Año lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
        </div>
    </section>

    <form wire:submit="save" class="se-card space-y-5 p-5 sm:p-6">
        <div>
            <label for="se-gest-eval-curso" class="form-label">Curso</label>
            <select id="se-gest-eval-curso" wire:model.live="idCurso" class="form-select mt-1.5">
                <option value="">— Seleccione —</option>
                @foreach ($cursos as $c)
                    <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                @endforeach
            </select>
            @error('idCurso')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @if ($cursos->isEmpty())
                <p class="mt-1.5 text-xs text-amber-800">No hay cursos cargados para el año lectivo actual.</p>
            @endif
        </div>

        <div>
            <label for="se-gest-eval-fecha" class="form-label">Fecha de evaluación</label>
            <input id="se-gest-eval-fecha" type="date" wire:model="fecha" class="form-input mt-1.5">
            @error('fecha')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="se-gest-eval-materia" class="form-label">Materia</label>
            <select id="se-gest-eval-materia" wire:model="idMateria" class="form-select mt-1.5" @disabled(! $idCurso)>
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
            @if ($idCurso && $materias->isEmpty())
                <p class="mt-1.5 text-xs text-amber-800">No hay materias cargadas para este curso.</p>
            @endif
        </div>

        <div>
            <label for="se-gest-eval-temas" class="form-label">Temas</label>
            <input id="se-gest-eval-temas" type="text" wire:model="temas" maxlength="200"
                   class="form-input mt-1.5" placeholder="Contenidos o temas a evaluar">
            @error('temas')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="se-gest-eval-obs" class="form-label">Observaciones</label>
            <textarea id="se-gest-eval-obs" wire:model="obs" maxlength="255" rows="3"
                      class="form-input mt-1.5 leading-relaxed" placeholder="Opcional"></textarea>
            @error('obs')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap gap-2 border-t border-accent-200 pt-4">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">{{ $esEdicion ? 'Guardar cambios' : 'Registrar evaluación' }}</span>
                <span wire:loading wire:target="save">Guardando…</span>
            </button>
            <a href="{{ route('calificacionesSecundario.gestionSolicitudesEvaluacion.index') }}" class="btn-secondary">Volver</a>
        </div>
    </form>
</div>
