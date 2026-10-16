{{-- Lista de documentación del estudiante (autogestión y solapa del legajo). --}}
@if (($avisoCargaDocumentacion ?? '') !== '')
    <p class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
        {{ $avisoCargaDocumentacion }}
    </p>
@endif
<p class="mb-4 text-xs leading-relaxed text-neutral-600">
    Suba fotos JPG o un PDF. Cada foto se guarda como JPG comprimido, sin convertirla a PDF.
</p>

<div class="space-y-5">
    @foreach ($tiposDocumentoEstudiante as $tipo)
        @php
            $clave = $tipo['clave'];
            $estado = $estadoDocumentos[$clave] ?? ['existe' => false, 'actualizado_en' => null, 'nombre' => ''];
            $maxArchivos = (int) $tipo['max_archivos'];
            $cargaBloqueada = (bool) ($bloqueado ?? false);
        @endphp
        <div class="rounded-xl border border-accent-200 bg-accent-50/40 p-4"
             id="campo-archivosDocumento-{{ $clave }}">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-neutral-900">
                        {{ $tipo['label'] }}
                        @if ($tipo['obligatorio'])
                            <span class="text-red-600">*</span>
                        @endif
                    </p>
                    <p class="mt-1 text-[11px] text-neutral-500">
                        {{ $maxArchivos === 1 ? '1 archivo' : $maxArchivos.' archivos' }} —
                        {{ strtoupper(implode(', ', $tipo['extensiones'])) }}
                        · máx. {{ $tipo['max_mb'] }} MB c/u
                    </p>
                    @if (! empty($tipo['explicacion']))
                        <p class="mt-2 flex items-start gap-2 text-sm leading-relaxed text-neutral-600">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13 16h-1v-4h-1m1-4h.01M12 20a8 8 0 100-16 8 8 0 000 16z"/>
                            </svg>
                            <span>{{ $tipo['explicacion'] }}</span>
                        </p>
                    @endif
                    @if ($estado['existe'])
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <p class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Documento recibido
                                @if ($estado['actualizado_en'])
                                    · {{ $estado['actualizado_en'] }}
                                @endif
                            </p>
                            @foreach ($estado['archivos'] ?? [] as $archivoDoc)
                                @if (! empty($archivoDoc['url_ver']))
                                    @php
                                        $cantidadArchivosDoc = count($estado['archivos'] ?? []);
                                        $esPdfDoc = ($archivoDoc['extension'] ?? '') === 'pdf';
                                        $etiquetaVerDoc = $cantidadArchivosDoc > 1
                                            ? 'Ver archivo '.$archivoDoc['indice']
                                            : ($esPdfDoc ? 'Ver PDF subido' : 'Ver foto subida');
                                    @endphp
                                    <button type="button"
                                            wire:click="abrirVistaDocumento(@js($clave), {{ (int) ($archivoDoc['indice'] ?? 1) }})"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-primary-200 bg-white px-2.5 py-1 text-xs font-semibold text-primary-700 shadow-sm hover:bg-primary-50 disabled:opacity-60">
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <span wire:loading.remove wire:target="abrirVistaDocumento">{{ $etiquetaVerDoc }}</span>
                                        <span wire:loading wire:target="abrirVistaDocumento">Abriendo…</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if (! $estado['existe'])
                <div class="mt-3"
                     wire:key="doc-slots-{{ $clave }}-{{ $revisionInputsDocumento[$clave] ?? 0 }}">
                    @php
                        $inputId = 'doc-estudiante-'.$clave.'-'.($revisionInputsDocumento[$clave] ?? 0);
                    @endphp
                    <label class="form-label" for="{{ $inputId }}">Archivo</label>
                    <input id="{{ $inputId }}"
                           type="file"
                           wire:model="archivosDocumento.{{ $clave }}"
                           @if ($maxArchivos > 1) multiple @endif
                           accept="{{ \App\Support\Alumnos\DocumentosEstudianteAutogestion::acceptAttribute($tipo['extensiones']) }}"
                           @disabled($cargaBloqueada)
                           class="mt-1 block w-full text-sm text-neutral-700 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-700">
                    <div wire:loading wire:target="archivosDocumento.{{ $clave }}" class="mt-1 text-xs text-neutral-500">
                        Subiendo archivo…
                    </div>
                </div>
            @endif

            @if ($estado['existe'])
                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <button type="button"
                            x-on:click="window.seSwalConfirmar(@js('Se eliminará la documentación subida de este documento. Si es obligatoria, deberá volver a subirla.'), @js('Eliminar documento'), { confirmButtonText: 'Sí, eliminar' }).then(ok => ok && $wire.eliminarDocumento(@js($clave)))"
                            wire:loading.attr="disabled"
                            wire:target="eliminarDocumento"
                            @disabled($cargaBloqueada)
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 shadow-sm hover:bg-red-50 disabled:opacity-50">
                        <span wire:loading.remove wire:target="eliminarDocumento">Eliminar documento</span>
                        <span wire:loading wire:target="eliminarDocumento">Eliminando…</span>
                    </button>
                </div>
            @endif
            @error('archivosDocumento.'.$clave)
                <p class="form-error mt-2">{{ $message }}</p>
            @enderror
        </div>
    @endforeach
</div>
