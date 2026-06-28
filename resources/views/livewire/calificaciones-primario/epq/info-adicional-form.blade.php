<div class="mx-auto w-full max-w-5xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Primario · EPQ</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Carga de calificaciones — Información adicional</h2>
                <p class="max-w-3xl text-sm text-white/80">
                    <span class="font-semibold text-white">{{ $alumnoLinea }}</span>
                    <span class="block sm:inline sm:before:content-['·'] sm:before:mx-2">{{ $cursoLabel }}</span>
                </p>
            </div>
            <a href="{{ \App\Support\PortalDocente\CalificacionesPrimarioPortalDocente::route('carga', ['curso' => $cursoId]) }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Volver
            </a>
        </div>
    </section>

    <p class="text-xs text-neutral-500">Los datos se guardan al salir de cada campo.</p>

    <div class="space-y-5">
        @foreach ([
            ['Inasistencias', ['md01' => '1º Trim', 'md02' => '2º Trim', 'md03' => '3º Trim', 'md04' => 'Total']],
            ['Respeto por las Normas de Convivencia', ['md05' => '1º Trim', 'md06' => '2º Trim', 'md07' => '3º Trim', 'md08' => 'Total']],
            ['Llamados de Atención', ['md09' => '1º Trim', 'md10' => '2º Trim', 'md11' => '3º Trim', 'md12' => 'Total']],
            ['Acompañamiento Familiar', ['md13' => '1º Trim', 'md14' => '2º Trim', 'md15' => '3º Trim', 'md16' => 'Total']],
        ] as [$titulo, $mapa])
            <div class="se-card p-4">
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-primary-700">{{ $titulo }}</h3>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ($mapa as $campo => $label)
                        <div>
                            <label class="form-label">{{ $label }}</label>
                            <input type="text"
                                   class="form-input mt-1 w-full text-center"
                                   maxlength="120"
                                   value="{{ $campos[$campo] ?? '' }}"
                                   wire:key="epq-info-{{ $campo }}"
                                   wire:blur="saveCampo('{{ $campo }}', $event.target.value)" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="se-card p-4">
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-primary-700">Habilidades Intelectuales y Prácticas</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse text-sm">
                    <thead>
                        <tr class="bg-accent-50">
                            <th class="border border-accent-200 p-2 text-left text-[10px] uppercase"></th>
                            <th class="border border-accent-200 p-2 text-center text-[10px] uppercase">1º Trim</th>
                            <th class="border border-accent-200 p-2 text-center text-[10px] uppercase">2º Trim</th>
                            <th class="border border-accent-200 p-2 text-center text-[10px] uppercase">3º Trim</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([
                            'Se destaca en:' => ['md17', 'md18', 'md19'],
                            'Trabaja bien en:' => ['md20', 'md21', 'md22'],
                            'Debe mejorar en:' => ['md23', 'md24', 'md25'],
                        ] as $fila => $cols)
                            <tr>
                                <td class="border border-accent-200 bg-accent-50/50 p-2 font-medium text-neutral-700">{{ $fila }}</td>
                                @foreach ($cols as $campo)
                                    <td class="border border-accent-200 p-1">
                                        <textarea rows="3"
                                                  class="form-input w-full resize-y text-sm"
                                                  wire:key="epq-hab-{{ $campo }}"
                                                  wire:blur="saveCampo('{{ $campo }}', $event.target.value)">{{ $campos[$campo] ?? '' }}</textarea>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="se-card p-4">
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-primary-700">Habilidades Personales y Sociales</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse text-sm">
                    <thead>
                        <tr class="bg-accent-50">
                            <th class="border border-accent-200 p-2 text-left text-[10px] uppercase"></th>
                            <th class="border border-accent-200 p-2 text-center text-[10px] uppercase">1º Trim</th>
                            <th class="border border-accent-200 p-2 text-center text-[10px] uppercase">2º Trim</th>
                            <th class="border border-accent-200 p-2 text-center text-[10px] uppercase">3º Trim</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([
                            'Colabora en tareas grupales' => ['md26', 'md27', 'md28'],
                            'Asume tareas por sí mismo' => ['md29', 'md30', 'md31'],
                            'Acepta normas grupales e institucionales de convivencia' => ['md32', 'md33', 'md34'],
                            'Establece contacto social con sus pares' => ['md35', 'md36', 'md37'],
                        ] as $fila => $cols)
                            <tr>
                                <td class="border border-accent-200 bg-accent-50/50 p-2 text-neutral-700">{{ $fila }}</td>
                                @foreach ($cols as $campo)
                                    <td class="border border-accent-200 p-1">
                                        <input type="text"
                                               class="form-input w-full text-center"
                                               maxlength="120"
                                               value="{{ $campos[$campo] ?? '' }}"
                                               wire:key="epq-soc-{{ $campo }}"
                                               wire:blur="saveCampo('{{ $campo }}', $event.target.value)" />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
