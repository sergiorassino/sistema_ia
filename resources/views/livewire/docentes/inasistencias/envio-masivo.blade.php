@php
    $esModoPrueba = ! empty($progreso['soloPrueba']);
    $bimestreProgreso = (int) ($progreso['bimestre'] ?? $bimestre);
    $anioProgreso = (int) ($progreso['anio'] ?? $anio);
    $pdfActual = $pdfsRevision[$revisionIndice] ?? null;
    $pdfActualUrl = $pdfActual
        ? route('docentes.inasistencias.informe.pdf', [
            'idProfesor' => $pdfActual['idProfesor'],
            'bimestre' => $bimestreProgreso,
            'anio' => $anioProgreso,
        ])
        : null;
@endphp

<div class="se-page {{ $revisionAbierta ? 'max-w-6xl' : 'max-w-xl' }}" @if($polling) wire:poll.500ms="actualizarProgreso" @endif>
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="se-eyebrow">Docentes</p>
                <h2 class="text-2xl font-bold tracking-tight">Envío masivo por correo</h2>
                <p class="text-sm text-white/80">Informe PDF del bimestre a cada docente con email</p>
            </div>
            <a href="{{ route('docentes.inasistencias') }}" class="btn-secondary !border-white/30 !bg-white/10 !text-white shrink-0">← Listado</a>
        </div>
    </section>

    <div class="se-card p-6 space-y-5">
        <p class="text-sm text-neutral-600">
            Se generará el informe PDF del bimestre elegido y se enviará por correo a cada docente del listado (con o sin inasistencias).
            Los docentes sin email registrado se omiten.
        </p>

        <form wire:submit="enviar" class="space-y-4">
            <div>
                <label for="bimestre" class="form-label">Bimestre</label>
                <select wire:model="bimestre" id="bimestre" class="form-input w-full max-w-xs" required>
                    <option value="0">— Elegir —</option>
                    @foreach ($bimestres as $num => $b)
                        <option value="{{ $num }}">{{ $b['label'] }} — {{ $b['titulo'] }}</option>
                    @endforeach
                </select>
                @error('bimestre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="anio" class="form-label">Año lectivo</label>
                <input wire:model="anio" type="number" id="anio" class="form-input w-full max-w-[8rem]" min="2020" max="2035" required>
                @error('anio') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-neutral-700 cursor-pointer">
                <input wire:model="soloPrueba" type="checkbox" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                Solo prueba (generar PDF pero no enviar mails)
            </label>

            <div>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" @if($polling) disabled @endif>
                    <span wire:loading.remove wire:target="enviar">
                        @if($soloPrueba)
                            Generar PDF de prueba para todos
                        @else
                            Enviar por correo a todos los docentes
                        @endif
                    </span>
                    <span wire:loading wire:target="enviar">Iniciando…</span>
                </button>
            </div>
        </form>

        @if ($polling || ! empty($progreso['lista']))
            <div class="rounded-xl border border-accent-200 bg-accent-50/80 p-4 space-y-3">
                <p class="text-sm font-semibold text-neutral-800">
                    @if (! empty($progreso['done']))
                        @if ($esModoPrueba)
                            Generación de prueba finalizada
                        @else
                            Proceso finalizado
                        @endif
                    @else
                        @if ($esModoPrueba)
                            Generando PDF…
                        @else
                            Enviando…
                        @endif
                        @if (($progreso['total'] ?? 0) > 0)
                            <span class="tabular-nums">{{ (int) ($progreso['current'] ?? 0) }}/{{ (int) $progreso['total'] }}</span>
                        @endif
                    @endif
                </p>

                @if (! empty($progreso['nombre']) && empty($progreso['done']))
                    <p class="text-xs text-neutral-600 truncate">{{ $progreso['nombre'] }}</p>
                @endif

                @if (! empty($progreso['lista']))
                    <div class="max-h-56 overflow-y-auto rounded-lg border border-accent-200 bg-white">
                        <table class="min-w-full text-xs">
                            <thead class="bg-accent-50 sticky top-0">
                                <tr>
                                    <th class="table-header">Docente</th>
                                    <th class="table-header">Resultado</th>
                                    @if ($esModoPrueba)
                                        <th class="table-header text-right">PDF</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-accent-100">
                                @foreach ($progreso['lista'] as $r)
                                    <tr>
                                        <td class="table-cell">{{ $r['nombre'] ?? '' }}</td>
                                        <td class="table-cell">
                                            @if (($r['estado'] ?? '') === 'enviado')
                                                <span class="text-green-700 font-medium">✓ Enviado</span>
                                            @elseif (($r['estado'] ?? '') === 'generado')
                                                <span class="text-primary-700 font-medium">✓ PDF generado</span>
                                            @elseif (($r['estado'] ?? '') === 'omitido')
                                                <span class="text-neutral-500">— Sin email</span>
                                            @elseif (($r['estado'] ?? '') === 'error')
                                                <span class="text-red-700 font-medium" title="{{ $r['detalle'] ?? '' }}">✗ Error</span>
                                            @endif
                                        </td>
                                        @if ($esModoPrueba)
                                            <td class="table-cell text-right">
                                                @if (($r['estado'] ?? '') === 'generado' && ! empty($r['idProfesor']))
                                                    <button type="button"
                                                            wire:click="abrirRevisionPorProfesor({{ (int) $r['idProfesor'] }})"
                                                            class="text-primary-700 font-semibold hover:underline">
                                                        Ver
                                                    </button>
                                                @else
                                                    <span class="text-neutral-400">—</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if (! empty($progreso['mensaje']) && ! empty($progreso['done']))
                    <div class="rounded-lg border px-3 py-2 text-sm {{ ($progreso['errores'] ?? 0) > 0 ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800' }}">
                        {{ $progreso['mensaje'] }}
                    </div>
                @endif

                @if ($esModoPrueba && ! empty($progreso['done']) && count($pdfsRevision) > 0)
                    <div class="flex flex-wrap gap-2 pt-1">
                        <button type="button" wire:click="abrirRevision(0)" class="btn-primary">
                            Revisar informes uno por uno
                        </button>
                        <p class="text-xs text-neutral-600 self-center">
                            {{ count($pdfsRevision) }} informe(s) listos para revisar
                        </p>
                    </div>
                @endif
            </div>
        @endif

        @if ($revisionAbierta && $pdfActualUrl)
            <div class="rounded-2xl border border-accent-200 bg-white shadow-sm overflow-hidden">
                <div class="flex flex-col gap-3 border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Revisión de informes</p>
                        <p class="text-sm font-semibold text-neutral-800 truncate">
                            {{ $pdfActual['nombre'] ?? '' }}
                        </p>
                        <p class="text-xs text-neutral-600 tabular-nums">
                            {{ $revisionIndice + 1 }} de {{ count($pdfsRevision) }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <button type="button"
                                wire:click="revisionAnterior"
                                class="btn-secondary"
                                @disabled($revisionIndice <= 0)>
                            ← Anterior
                        </button>
                        <button type="button"
                                wire:click="revisionSiguiente"
                                class="btn-secondary"
                                @disabled($revisionIndice >= count($pdfsRevision) - 1)>
                            Siguiente →
                        </button>
                        <a href="{{ $pdfActualUrl }}" target="_blank" rel="noopener" class="btn-secondary">
                            Abrir en pestaña
                        </a>
                        <button type="button" wire:click="cerrarRevision" class="btn-secondary">
                            Cerrar revisión
                        </button>
                    </div>
                </div>
                <iframe wire:key="pdf-revision-{{ $revisionIndice }}-{{ $pdfActual['idProfesor'] ?? 0 }}"
                        src="{{ $pdfActualUrl }}"
                        title="Informe PDF — {{ $pdfActual['nombre'] ?? '' }}"
                        class="w-full h-[min(75vh,720px)] border-0 bg-neutral-100">
                </iframe>
            </div>
        @endif

        <p class="text-[11px] text-neutral-500 leading-relaxed">
            El envío usa la configuración de correo del sistema (<code class="text-neutral-600">MAIL_*</code> en <code class="text-neutral-600">.env</code>).
            En modo prueba los PDF se pueden revisar en pantalla antes de enviar correos reales.
        </p>
    </div>
</div>
