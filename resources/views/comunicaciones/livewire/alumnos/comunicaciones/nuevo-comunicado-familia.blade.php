<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Comunicaciones</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Nuevo comunicado</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ studentCtx()->nivelNombre() }} · Ciclo lectivo {{ studentCtx()->terlecAno() }}
                    </p>
                </div>
            </div>

            <a href="{{ route('alumnos.comunicaciones.index') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver a la bandeja
            </a>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="se-section-title">Destinatarios y mensaje</p>
            <p class="mt-1 text-sm text-neutral-600">El envío respeta canales y políticas definidas por la institución.</p>
        </div>

        <div class="space-y-6 border-t border-accent-100 bg-accent-50/30 p-5 sm:p-6">
            <div>
                <label for="fam-vinculo-nuevo" class="form-label">Yo soy el/la…</label>
                <select id="fam-vinculo-nuevo"
                        wire:model="vinculo"
                        class="form-select mt-1.5">
                    <option value="">Seleccionar vínculo…</option>
                    @foreach ($vinculos as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('vinculo') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <span class="form-label">Quiero comunicarme con…</span>
                @if (empty($opcionesRolReceptor))
                    <p class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        No hay destinatarios habilitados para familias en este nivel. Consulte con la institución.
                    </p>
                @else
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($opcionesRolReceptor as $op)
                            <button type="button"
                                    wire:click="$set('rolReceptor', '{{ $op['value'] }}')"
                                    @class([
                                        'inline-flex cursor-pointer items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-semibold shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
                                        'border-primary-500 bg-primary-600 text-white' => $rolReceptor === $op['value'],
                                        'border-accent-200 bg-white text-neutral-700 hover:bg-accent-50' => $rolReceptor !== $op['value'],
                                    ])>
                                {{ $op['label'] }}
                            </button>
                        @endforeach
                    </div>
                @endif
                @error('rolReceptor') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            @if (! empty($destinatariosDisponibles))
                <div>
                    <label for="fam-destinatario-nuevo" class="form-label">Destinatario específico</label>
                    <select id="fam-destinatario-nuevo"
                            wire:model="idDestinatario"
                            class="form-select mt-1.5">
                        <option value="">Seleccionar…</option>
                        @foreach ($destinatariosDisponibles as $d)
                            <option value="{{ $d['id'] }}">{{ $d['label'] }}</option>
                        @endforeach
                    </select>
                    @error('idDestinatario') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @elseif ($rolReceptor !== '')
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    No se encontraron destinatarios disponibles para ese rol. Consulte con la institución.
                </div>
            @endif

            <div>
                <label for="fam-asunto-nuevo" class="form-label">Asunto</label>
                <input id="fam-asunto-nuevo"
                       type="text"
                       wire:model="asunto"
                       maxlength="{{ $maxAsunto }}"
                       placeholder="Motivo del comunicado…"
                       class="form-input mt-1.5"/>
                @error('asunto') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="fam-contenido-nuevo" class="form-label">Mensaje</label>
                <textarea id="fam-contenido-nuevo"
                          wire:model="contenido"
                          rows="5"
                          maxlength="{{ $maxContenido }}"
                          placeholder="Escriba el comunicado aquí…"
                          class="form-input mt-1.5 resize-none leading-relaxed"></textarea>
                <p class="mt-1 text-right text-xs text-neutral-500 tabular-nums">
                    {{ mb_strlen($contenido) }} / {{ $maxContenido }}
                </p>
                @error('contenido') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end border-t border-accent-200 pt-2">
                <button type="button"
                        wire:click="enviar"
                        wire:loading.attr="disabled"
                        class="btn-primary disabled:opacity-60">
                    <span wire:loading wire:target="enviar" class="mr-2 inline-flex">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </span>
                    Enviar comunicado
                </button>
            </div>
        </div>
    </div>
</div>
