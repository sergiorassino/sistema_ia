<div class="se-card overflow-hidden">
    <div class="border-b border-accent-200 bg-white px-5 py-4">
        <p class="se-section-title">Confirmar envío</p>
        <p class="mt-1 text-sm text-neutral-600">Los destinatarios recibirán el mensaje en <strong>copia oculta (BCC)</strong>; no verán el email de otras familias.</p>
    </div>
    <div class="space-y-6 p-5 sm:p-6">
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt class="text-[10px] font-semibold uppercase text-neutral-500">Remitente</dt><dd class="text-sm">{{ $profesor?->nombre_completo }} · {{ $profesor?->email }}</dd></div>
            <div><dt class="text-[10px] font-semibold uppercase text-neutral-500">Ciclo</dt><dd class="text-sm">{{ schoolCtx()->terlecAno() }}</dd></div>
            <div><dt class="text-[10px] font-semibold uppercase text-neutral-500">Asunto</dt><dd class="text-sm">{{ $asunto }}</dd></div>
            <div><dt class="text-[10px] font-semibold uppercase text-neutral-500">Correos en BCC</dt><dd class="text-sm font-semibold text-primary-800">{{ $nEnvios }}</dd></div>
        </dl>

        @if ($superaTope)
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                Supera el máximo de {{ $maxEnvio }} destinatarios por envío. Volvé atrás y reducí la selección.
            </div>
        @elseif ($superaAviso)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Envío grande (más de {{ $avisoEnvio }} destinatarios). Verificá la lista antes de confirmar.
            </div>
        @endif

        <div class="w-full overflow-x-auto">
            <div class="flex justify-start">
                <table class="se-matriz-list-tabla min-w-[40rem]">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Alumno</th>
                            <th>Curso</th>
                            <th>Tipo</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($destinatariosPreview as $i => $d)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $d['alumnoLabel'] }}</td>
                                <td>{{ $d['cursoLabel'] ?: '—' }}</td>
                                <td class="capitalize">{{ $d['tipo'] }}</td>
                                <td class="font-mono text-xs">{{ $d['email'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-wrap justify-between gap-3">
            <button type="button" wire:click="volverADestinatarios" class="rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold">Volver</button>
            <button type="button"
                    wire:click="confirmarYEnviar"
                    @disabled($superaTope || ! $credencialesOk)
                    wire:loading.attr="disabled"
                    wire:target="confirmarYEnviar"
                    class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50">
                <span wire:loading.remove wire:target="confirmarYEnviar">Confirmar y enviar (BCC)</span>
                <span wire:loading wire:target="confirmarYEnviar">Enviando…</span>
            </button>
        </div>
    </div>
</div>
