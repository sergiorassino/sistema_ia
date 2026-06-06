@php
    use App\Livewire\Comunicaciones\InformeEnvioComunicado as InformeEnv;
    use App\Support\ComunicacionesRutasGestion;
@endphp

<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Comunicaciones</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Informe de envío</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 flex-col gap-2 sm:flex-row sm:items-center">
                <a href="{{ ComunicacionesRutasGestion::route('abrir', ['id' => (int) $informe['id_hilo']]) }}"
                   class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                    Ir al comunicado
                </a>
                <a href="{{ ComunicacionesRutasGestion::route('index') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                    Volver a la bandeja
                </a>
            </div>
        </div>
    </section>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm font-medium text-primary-900">
            {{ session('success') }}
        </div>
    @endif

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="se-section-title">Resumen</p>
            <p class="mt-1 text-sm font-semibold text-neutral-900">{{ $informe['asunto'] }}</p>
            @if (($informe['contenido_preview'] ?? '') !== '')
                <p class="mt-2 text-xs leading-relaxed text-neutral-600">{{ $informe['contenido_preview'] }}</p>
            @endif
            <p class="mt-3 flex flex-wrap gap-2 text-xs text-neutral-600">
                @php $t = $informe['totales']; @endphp
                <span class="se-pill border border-emerald-200 bg-emerald-50 text-emerald-800">Enviado: {{ $t['enviado'] }}</span>
                <span class="se-pill border border-red-200 bg-red-50 text-red-800">Fallido: {{ $t['fallido'] }}</span>
                <span class="se-pill border border-neutral-200 bg-neutral-50 text-neutral-700">No aplica: {{ $t['no_aplicable'] }}</span>
                <span class="se-pill border border-amber-200 bg-amber-50 text-amber-900">Pendiente: {{ $t['pendiente'] }}</span>
            </p>
        </div>

        <div class="border-t border-accent-100 bg-accent-50/30 p-5 sm:p-6">
            @if ($waLinks && ! empty($waLinks['links'] ?? []))
                @php
                    $waWinName = (string) config('comunicaciones.whatsapp_wa_link_target', 'comunicaciones_whatsapp_wa');
                @endphp
                <div class="mb-6 rounded-2xl border border-accent-200 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-500">Enlaces WhatsApp (manual)</p>
                    <ul class="mt-2 space-y-2">
                        @foreach ($waLinks['links'] as $link)
                            <li class="text-sm">
                                <a href="{{ $link['url'] }}"
                                   target="{{ $waWinName }}"
                                   data-se-wa-reuse="1"
                                   data-se-wa-window-name="{{ e($waWinName) }}"
                                   class="font-semibold text-primary-700 underline decoration-primary-300 underline-offset-2 hover:text-primary-900">
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-500">Detalle por destinatario y medio</p>
            <p class="mt-1 text-xs text-neutral-600">Cada fila es un intento de envío (push, correo o WhatsApp). «No aplica» indica que no hubo canal disponible (p. ej. sin mail o sin suscripción push).</p>

            <div class="mt-4 w-full overflow-x-auto rounded-2xl border border-accent-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-accent-100 text-left text-sm">
                    <thead class="bg-accent-50/80">
                        <tr>
                            <th class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Destinatario</th>
                            <th class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Medio</th>
                            <th class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Resultado</th>
                            <th class="px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100">
                        @forelse ($informe['filas'] as $fila)
                            <tr class="hover:bg-accent-50/50">
                                <td class="max-w-[12rem] px-3 py-2 text-xs font-medium text-neutral-900 sm:max-w-md">
                                    <span class="break-words">{{ $fila['nombre'] }}</span>
                                    <span class="mt-0.5 block text-[10px] font-normal text-neutral-500">{{ $fila['tipo_destinatario'] === 'familia' ? 'Familia' : 'Docente' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-xs text-neutral-700">{{ InformeEnv::medioEtiqueta($fila['medio']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    @php
                                        $est = $fila['estado'];
                                        $medioFila = (string) ($fila['medio'] ?? '');
                                        $waManual = \App\Models\ComMensajeEnvio::esWhatsappEnvioManualWaMe($medioFila, $est, $fila['proveedor_msgid'] ?? null);
                                        $estClass = match (true) {
                                            $waManual => 'border-sky-200 bg-sky-50 text-sky-900',
                                            $est === 'enviado' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                                            $est === 'fallido' => 'border-red-200 bg-red-50 text-red-800',
                                            $est === 'no_aplicable' => 'border-neutral-200 bg-neutral-100 text-neutral-700',
                                            $est === 'pendiente' => 'border-amber-200 bg-amber-50 text-amber-900',
                                            default => 'border-accent-200 bg-white text-neutral-700',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-lg border px-2 py-0.5 text-[10px] font-semibold {{ $estClass }}">{{ InformeEnv::estadoEtiqueta($est, $medioFila, $fila['proveedor_msgid'] ?? null) }}</span>
                                </td>
                                <td class="max-w-xs px-3 py-2 text-xs text-neutral-600">
                                    @if (! empty($fila['motivo']))
                                        <span class="break-words">{{ $fila['motivo'] }}</span>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-8 text-center text-sm text-neutral-500">No hay registros de envío para este mensaje.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
