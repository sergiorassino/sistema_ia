{{-- Constancia de alumno/a regular — elección de modelo, listado y modal de emisión. --}}
<div class="se-cierre-anual-fill se-matriz-list-fill">
    <div @class([
             'se-cierre-anual-grid',
             'se-cierre-anual-grid--matriz-listado' => $tipoElegido,
         ])>
        <section class="se-hero se-matriz-list-hero min-w-0 shrink-0">
            <div class="se-hero-inner flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-0.5">
                    <p class="se-eyebrow !text-[10px]">Certificados</p>
                    <h2 class="font-bold tracking-tight">Constancia de Alumno Regular</h2>
                    <p class="text-xs text-white/80 truncate">
                        @if ($tipoElegido)
                            {{ $etiquetaTipo }} · {{ schoolCtx()->nivelNombre() }} · Ciclo {{ $anoLectivo }}
                        @else
                            {{ schoolCtx()->nivelNombre() }} · Ciclo {{ $anoLectivo }} · Elija el tipo de certificado
                        @endif
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                    @if ($tipoElegido)
                        <button type="button"
                                wire:click="cambiarTipo"
                                class="inline-flex items-center justify-center gap-1 rounded-lg border border-white/25 bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-white/20">
                            Cambiar tipo
                        </button>
                    @endif
                    <a href="{{ route('dashboard') }}"
                       class="inline-flex items-center justify-center gap-1 rounded-lg border border-white/25 bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-white/20">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Panel
                    </a>
                </div>
            </div>
        </section>

        @if (! $tipoElegido)
            <div class="se-card flex min-h-0 flex-col justify-center p-5 sm:p-8">
                <p class="mb-4 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                    Tipo de certificado
                </p>
                <div class="mx-auto grid w-full max-w-3xl grid-cols-1 gap-4 sm:grid-cols-2">
                    <button type="button"
                            wire:click="elegirTipo('{{ \App\Support\Certificados\CertificadoAlumnoRegular::TIPO_LABORAL }}')"
                            class="se-dash-access group w-full cursor-pointer text-left">
                        <div class="se-dash-access-icon">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-neutral-900 group-hover:text-primary-700">
                                Certificado de Alumno Regular (LABORAL)
                            </p>
                            <p class="mt-1 text-sm leading-snug text-neutral-600">
                                Inicio o fin de año, presentado por y ante.
                            </p>
                        </div>
                    </button>
                    <button type="button"
                            wire:click="elegirTipo('{{ \App\Support\Certificados\CertificadoAlumnoRegular::TIPO_ESCOLAR }}')"
                            class="se-dash-access group w-full cursor-pointer text-left">
                        <div class="se-dash-access-icon">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-neutral-900 group-hover:text-primary-700">
                                Certificado de Alumno Regular (ESCOLAR)
                            </p>
                            <p class="mt-1 text-sm leading-snug text-neutral-600">
                                Alumno/a regular del ciclo en curso, para presentar ante un organismo.
                            </p>
                        </div>
                    </button>
                </div>
            </div>
        @else
            <div class="se-matriz-list-toolbar se-matriz-list-toolbar--angosta">
                <div class="relative min-w-0 flex-1 sm:max-w-sm">
                    <svg class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input id="buscar-cert-alu-reg"
                           type="search"
                           wire:model.live.debounce.400ms="buscar"
                           placeholder="Apellido, nombre o DNI…"
                           class="form-input w-full !py-1.5 !pl-8 text-sm"
                           autocomplete="off"
                           aria-label="Buscar alumno">
                </div>
                <p class="shrink-0 text-[11px] font-medium tabular-nums text-neutral-500">
                    {{ $alumnos->total() }} alumnos
                </p>
            </div>

            <div class="se-matriz-list-card min-h-0">
                <div class="se-cierre-anual-grilla se-matriz-list-grilla se-matriz-list-grilla--unified">
                    <div class="se-cierre-anual-body-wrap se-matriz-list-scroll se-grid-angosta-wrap" tabindex="0" data-se-cierre-body>
                        <table class="se-matriz-list-tabla se-matriz-list-tabla--cert-alu-reg table-fixed">
                            <colgroup>
                                <col style="width:8.5rem">
                                <col style="width:8.5rem">
                                <col style="width:5.25rem">
                                <col style="width:11rem">
                                <col style="width:8.75rem">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col" class="text-left">Apellido</th>
                                    <th scope="col" class="text-left">Nombre</th>
                                    <th scope="col" class="text-left">DNI</th>
                                    <th scope="col" class="text-left">Curso</th>
                                    <th scope="col" class="text-left whitespace-nowrap">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($alumnos as $a)
                                    <tr wire:key="cert-alu-reg-{{ $a['idLegajos'] }}">
                                        <td class="font-medium text-neutral-800">
                                            <span class="se-matriz-list-cell-truncate" title="{{ $a['apellido'] }}">{{ $a['apellido'] }}</span>
                                        </td>
                                        <td class="text-neutral-800">
                                            <span class="se-matriz-list-cell-truncate" title="{{ $a['nombre'] }}">{{ $a['nombre'] }}</span>
                                        </td>
                                        <td class="tabular-nums text-neutral-700 whitespace-nowrap">{{ $a['dni'] !== '' ? $a['dni'] : '—' }}</td>
                                        <td class="text-neutral-700">
                                            <span class="se-matriz-list-cell-truncate" title="{{ $a['curso'] }}">{{ $a['curso'] !== '' ? $a['curso'] : '—' }}</span>
                                        </td>
                                        <td class="!px-1.5 whitespace-nowrap">
                                            <button type="button"
                                                    wire:click="abrirModal({{ $a['idLegajos'] }})"
                                                    title="Emitir certificado"
                                                    class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-2 py-1 text-[10px] font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1">
                                                Emitir cert.
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="!h-auto py-8 text-center text-xs text-neutral-500">
                                            No hay alumnos matriculados en el ciclo lectivo activo que coincidan con la búsqueda.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($alumnos->hasPages())
                    <div class="se-matriz-list-footer">
                        {{ $alumnos->links('vendor.pagination.se-compact') }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    @teleport('body')
        <div>
        @if ($modalAbierto)
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="modal-cert-alu-reg-titulo"
             wire:key="modal-cert-alu-reg-{{ $tipoModelo }}-{{ $idLegajosModal }}">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm"
                 wire:click="cerrarModal"
                 aria-hidden="true"></div>

            <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl border border-accent-200 bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),40rem)]">
                <header class="shrink-0 border-b border-accent-200 px-5 py-4">
                    <h3 id="modal-cert-alu-reg-titulo" class="text-base font-bold text-neutral-800">
                        {{ $etiquetaTipo !== '' ? $etiquetaTipo : 'Constancia de Alumno Regular' }}
                    </h3>
                    <p class="mt-0.5 text-xs text-neutral-500 truncate" title="{{ $alumnoModalEtiqueta }}">
                        {{ $alumnoModalEtiqueta }}
                    </p>
                </header>

                <form class="flex min-h-0 flex-1 flex-col overflow-hidden" wire:submit.prevent="emitirPdf">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                        @if (! $esEscolar)
                            <fieldset class="space-y-2">
                                <legend class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                                    Tipo de certificado
                                </legend>
                                <div class="flex flex-wrap gap-4">
                                    <label class="inline-flex items-center gap-2 text-sm text-neutral-700 cursor-pointer">
                                        <input type="radio"
                                               wire:model="iniFin"
                                               value="{{ \App\Support\Certificados\CertificadoAlumnoRegular::INI_FIN_INICIO }}"
                                               class="text-primary-600 focus:ring-primary-500">
                                        Inicio de año escolar
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-neutral-700 cursor-pointer">
                                        <input type="radio"
                                               wire:model="iniFin"
                                               value="{{ \App\Support\Certificados\CertificadoAlumnoRegular::INI_FIN_FIN }}"
                                               class="text-primary-600 focus:ring-primary-500">
                                        Fin de año escolar
                                    </label>
                                </div>
                                @error('iniFin')
                                    <p class="text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </fieldset>
                        @endif

                        <div class="grid gap-4 {{ $esEscolar ? '' : 'sm:grid-cols-2' }}">
                            @if (! $esEscolar)
                                <div>
                                    <label for="fechIniFin" class="form-label">Fecha de inicio o fin</label>
                                    <input id="fechIniFin" type="date" wire:model="fechIniFin" class="form-input w-full">
                                    @error('fechIniFin')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                            <div>
                                <label for="fechaEmision" class="form-label">Fecha de emisión</label>
                                <input id="fechaEmision" type="date" wire:model="fechaEmision" class="form-input w-full">
                                @error('fechaEmision')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        @if (! $esEscolar)
                            <div>
                                <label for="prePor" class="form-label">Presentado por (Sr/Sra)</label>
                                <input id="prePor" type="text" wire:model="prePor" class="form-input w-full" maxlength="300" autocomplete="off">
                                @error('prePor')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="prePorDni" class="form-label">D.N.I. de quien presenta</label>
                                <input id="prePorDni" type="text" wire:model="prePorDni" class="form-input w-full" maxlength="10" inputmode="numeric" autocomplete="off">
                                @error('prePorDni')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        <div>
                            <label for="preAnte" class="form-label">Presentado ante</label>
                            <input id="preAnte" type="text" wire:model="preAnte" class="form-input w-full" maxlength="300" autocomplete="off">
                            @error('preAnte')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm text-neutral-600 cursor-pointer">
                            <input type="checkbox" wire:model="guardarAlEmitir" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                            Guardar datos en el legajo al generar el PDF
                        </label>

                        @error('guardar')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <footer class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-accent-100 bg-accent-50 px-5 py-3">
                        <button type="button"
                                wire:click="cerrarModal"
                                class="rounded-xl border border-accent-200 bg-white px-3 py-2 text-sm font-semibold text-primary-700 transition hover:bg-accent-50">
                            Cancelar
                        </button>
                        <button type="button"
                                wire:click="guardarDatos"
                                wire:loading.attr="disabled"
                                class="rounded-xl border border-accent-200 bg-white px-3 py-2 text-sm font-semibold text-neutral-700 transition hover:bg-accent-50 disabled:opacity-60">
                            <span wire:loading.remove wire:target="guardarDatos">Solo guardar</span>
                            <span wire:loading wire:target="guardarDatos">Guardando…</span>
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="rounded-xl bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="emitirPdf">Generar PDF</span>
                            <span wire:loading wire:target="emitirPdf">Generando…</span>
                        </button>
                    </footer>
                </form>
            </div>
        </div>
        @endif
        </div>
    @endteleport

    @script
    <script>
        $wire.on('se-swal-exito', (e) => window.seSwalExito(e.mensaje));
        $wire.on('se-swal-error', (e) => window.seSwalError(e.mensaje));
    </script>
    @endscript
</div>
