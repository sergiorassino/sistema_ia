<div>
<div class="se-page max-w-5xl mx-auto"
     x-data
     x-on:mora-gestion-morosos-abrir-pdf.window="window.open($event.detail.url, '_blank')">
    <section class="se-hero mb-4">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Administración · Gestión de mora</p>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Gestión de Morosos</h1>
                <p class="text-sm text-white/80 max-w-2xl">
                    Filtre el listado de deuda, genere el PDF o envíe la notificación por mail. Ciclo de contexto {{ $anoContexto }}.
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

        @if ($puedeGenerarPdf)
            <button type="button"
                    wire:click="abrirPreviewMailNotificacion"
                    wire:loading.attr="disabled"
                    wire:target="abrirPreviewMailNotificacion"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:border-primary-500 hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-60">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span wire:loading.remove wire:target="abrirPreviewMailNotificacion">Enviar notificación por mail</span>
                <span wire:loading wire:target="abrirPreviewMailNotificacion">Armando lista…</span>
            </button>
        @else
            <button type="button"
                    disabled
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-neutral-500 cursor-not-allowed"
                    title="Revise los filtros activos">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Enviar notificación por mail
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
                Si no activa ningún filtro opcional, el listado, la notificación y el envío por mail incluyen familias con cuotas adeudadas <strong>vencidas al 2.º vencimiento</strong> (según la fecha de cálculo), con saldo mayor a cero.
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

            <div>
                <label class="inline-flex items-center gap-2 mb-1">
                    <input type="checkbox" wire:model.live="chkNivel" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                    <span class="form-label mb-0">Nivel</span>
                </label>
                <select id="filtro-nivel-morosos"
                        wire:model.live="idNivel"
                        class="form-input"
                        @disabled(! $chkNivel)>
                    <option value="0">— Seleccione —</option>
                    @foreach ($niveles as $nivel)
                        <option value="{{ (int) $nivel->id }}">{{ $nivel->nivel }}</option>
                    @endforeach
                </select>
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

    @teleport('body')
        <div>
            @if ($modalMailAbierto)
                <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                     role="dialog" aria-modal="true" aria-labelledby="mora-mail-titulo">
                    <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalMail"></div>
                    <div class="relative z-10 my-auto flex w-full max-w-4xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),44rem)]">
                        <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                            <h3 id="mora-mail-titulo" class="text-lg font-semibold text-neutral-900">Enviar notificación por mail</h3>
                            <p class="mt-1 text-sm text-neutral-600">
                                Responsable administrativo (<span class="font-mono text-xs">familias.email</span>).
                                El texto es el mismo de la notificación (carta, deuda y cierre).
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="se-pill">{{ $destinatariosMailTotal }} destinatario(s)</span>
                                <span class="se-pill">{{ $destinatariosMailListos }} listos para enviar</span>
                                @if ($destinatariosMailIncompletos > 0)
                                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">
                                        {{ $destinatariosMailIncompletos }} con datos faltantes
                                    </span>
                                @endif
                            </div>
                            @if ($cuentasSmtpMail !== [])
                                <p class="mt-2 text-xs text-neutral-600">
                                    Remitente institucional:
                                    @foreach ($cuentasSmtpMail as $cta)
                                        <span class="font-semibold text-primary-700">{{ $cta['cuenta'] !== '' ? $cta['cuenta'] : '—' }}</span>
                                        @if ($cta['nivel'] !== '')
                                            <span class="text-neutral-500">({{ $cta['nivel'] }})</span>
                                        @endif
                                        @if (! $loop->last) · @endif
                                    @endforeach
                                </p>
                            @endif
                            @if ($avisosSmtpMail !== [])
                                <p class="mt-2 text-xs font-semibold text-amber-800">
                                    {{ implode(' · ', $avisosSmtpMail) }}. Cargá usuario y contraseña en Parametrización → Correo institucional Gmail.
                                </p>
                            @endif
                            @if ($mailLocalLog)
                                <p class="mt-2 text-xs text-sky-800">Entorno local: el correo no sale por SMTP (queda en el log).</p>
                            @endif
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <table class="se-matriz-list-tabla w-full">
                                <thead>
                                    <tr>
                                        <th class="w-10">#</th>
                                        <th>Apellido</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($destinatariosMail as $i => $dest)
                                        <tr wire:key="mora-mail-{{ $dest['clave'] }}"
                                            @class(['bg-amber-50/80' => $dest['incompleto']])>
                                            <td class="tabular-nums">{{ $i + 1 }}</td>
                                            <td class="text-sm">
                                                {{ $dest['apellido'] !== '' ? $dest['apellido'] : '—' }}
                                            </td>
                                            <td class="text-sm">
                                                {{ $dest['nombre'] !== '' ? $dest['nombre'] : '—' }}
                                            </td>
                                            <td class="font-mono text-xs break-all">
                                                {{ $dest['email'] !== '' ? $dest['email'] : '—' }}
                                            </td>
                                            <td class="text-xs">
                                                @if ($dest['puedeEnviar'] && ! $dest['incompleto'])
                                                    <span class="font-semibold text-primary-700">Listo</span>
                                                @elseif ($dest['puedeEnviar'])
                                                    <span class="font-semibold text-amber-800">Se envía · faltan datos</span>
                                                @else
                                                    <span class="font-semibold text-red-700">No se envía</span>
                                                @endif
                                                @if ($dest['sinFamilia'])
                                                    <span class="block text-neutral-500">Sin familia asignada</span>
                                                @endif
                                                @if ($dest['faltantes'] !== [])
                                                    <span class="block text-amber-800">Falta: {{ implode(', ', $dest['faltantes']) }}</span>
                                                @endif
                                                @if ($dest['avisoSmtp'] !== '')
                                                    <span class="block text-amber-800">{{ $dest['avisoSmtp'] }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-8 text-center text-sm text-neutral-500">No hay destinatarios.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-accent-100 bg-accent-50 px-5 py-4">
                            <button type="button"
                                    wire:click="cerrarModalMail"
                                    wire:loading.attr="disabled"
                                    wire:target="enviarMailNotificacion"
                                    class="rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 shadow-sm hover:bg-accent-50">
                                Cancelar
                            </button>
                            <button type="button"
                                    @disabled($destinatariosMailListos < 1)
                                    wire:loading.attr="disabled"
                                    wire:target="enviarMailNotificacion"
                                    x-data
                                    x-on:click="seSwalConfirmar('¿Enviar la notificación a {{ $destinatariosMailListos }} destinatario(s) con email válido? Quienes no tienen correo no reciben el mensaje.', 'Enviar notificación').then(ok => ok && $wire.enviarMailNotificacion())"
                                    class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50">
                                <span wire:loading.remove wire:target="enviarMailNotificacion">Enviar a {{ $destinatariosMailListos }} destinatario(s)</span>
                                <span wire:loading wire:target="enviarMailNotificacion">Enviando…</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <div wire:loading.flex
                 wire:target="enviarMailNotificacion"
                 class="fixed inset-0 z-[100] items-center justify-center bg-neutral-900/45 backdrop-blur-sm px-4">
                <div class="max-w-md rounded-2xl bg-white px-6 py-5 text-center shadow-xl ring-1 ring-black/5">
                    <div class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-2 border-primary-200 border-t-primary-600"></div>
                    <p class="text-sm font-semibold text-neutral-800">Enviando notificación de deuda</p>
                    <p class="mt-2 text-sm text-neutral-600">{{ $destinatariosMailListos }} destinatario(s). No cierre esta ventana.</p>
                </div>
            </div>
        </div>
    @endteleport

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje, titulo }) => window.seSwalExito(mensaje, titulo ?? 'Listo'));
        $wire.on('se-swal-aviso', ({ mensaje, titulo }) => window.seSwalAviso(mensaje, titulo ?? 'Atención'));
        $wire.on('se-swal-error', ({ mensaje, titulo }) => window.seSwalError(mensaje, titulo ?? 'Error'));
    </script>
    @endscript
</div>
