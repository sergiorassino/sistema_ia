@php
    use App\Support\Cuotas\GeneracionMasivaCuotasConsulta;
@endphp

<div class="se-page max-w-[90rem] mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Medios de pago · SIRO</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Planilla Nº {{ number_format($planilla->nroPlanilla, 0, ',', '.') }}</h1>
                <dl class="grid gap-1 text-xs text-white/85 sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="inline font-semibold">Fecha de Carga:</dt> {{ $planilla->fecha?->format('d/m/Y') ?? '—' }}</div>
                    <div><dt class="inline font-semibold">Canal de Pago:</dt> {{ $etiquetaCanal }}</div>
                    <div class="sm:col-span-2"><dt class="inline font-semibold">Archivo de Origen:</dt> {{ $planilla->nombreArchivo !== '' ? $planilla->nombreArchivo : '—' }}</div>
                </dl>
            </div>
            <a href="{{ route('cuotas.siro-descarga') }}"
               wire:navigate
               class="inline-flex shrink-0 items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
                Volver
            </a>
        </div>
    </section>

    <section class="se-card mb-4 overflow-hidden">
        @if (\App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionMatchUploadCercano::HABILITADO)
            <div class="border-b border-amber-200 bg-amber-50/90 px-4 py-3 sm:px-5" role="status">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-900">
                    Excepción provisorio — puesta en marcha
                </p>
                <p class="mt-1 text-sm leading-relaxed text-amber-950/90">
                    {{ \App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionMatchUploadCercano::mensajeAvisoFormulario() }}
                </p>
            </div>
        @endif
        <div class="se-toolbar flex flex-col gap-3 border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-end sm:px-5">
            <div class="min-w-0 flex-1 sm:max-w-md">
                <label class="form-label">Añadir Archivo</label>
                <input type="file"
                       wire:model="archivoRendicion"
                       accept=".txt,text/plain"
                       class="block w-full text-sm text-neutral-700 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-700">
                @error('archivoRendicion') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <button type="button"
                    wire:click="procesarArchivo"
                    wire:loading.attr="disabled"
                    wire:target="procesarArchivo,archivoRendicion"
                    @disabled((int) ($planilla->impactado ?? 0) === 1)
                    class="rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 hover:border-primary-500 disabled:opacity-50">
                Procesar archivo
            </button>
            <button type="button"
                    x-on:click="window.seSwalConfirmar('¿Impactar todos los pagos en las cuotas de los alumnos?', 'Impactar pagos').then(ok => ok && $wire.impactarTodos())"
                    wire:loading.attr="disabled"
                    wire:target="impactarTodos"
                    @disabled($rendiciones->isEmpty())
                    class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                Impactar los pagos en las cuotas de los alumnos
            </button>
            <button type="button"
                    x-on:click="window.seSwalConfirmar('¿Eliminar todos los pagos descargados de esta planilla?', 'Borrar todos', { icon: 'warning' }).then(ok => ok && $wire.borrarTodos())"
                    wire:loading.attr="disabled"
                    wire:target="borrarTodos"
                    @disabled($rendiciones->isEmpty() || (int) ($planilla->impactado ?? 0) === 1)
                    class="rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50">
                Borrar todos
            </button>
        </div>

        @if ($rendiciones->isEmpty())
            <div class="py-14 text-center text-sm text-neutral-600">
                No hay pagos descargados. Cargue el archivo de rendición SIRO.
            </div>
        @else
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <div @class([
                        'gf gf-vcenter gf-siro-descarga-rendiciones',
                        'has-obs-data' => $hayObsEnPlanilla,
                    ])>
                        <div class="gf-head">
                            <div class="gf-th gf-th-item">#</div>
                            <div class="gf-th gf-th-fecha-pago" title="Fecha de pago">F. pago</div>
                            <div class="gf-th gf-th-canal-pago" title="Canal de pago">Canal</div>
                            <div class="gf-th gf-th-estudiante" title="Estudiante">Estud.</div>
                            <div class="gf-th gf-th-curso" title="Sala / grado / curso">Curso</div>
                            <div class="gf-th gf-th-cuota" title="Cuota">Cuota</div>
                            <div class="gf-th gf-th-planilla" title="Nº planilla">Plan.</div>
                            <div class="gf-th gf-th-venc1" title="1º vencimiento">1º vto</div>
                            <div class="gf-th gf-th-beca" title="Porcentaje de beca">Beca</div>
                            <div class="gf-th gf-th-importe" title="Importe">Importe</div>
                            <div class="gf-th gf-th-interes" title="Interés">Int.</div>
                            <div class="gf-th gf-th-bonific" title="Bonificación">Bonif.</div>
                            <div class="gf-th gf-th-pagado" title="Pagado">Pagado</div>
                            <div class="gf-th gf-th-nombre-archivo" title="Nombre del archivo">Archivo</div>
                            <div class="gf-th gf-th-impactado" title="Impactado">Impto.</div>
                            @if ($hayObsEnPlanilla)
                                <div class="gf-th gf-th-obs" title="Observaciones">Obs.</div>
                            @endif
                        </div>
                        @foreach ($rendiciones as $i => $r)
                            @php
                                $legajo = $r->legajo;
                                $nombreEst = $legajo
                                    ? mb_strtoupper(trim((string) $legajo->apellido.' '.(string) $legajo->nombre))
                                    : '—';
                                $cursoEtiqueta = $r->curso
                                    ? GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($r->curso)
                                    : '—';
                                $cuotaNombre = trim((string) ($r->cuota?->nombre ?? ''));
                                $becaPct = $r->beca?->porcentaje;
                            @endphp
                            <div class="gf-row gf-row-hover" wire:key="rend-{{ $r->id }}">
                                <div class="gf-td gf-td-item tabular-nums">{{ $i + 1 }}</div>
                                <div class="gf-td gf-td-fecha-pago tabular-nums">{{ $r->fechaPago?->format('d/m/Y') ?? '—' }}</div>
                                <div class="gf-td gf-td-canal-pago">{{ trim((string) ($r->tipoPago?->abrev ?? '')) }}</div>
                                <div class="gf-td gf-td-estudiante" title="{{ $nombreEst }}">{{ $nombreEst }}</div>
                                <div class="gf-td gf-td-curso" title="{{ $cursoEtiqueta }}">{{ $cursoEtiqueta }}</div>
                                <div class="gf-td gf-td-cuota" title="{{ $cuotaNombre }}">{{ $cuotaNombre }}</div>
                                <div class="gf-td gf-td-planilla tabular-nums">{{ number_format((int) $r->nroPlanilla, 0, ',', '.') }}</div>
                                <div class="gf-td gf-td-venc1 tabular-nums">{{ $r->fechVenc1?->format('d/m/Y') ?? '—' }}</div>
                                <div class="gf-td gf-td-beca tabular-nums">{{ $becaPct !== null ? rtrim(rtrim(number_format((float) $becaPct, 2, ',', '.'), '0'), ',') : '' }}</div>
                                <div class="gf-td gf-td-importe tabular-nums">{{ $fmtImporte((float) $r->importe) }}</div>
                                <div class="gf-td gf-td-interes tabular-nums">{{ $fmtImporte((float) $r->interes) }}</div>
                                <div class="gf-td gf-td-bonific tabular-nums">{{ $fmtImporte((float) $r->bonificacion) }}</div>
                                <div class="gf-td gf-td-pagado tabular-nums font-semibold">{{ $fmtImporte((float) $r->pagado) }}</div>
                                <div class="gf-td gf-td-nombre-archivo" title="{{ $r->nombreArchivo }}">{{ $r->nombreArchivo }}</div>
                                <div class="gf-td gf-td-impactado">
                                    @if ((int) ($r->impactado ?? 0) === 1)
                                        <span class="font-semibold text-primary-700">Sí</span>
                                    @else
                                        No
                                    @endif
                                </div>
                                @if ($hayObsEnPlanilla)
                                    @php $obsTexto = trim((string) ($r->obs ?? '')); @endphp
                                    <div @class([
                                        'gf-td gf-td-obs',
                                        'text-amber-800' => $obsTexto !== '',
                                    ]) title="{{ $obsTexto }}">{{ $obsTexto }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="border-t border-accent-200 bg-accent-50/50 px-4 py-3 text-right text-sm font-semibold text-neutral-800 sm:px-5">
                Total Cobrado: {{ $fmtImporte($totalCobrado) }}
            </div>
        @endif
    </section>

    @if ($modalResumenAbierto)
        @teleport('body')
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog" aria-modal="true" aria-labelledby="siro-descarga-resumen-titulo"
                 wire:key="siro-descarga-modal-resumen">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalResumen" aria-hidden="true"></div>
                <div class="relative z-10 my-auto flex w-full max-w-4xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),44rem)]"
                     @click.stop>
                    <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                        <h2 id="siro-descarga-resumen-titulo" class="text-lg font-bold text-neutral-800">{{ $modalResumenTitulo }}</h2>
                        <p class="mt-1 text-sm text-neutral-600">
                            Planilla Nº {{ number_format($planilla->nroPlanilla, 0, ',', '.') }}
                            @if ($planilla->nombreArchivo !== '')
                                · {{ $planilla->nombreArchivo }}
                            @endif
                        </p>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-4" id="siro-descarga-resumen-contenido">
                        @if ($modalResumenEncabezado !== [])
                            <ul class="space-y-1 text-sm text-neutral-700">
                                @foreach ($modalResumenEncabezado as $linea)
                                    <li>{{ $linea }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($modalRegistrosArchivo !== [])
                            <div>
                                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-600">
                                    Registros del archivo ({{ count($modalRegistrosArchivo) }})
                                </p>
                                <div class="w-full overflow-x-auto se-grid-angosta-wrap">
                                    <table class="se-matriz-list-tabla table-fixed text-sm">
                                        <thead>
                                            <tr class="bg-accent-50 text-[10px] font-semibold uppercase tracking-wide text-neutral-600">
                                                <th class="w-12 px-2 py-2 text-left">#</th>
                                                <th class="w-16 px-2 py-2 text-left">Canal</th>
                                                <th class="min-w-[12rem] px-2 py-2 text-left">id_factura buscado</th>
                                                <th class="min-w-[10rem] px-2 py-2 text-left">Modalidad</th>
                                                <th class="w-28 px-2 py-2 text-left">Resultado</th>
                                                <th class="min-w-[10rem] px-2 py-2 text-left">Detalle</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($modalRegistrosArchivo as $registro)
                                                @php
                                                    $estado = (string) ($registro['estado'] ?? '');
                                                    $estadoEtiqueta = match ($estado) {
                                                        'encontrado' => 'Encontrado',
                                                        'no_encontrado' => 'No encontrado',
                                                        'omitido' => 'Omitido',
                                                        'rechazo' => 'Rechazo',
                                                        default => ucfirst($estado),
                                                    };
                                                    $estadoClase = match ($estado) {
                                                        'encontrado' => 'text-primary-700 font-semibold',
                                                        'no_encontrado' => 'text-red-700 font-semibold',
                                                        'omitido' => 'text-amber-800 font-semibold',
                                                        'rechazo' => 'text-red-800 font-semibold',
                                                        default => 'text-neutral-700',
                                                    };
                                                @endphp
                                                <tr class="border-t border-accent-100 hover:bg-accent-50/60" wire:key="siro-resumen-reg-{{ $registro['linea'] ?? $loop->index }}">
                                                    <td class="px-2 py-2 tabular-nums">{{ $registro['linea'] ?? '—' }}</td>
                                                    <td class="px-2 py-2">{{ $registro['canal'] ?? '—' }}</td>
                                                    <td class="px-2 py-2 font-mono text-xs break-all">{{ $registro['idFacturaBuscado'] ?? '—' }}</td>
                                                    <td class="px-2 py-2 text-xs text-neutral-700">{{ $registro['modalidadIdentificacion'] ?? '—' }}</td>
                                                    <td class="px-2 py-2 {{ $estadoClase }}">{{ $estadoEtiqueta }}</td>
                                                    <td class="px-2 py-2 text-xs text-neutral-600">{{ $registro['detalle'] ?? '' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if ($modalResumenProblemas !== [])
                            <div>
                                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-amber-800">
                                    Errores y advertencias ({{ count($modalResumenProblemas) }})
                                </p>
                                <ol class="list-decimal space-y-2 border border-amber-200/80 bg-amber-50/60 px-4 py-3 pl-8 text-sm leading-relaxed text-neutral-800">
                                    @foreach ($modalResumenProblemas as $problema)
                                        <li>{{ $problema }}</li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                    </div>

                    <div class="shrink-0 flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/80 px-5 py-4">
                        <button type="button"
                                x-on:click="window.seSiroDescargaImprimirResumen(
                                    @js($modalResumenTitulo),
                                    @js(number_format((int) $planilla->nroPlanilla, 0, ',', '.')),
                                    @js($planilla->nombreArchivo !== '' ? $planilla->nombreArchivo : '—'),
                                    @js($modalResumenContexto),
                                    @js($modalResumenEncabezado),
                                    @js($modalResumenProblemas),
                                    @js($modalRegistrosArchivo)
                                )"
                                class="rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 hover:bg-accent-50">
                            Imprimir
                        </button>
                        <button type="button"
                                wire:click="cerrarModalResumen"
                                class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    @script
    <script>
        window.seSiroDescargaImprimirResumen = (titulo, nroPlanilla, nombreArchivo, contexto, encabezado, problemas, registros) => {
            const esc = (valor) => String(valor ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');

            const encabezadoHtml = (encabezado ?? []).map((linea) => `<li>${esc(linea)}</li>`).join('');
            const problemasHtml = (problemas ?? []).map((linea) => `<li>${esc(linea)}</li>`).join('');
            const registrosHtml = (registros ?? []).map((reg) => {
                const estado = reg.estado === 'encontrado' ? 'Encontrado'
                    : reg.estado === 'no_encontrado' ? 'No encontrado'
                    : reg.estado === 'omitido' ? 'Omitido'
                    : reg.estado === 'rechazo' ? 'Rechazo'
                    : esc(reg.estado);
                return `<tr>
                    <td>${esc(reg.linea)}</td>
                    <td>${esc(reg.canal)}</td>
                    <td style="font-family:monospace;font-size:10pt">${esc(reg.idFacturaBuscado)}</td>
                    <td>${esc(reg.modalidadIdentificacion ?? '—')}</td>
                    <td>${estado}</td>
                    <td>${esc(reg.detalle ?? '')}</td>
                </tr>`;
            }).join('');
            const operacion = contexto === 'impacto' ? 'Impacto de pagos' : 'Carga de archivo de rendición';
            const fecha = new Date().toLocaleString('es-AR');

            const html = `<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>${esc(titulo)}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12pt; color: #222; margin: 18mm; }
        h1 { font-size: 16pt; margin: 0 0 8px; }
        .meta { font-size: 10pt; color: #555; margin-bottom: 16px; }
        h2 { font-size: 12pt; margin: 16px 0 8px; }
        ol { margin: 0; padding-left: 22px; }
        li { margin-bottom: 6px; }
        ul { margin: 0; padding-left: 18px; }
        table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f4f8f9; font-size: 9pt; text-transform: uppercase; }
    </style>
</head>
<body>
    <h1>${esc(titulo)}</h1>
    <p class="meta">${esc(operacion)} · Planilla Nº ${esc(nroPlanilla)} · Archivo: ${esc(nombreArchivo)} · ${esc(fecha)}</p>
    ${encabezadoHtml ? `<h2>Resumen</h2><ul>${encabezadoHtml}</ul>` : ''}
    ${registrosHtml ? `<h2>Registros del archivo</h2><table><thead><tr><th>#</th><th>Canal</th><th>id_factura buscado</th><th>Modalidad</th><th>Resultado</th><th>Detalle</th></tr></thead><tbody>${registrosHtml}</tbody></table>` : ''}
    ${problemasHtml ? `<h2>Errores y advertencias</h2><ol>${problemasHtml}</ol>` : ''}
</body>
</html>`;

            const iframe = document.createElement('iframe');
            iframe.setAttribute('aria-hidden', 'true');
            iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
            document.body.appendChild(iframe);

            const doc = iframe.contentDocument ?? iframe.contentWindow?.document;
            if (!doc) {
                iframe.remove();
                window.seSwalError?.('No se pudo preparar la impresión.');
                return;
            }

            doc.open();
            doc.write(html);
            doc.close();

            const imprimir = () => {
                try {
                    iframe.contentWindow?.focus();
                    iframe.contentWindow?.print();
                } finally {
                    window.setTimeout(() => iframe.remove(), 1000);
                }
            };

            if (iframe.contentWindow?.document.readyState === 'complete') {
                imprimir();
            } else {
                iframe.onload = imprimir;
            }
        };

        $wire.on('se-swal-exito', ({ mensaje }) => window.seSwalExito(mensaje));
        $wire.on('se-swal-aviso', ({ mensaje }) => window.seSwalAviso(mensaje));
        $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
    </script>
    @endscript
</div>
