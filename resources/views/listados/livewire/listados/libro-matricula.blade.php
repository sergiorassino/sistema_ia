<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Listados</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Libro de Matrícula</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <div class="se-card p-6 sm:p-8 max-w-xl">
        <p class="text-sm text-neutral-600 mb-6">
            Genera el libro de matrícula en hoja Legal/Oficio apaisada. Incluye alumnos regulares matriculados
            hasta la fecha indicada y una hoja en blanco al final para anotaciones manuales.
        </p>

        <div class="space-y-2">
            <label for="inscriptos-al" class="form-label">
                Alumnos inscriptos al: <span class="text-red-600">*</span>
            </label>
            <input
                id="inscriptos-al"
                type="date"
                wire:model="inscriptosAl"
                class="form-input @error('inscriptosAl') border-red-400 @enderror"
                required
            >
            @error('inscriptosAl')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ $inscriptosAl !== '' ? route('listados.libro-matricula.pdf', ['inscriptos_al' => $inscriptosAl]) : '#' }}"
               target="_blank"
               rel="noopener noreferrer"
               @class([
                   'inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold shadow-sm transition-colors',
                   'bg-primary-600 text-white hover:bg-primary-700' => $inscriptosAl !== '',
                   'pointer-events-none bg-neutral-200 text-neutral-400' => $inscriptosAl === '',
               ])>
                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Generar PDF
            </a>
        </div>
    </div>
</div>
