<div class="se-page max-w-3xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Resúmenes</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Estadística de pago por curso y cuota</h1>
                <p class="text-xs text-white/75">
                    Ciclo lectivo {{ $ano }} · Matrícula y cuotas de marzo a diciembre
                </p>
            </div>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
            <p class="text-sm text-neutral-700">
                La planilla toma las cuotas cuyo primer vencimiento es anterior o igual a la fecha de cálculo.
            </p>
        </div>

        <div class="grid gap-4 px-4 py-4 sm:grid-cols-2 sm:px-5">
            <div>
                <label for="fecha-estadistica-curso" class="form-label">Fecha para el cálculo</label>
                <input id="fecha-estadistica-curso"
                       type="date"
                       wire:model.live="fecha"
                       class="form-input tabular-nums" />
            </div>
            <div>
                <label for="nivel-estadistica-curso" class="form-label">Nivel</label>
                <select id="nivel-estadistica-curso"
                        wire:model.live="idNivel"
                        class="form-input">
                    <option value="0">Todos</option>
                    @foreach ($niveles as $nivel)
                        <option value="{{ (int) $nivel['id'] }}">{{ $nivel['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="border-t border-accent-200 px-4 py-4 sm:px-5">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Notas para interpretar esta estadística</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-neutral-700">
                <li>Los importes de cuotas y montos adeudados son históricos (no incluyen intereses).</li>
                <li>Las becas (en sus distintos porcentajes) están contempladas en la estadística.</li>
                <li>El importe esperado tiene ya descontada la bonificación por pago en término.</li>
            </ul>
        </div>

        @if ($pdfUrl !== '#')
            <div class="border-t border-accent-200 bg-accent-50/60 px-4 py-4 sm:px-5">
                <p class="mb-3 text-sm text-neutral-600">
                    Se generará el PDF con fecha de cálculo {{ $fechaTexto }}.
                </p>
                <a href="{{ $pdfUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir estadística (PDF)
                </a>
            </div>
        @else
            <div class="border-t border-accent-200 px-4 py-6 sm:px-5">
                <p class="text-center text-sm text-neutral-600 sm:text-left">
                    Indique una fecha de cálculo válida.
                </p>
            </div>
        @endif
    </div>
</div>
