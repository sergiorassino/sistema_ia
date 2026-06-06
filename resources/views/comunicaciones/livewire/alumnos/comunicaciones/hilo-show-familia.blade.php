<div class="se-page max-w-4xl">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 flex-1 space-y-2">
                <p class="se-eyebrow">Comunicaciones</p>
                <h2 class="break-words text-xl font-bold tracking-tight sm:text-2xl">{{ $hilo->asunto }}</h2>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-white/75">
                    <span>{{ $hilo->scopeLabel() }} · Iniciado {{ \Carbon\Carbon::parse($hilo->created_at)->format('d/m/Y H:i') }}</span>
                    @if ($hilo->estado === 'cerrado')
                        <span class="se-pill border-white/30 bg-white/10 text-white">Cerrado</span>
                    @endif
                    @if ($hilo->esComunicadoInformativoEscuela())
                        <span class="inline-flex items-center rounded-full border border-amber-300/90 bg-amber-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900">
                            Solo informativo · sin respuesta familia
                        </span>
                    @endif
                </div>
                @if (! empty($paraCompleto))
                    <p class="text-sm text-white/85">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/60">Para:</span>
                        <span class="ml-2">{{ $paraCompleto }}</span>
                    </p>
                @endif
            </div>
            <a href="{{ route('alumnos.comunicaciones.index') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Bandeja
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @error('modalBorrar')
        <div class="se-soft-card flex items-center gap-3 border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <svg class="h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 4.93l14.14 14.14M12 3a9 9 0 100 18 9 9 0 000-18z"/>
            </svg>
            {{ $message }}
        </div>
    @enderror

    @error('marcarNoLeido')
        <div class="se-soft-card flex items-center gap-3 border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <svg class="h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 4.93l14.14 14.14M12 3a9 9 0 100 18 9 9 0 000-18z"/>
            </svg>
            {{ $message }}
        </div>
    @enderror

    <div class="space-y-8">
        @forelse ($mensajesPorDia as $fecha => $mensajes)
            <div>
                <div class="mb-4 flex items-center gap-3">
                    <div class="h-px flex-1 bg-accent-200"></div>
                    <span class="px-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-500">
                        {{ \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM') }}
                    </span>
                    <div class="h-px flex-1 bg-accent-200"></div>
                </div>

                <div class="space-y-3">
                    @foreach ($mensajes as $msg)
                        @php
                            $esPlataforma = $msg->tipo_remitente === 'profesor';
                            $esEnviadoPorFamilia = $msg->tipo_remitente === 'familia'
                                && (int) ($msg->id_legajo ?? 0) === (int) $idLegajoSesion;
                            $miDestMarcar = null;
                            if ($esPlataforma) {
                                $miDestMarcar = $msg->destinatarios->first(function ($d) use ($idLegajoSesion) {
                                    return $d->tipo_destinatario === 'familia'
                                        && (int) $d->id_legajo === (int) $idLegajoSesion;
                                });
                            }
                            $lecturaEnviado = $esEnviadoPorFamilia ? $msg->resumenLecturaDestinatarios() : null;
                        @endphp
                        <div @class(['flex', 'justify-start' => $esPlataforma, 'justify-end' => ! $esPlataforma])>
                            <div @class([
                                'max-w-[85%] rounded-3xl px-4 py-3 shadow-sm sm:max-w-[80%]',
                                'rounded-tl-md border border-accent-200 bg-accent-50/70 text-neutral-900' => $esPlataforma,
                                'rounded-tr-md bg-gradient-to-br from-primary-600 to-primary-700 text-white' => ! $esPlataforma,
                            ])>
                                <div @class([
                                    'mb-1 flex flex-wrap items-center justify-between gap-x-2 gap-y-1 text-xs font-semibold',
                                    'text-neutral-600' => $esPlataforma,
                                    'text-white/85' => ! $esPlataforma,
                                ])>
                                    <span class="min-w-0">
                                        @if ($esPlataforma)
                                            {{ $msg->nombre_remitente_snapshot ?? 'Personal escolar' }}
                                        @else
                                            {{ $msg->nombre_remitente_snapshot ?? 'Familia' }}
                                            @if ($msg->vinculo_familiar)
                                                <span class="ml-1 font-normal text-white/65">
                                                    ({{ $msg->vinculoLabel() }})
                                                </span>
                                            @endif
                                        @endif
                                    </span>
                                    @if ($lecturaEnviado)
                                        @include('comunicaciones::partials.marca-lectura-destinatarios', [
                                            'lectura' => $lecturaEnviado,
                                            'variante' => 'oscura',
                                            'idMensaje' => (int) $msg->id,
                                        ])
                                    @endif
                                </div>

                                <p @class([
                                    'whitespace-pre-wrap text-left text-sm leading-relaxed',
                                    'text-neutral-900' => $esPlataforma,
                                    'text-white' => ! $esPlataforma,
                                ])>{{ preg_replace('/\A[\p{Z}\s]+/u', '', (string) $msg->contenido) }}</p>

                                <div @class([
                                    'mt-2 flex items-center justify-between gap-3',
                                    'text-neutral-500' => $esPlataforma,
                                    'text-white/65' => ! $esPlataforma,
                                ])>
                                    <span class="text-[10px] tabular-nums">
                                        {{ $msg->hora ? substr($msg->hora, 0, 5) : '' }}
                                    </span>
                                    <div class="flex flex-wrap items-center justify-end gap-x-2 gap-y-1">
                                        @if ($miDestMarcar && $miDestMarcar->leido_at)
                                            <button type="button"
                                                    wire:click="marcarMensajeNoLeido({{ (int) $msg->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="marcarMensajeNoLeido"
                                                    @class([
                                                        'cursor-pointer text-[10px] font-semibold uppercase tracking-wide transition opacity-80 hover:opacity-100',
                                                        'text-neutral-600' => $esPlataforma,
                                                        'text-white/90' => ! $esPlataforma,
                                                    ])>
                                                <span wire:loading.remove wire:target="marcarMensajeNoLeido">Marcar como no leído</span>
                                                <span wire:loading wire:target="marcarMensajeNoLeido">…</span>
                                            </button>
                                        @endif
                                        @php($borrado = $this->infoBorradoMensaje($msg, (int) $hilo->cuerpo_inicial_id, $hilo->mensajes->count()))
                                        <button type="button"
                                                @if ($borrado['puede'])
                                                    wire:click="abrirModalBorrar({{ (int) $msg->id }})"
                                                @else
                                                    disabled
                                                    title="{{ $borrado['motivo'] }}"
                                                @endif
                                                @class([
                                                    'text-[10px] font-semibold uppercase tracking-wide transition',
                                                    'cursor-pointer opacity-80 hover:opacity-100' => $borrado['puede'],
                                                    'cursor-not-allowed opacity-40' => ! $borrado['puede'],
                                                ])>
                                            Borrar
                                        </button>

                                        @if ($msg->destinatarios->count())
                                            <div class="flex items-center gap-1">
                                                @foreach ($msg->destinatarios->take(1)->first()?->envios ?? [] as $envio)
                                                    <span title="{{ $envio->medio }}: {{ $envio->estadoLabel() }}{{ $envio->motivo ? ' — '.$envio->motivo : '' }}"
                                                          @class([
                                                              'text-[10px]',
                                                              'text-neutral-700' => $esPlataforma && $envio->estado === 'enviado',
                                                              'text-amber-700' => $esPlataforma && $envio->estado === 'pendiente',
                                                              'text-red-700' => $esPlataforma && $envio->estado === 'fallido',
                                                              'text-neutral-500' => $esPlataforma && $envio->estado === 'no_aplicable',
                                                              'text-white/90' => ! $esPlataforma,
                                                          ])>
                                                        {{ $envio->iconoMedio() }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="se-card p-10">
                <div class="flex flex-col items-center justify-center gap-3 text-center sm:flex-row sm:text-left">
                    <div class="se-icon-badge h-14 w-14">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-neutral-800">Sin mensajes</p>
                        <p class="mt-1 text-sm text-neutral-600">Este hilo aún no tiene mensajes.</p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    @if ($puedeResponder && $hilo->estado === 'abierto')
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/50 px-5 py-3">
                <p class="se-section-title">Mensaje</p>
            </div>
            <div class="p-5">
                @if (! $mostrarFormRespuesta)
                    <button type="button"
                            wire:click="$set('mostrarFormRespuesta', true)"
                            class="w-full rounded-2xl border border-dashed border-accent-300 bg-accent-50/40 px-4 py-3 text-left text-sm font-medium text-neutral-500 transition hover:border-primary-300 hover:bg-accent-50 hover:text-primary-800">
                        Escribir respuesta…
                    </button>
                @else
                    <div class="space-y-3">
                        <div>
                            <label for="com-fam-vinculo" class="form-label">Yo soy el/la…</label>
                            <select id="com-fam-vinculo"
                                    wire:model="vinculo"
                                    class="form-select mt-1.5">
                                <option value="">Seleccionar…</option>
                                @foreach (['madre' => 'Madre', 'padre' => 'Padre', 'tutor' => 'Tutor/a', 'resp_admin' => 'Resp. Administrativo/a', 'otro' => 'Otro responsable'] as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </select>
                            @error('vinculo') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <textarea wire:model="respuesta"
                                  rows="4"
                                  placeholder="Escriba su respuesta…"
                                  autofocus
                                  maxlength="{{ $maxContenido }}"
                                  class="form-input min-h-[6rem] resize-none leading-relaxed"></textarea>
                        @error('respuesta') <p class="form-error">{{ $message }}</p> @enderror
                        <p class="text-right text-xs text-neutral-500 tabular-nums">{{ mb_strlen($respuesta) }} / {{ $maxContenido }}</p>
                        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                            <button type="button"
                                    wire:click="$set('mostrarFormRespuesta', false)"
                                    class="text-sm font-semibold text-neutral-500 transition hover:text-neutral-800">
                                Cancelar
                            </button>
                            <button type="button"
                                    wire:click="responder"
                                    wire:loading.attr="disabled"
                                    class="btn-primary disabled:opacity-60">
                                <span wire:loading.remove wire:target="responder">Enviar respuesta</span>
                                <span wire:loading wire:target="responder">Enviando…</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @include('comunicaciones::partials.modal-detalle-lectura')

    @if ($modalBorrarAbierto)
        <div class="fixed inset-0 z-[90] flex items-center justify-center px-4 py-8 sm:px-6" role="dialog" aria-modal="true"
             aria-labelledby="com-fam-borrar-titulo">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalBorrar"></div>

            <div class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                <div class="border-b border-accent-200 bg-accent-50/60 px-5 py-4">
                    <p id="com-fam-borrar-titulo" class="text-sm font-bold text-neutral-900">
                        {{ $modalBorrarEliminaHiloCompleto ? 'Eliminar comunicado' : 'Eliminar mensaje' }}
                    </p>
                    <p class="mt-1 text-xs leading-relaxed text-neutral-600">
                        @if ($modalBorrarEliminaHiloCompleto)
                            Va a eliminar el comunicado completo (no hay otros mensajes en el hilo).
                        @else
                            Va a eliminar solo este mensaje del hilo.
                        @endif
                        Esta acción no se puede deshacer.
                    </p>
                    @error('modalBorrar')
                        <p class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-2 border-t border-accent-200 bg-accent-50/40 px-5 py-4 sm:flex-row sm:justify-end">
                    <button type="button"
                            wire:click="cerrarModalBorrar"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-800 shadow-sm transition hover:bg-accent-50 sm:w-auto">
                        Cancelar
                    </button>
                    <button type="button"
                            wire:click="confirmarModalBorrar"
                            wire:loading.attr="disabled"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 disabled:opacity-60 sm:w-auto">
                        <span wire:loading.remove wire:target="confirmarModalBorrar">Eliminar</span>
                        <span wire:loading wire:target="confirmarModalBorrar">Eliminando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
