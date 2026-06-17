<div class="se-page max-w-5xl mx-auto"
     x-data
     x-on:mora-gestion-morosos-abrir-pdf.window="window.open($event.detail.url, '_blank')">
    <section class="se-hero mb-4">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Administración · Gestión de mora</p>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Gestión de Morosos</h1>
                <p class="text-sm text-white/80 max-w-2xl">
                    Filtre el listado de deuda y genere el PDF. Ciclo de contexto {{ $anoContexto }}.
                </p>
            </div>
        </div>
    </section>

    {{-- Barra de acciones --}}
    <div class="se-toolbar se-toolbar-pocos-campos mb-4 flex-wrap gap-2">
        @if ($puedeGenerarPdf)
            <button type="button"
                    wire:click="abrirPdfListado"
                    wire:loading.attr="disabled"
                    wire:target="abrirPdfListado"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-60">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                <span wire:loading.remove wire:target="abrirPdfListado">Listado de Deuda</span>
                <span wire:loading wire:target="abrirPdfListado">Generando…</span>
            </button>
        @else
            <button type="button"
                    disabled
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-neutral-300 px-4 py-2.5 text-sm font-semibold text-neutral-600 cursor-not-allowed"
                    title="Revise los filtros activos">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Listado de Deuda
            </button>
        @endif

        @if ($puedeGenerarPdf)
            <button type="button"
                    wire:click="abrirPdfNotificacion"
                    wire:loading.attr="disabled"
                    wire:target="abrirPdfNotificacion"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:border-primary-500 hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-60">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span wire:loading.remove wire:target="abrirPdfNotificacion">Imprimir Notificación de Deuda</span>
                <span wire:loading wire:target="abrirPdfNotificacion">Generando…</span>
            </button>
        @else
            <button type="button"
                    disabled
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-neutral-500 cursor-not-allowed"
                    title="Revise los filtros activos">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Imprimir Notificación de Deuda
            </button>
        @endif

        <a href="{{ route('mora.gestion-morosos.textos-notificacion') }}"
           wire:navigate
           class="inline-flex items-center justify-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:border-primary-500 hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Editar Textos en la Notificación
        </a>
    </div>

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
            <p class="text-sm text-neutral-700">
                Active cada filtro con su casilla. La <strong>fecha de cálculo</strong> define intereses y total a pagar en el PDF.
                Si no activa ningún filtro opcional, el listado y la notificación incluyen <strong>todas las familias</strong> con cuotas adeudadas (saldo mayor a cero).
            </p>
        </div>

        <div class="grid gap-4 px-4 py-4 sm:grid-cols-2 sm:px-5">
            <div class="sm:col-span-2 flex flex-col items-center border-b border-accent-200 pb-5 mb-1">
                <label for="fecha-calculo-morosos" class="form-label text-center">Fecha de cálculo</label>
                <input id="fecha-calculo-morosos"
                       type="date"
                       wire:model.live="fechaCalculo"
                       class="form-input w-full max-w-xs tabular-nums text-center" />
            </div>

            <div class="sm:col-span-2">
                <label class="inline-flex items-center gap-2 mb-1">
                    <input type="checkbox" wire:model.live="chkFamilia" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                    <span class="form-label mb-0">Familia</span>
                </label>
                <select wire:model.live="idFamilia"
                        class="form-input"
                        @disabled(! $chkFamilia)>
                    <option value="0">— Seleccione —</option>
                    @foreach ($familias as $familia)
                        @php
                            $etiq = trim((string) ($familia->apellido ?? ''));
                            $resp = trim((string) ($familia->responsable ?? ''));
                            $texto = $etiq.($etiq !== '' && $resp !== '' ? ' — '.$resp : ($resp !== '' ? $resp : ''));
                        @endphp
                        <option value="{{ (int) $familia->id }}">{{ $texto !== '' ? $texto : 'Familia #'.$familia->id }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="inline-flex items-center gap-2 mb-1">
                    <input type="checkbox" wire:model.live="chkAlumno" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                    <span class="form-label mb-0">Estudiante</span>
                </label>
                <select wire:model.live="idAlumno"
                        class="form-input"
                        @disabled(! $chkAlumno)>
                    <option value="0">— Seleccione —</option>
                    @foreach ($alumnos as $alumno)
                        <option value="{{ (int) $alumno->id }}">
                            {{ mb_strtoupper(trim((string) ($alumno->apellido ?? '').' '.(string) ($alumno->nombre ?? ''))) }}
                            @if ((string) ($alumno->dni ?? '') !== '')
                                · DNI {{ $alumno->dni }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="inline-flex items-center gap-2 mb-1">
                    <input type="checkbox" wire:model.live="chkVencDesde" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                    <span class="form-label mb-0">1º venc. desde</span>
                </label>
                <input type="date"
                       wire:model.live="vencDesde"
                       class="form-input tabular-nums"
                       @disabled(! $chkVencDesde) />
            </div>
            <div>
                <label class="inline-flex items-center gap-2 mb-1">
                    <input type="checkbox" wire:model.live="chkVencHasta" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                    <span class="form-label mb-0">1º venc. hasta</span>
                </label>
                <input type="date"
                       wire:model.live="vencHasta"
                       class="form-input tabular-nums"
                       @disabled(! $chkVencHasta) />
            </div>

            <div class="sm:col-span-2">
                <label class="inline-flex items-center gap-2 mb-1">
                    <input type="checkbox" wire:model.live="chkExcluir" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                    <span class="form-label mb-0">Excluir cuotas (plantilla)</span>
                </label>
                <select wire:model.live="idsExcluirCuotas"
                        multiple
                        size="4"
                        class="form-input min-h-[6rem]"
                        @disabled(! $chkExcluir)>
                    @foreach ($cuotas as $cuota)
                        @php $anoCuota = (int) ($cuota->terlec_ano ?? 0); @endphp
                        <option value="{{ (int) $cuota->id }}">
                            {{ $anoCuota > 0 ? $anoCuota.' — ' : '' }}{{ $cuota->nombre }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-neutral-500">Mantenga Ctrl (o Cmd) para elegir varias.</p>
            </div>

            <div class="sm:col-span-2">
                <label class="inline-flex items-center gap-2 mb-1">
                    <input type="checkbox" wire:model.live="chkCurso" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                    <span class="form-label mb-0">Cursos (ciclo activo)</span>
                </label>
                <select wire:model.live="idsCursos"
                        multiple
                        size="4"
                        class="form-input min-h-[6rem]"
                        @disabled(! $chkCurso)>
                    @foreach ($cursos as $curso)
                        <option value="{{ (int) $curso->Id }}">{{ $etiquetaCurso($curso) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="inline-flex items-center gap-2 mb-1">
                    <input type="checkbox" wire:model.live="chkMasDe" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                    <span class="form-label mb-0">Más de X cuotas adeudadas</span>
                </label>
                <input type="number"
                       min="0"
                       step="1"
                       wire:model.live="masDe"
                       class="form-input max-w-[8rem] tabular-nums"
                       @disabled(! $chkMasDe) />
            </div>
            <div>
                <label class="inline-flex items-center gap-2 mb-1">
                    <input type="checkbox" wire:model.live="chkHasta" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                    <span class="form-label mb-0">Hasta X cuotas adeudadas</span>
                </label>
                <input type="number"
                       min="0"
                       step="1"
                       wire:model.live="hasta"
                       class="form-input max-w-[8rem] tabular-nums"
                       @disabled(! $chkHasta) />
            </div>

            <div class="flex flex-col gap-2">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model.live="chkSoloFuera" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                    <span class="text-sm text-neutral-800">Solo fuera de colegio (sin matrícula {{ $anoContexto }})</span>
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model.live="chkExceptoFuera" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                    <span class="text-sm text-neutral-800">Excepto fuera de colegio (con matrícula {{ $anoContexto }})</span>
                </label>
            </div>

            <div>
                <label class="inline-flex items-center gap-2 mb-1">
                    <input type="checkbox" wire:model.live="chkAno" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                    <span class="form-label mb-0">Año lectivo de la cuota</span>
                </label>
                <select wire:model.live="idTerlec"
                        class="form-input"
                        @disabled(! $chkAno)>
                    <option value="0">—</option>
                    @foreach ($terlecs as $terlec)
                        <option value="{{ (int) $terlec->id }}">{{ (int) $terlec->ano }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="inline-flex items-center gap-2 mb-1">
                    <input type="checkbox" wire:model.live="chkSoloBecados" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                    <span class="form-label mb-0">Solo becados (tipo de beca)</span>
                </label>
                <select wire:model.live="idsBecas"
                        multiple
                        size="3"
                        class="form-input min-h-[5rem]"
                        @disabled(! $chkSoloBecados)>
                    @foreach ($becas as $beca)
                        <option value="{{ (int) $beca->id }}">{{ $beca->nombreBeca }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if (! $puedeGenerarPdf)
            <div class="border-t border-accent-200 px-4 py-4 sm:px-5">
                <p class="text-sm text-neutral-600">
                    Revise los filtros activos: debe completar los campos de cada casilla marcada y las fechas «hasta» ≥ «desde».
                </p>
            </div>
        @endif
    </div>
</div>
