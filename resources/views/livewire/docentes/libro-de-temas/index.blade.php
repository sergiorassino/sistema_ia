<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">{{ $modoPortalDocente ? 'Menú de Docentes' : 'Docentes / Usuarios' }}</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Libro de temas</h2>
                    <p class="text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
                @if ($modoPortalDocente)
                    <a href="{{ route('portalDocente.home') }}"
                       class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Volver al inicio
                    </a>
                @endif
            </div>
        </section>

        <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
            <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="se-ldt-curso" class="form-label">Curso</label>
                    <select id="se-ldt-curso" wire:model.live="cursoId" class="form-select mt-1.5 w-full">
                        <option value="">— Seleccione —</option>
                        @foreach ($cursos as $c)
                            <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="se-ldt-materia" class="form-label">Materia</label>
                    <select id="se-ldt-materia" wire:model.live="materiaId" class="form-select mt-1.5 w-full" @disabled(! $cursoId)>
                        <option value="">— Seleccione —</option>
                        @foreach ($materias as $m)
                            <option value="{{ $m->id }}">{{ trim((string) ($m->materia ?? '')) !== '' ? $m->materia : ('ID '.$m->id) }}</option>
                        @endforeach
                    </select>
                    @error('materiaId') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <button type="button"
                    wire:click="abrirLibro"
                    @disabled(! $cursoId || ! $materiaId)
                    class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50">
                Abrir libro
            </button>
        </div>

        @if ($cursos->isEmpty())
            <div class="se-card px-5 py-10 text-center text-sm text-neutral-600">
                @if ($modoPortalDocente)
                    No tiene materias asignadas en este ciclo lectivo.
                @else
                    No hay cursos en este ciclo lectivo.
                @endif
            </div>
        @endif
    </div>
</div>
