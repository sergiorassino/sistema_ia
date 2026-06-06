<div class="se-page">
    @if (session()->has('success'))
        <div class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Notificaciones</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Enviar notificación push</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                    <p class="mt-1 max-w-2xl text-sm text-white/70">
                        Llega a dispositivos con notificaciones activas de cada alumno.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <div class="se-toolbar">
        <div class="flex flex-wrap items-center gap-2">
            <span class="se-pill tabular-nums">Destinatarios: {{ $preview['destinatarios'] ?? 0 }}</span>
            <span class="se-pill tabular-nums">Suscriptos: {{ $preview['suscriptos'] ?? 0 }}</span>
            <span class="se-pill tabular-nums">Sin suscripción: {{ $preview['no_suscriptos'] ?? 0 }}</span>
        </div>
        <button type="button" wire:click="previewNow" class="btn-secondary btn-sm shrink-0">
            Actualizar vista previa
        </button>
    </div>

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="se-section-title">Configuración del envío</p>
            <p class="mt-1 text-sm text-neutral-600">Elegí alcance y redactá el aviso.</p>
        </div>

        <div class="grid grid-cols-1 gap-6 border-t border-accent-100 bg-accent-50/30 p-5 sm:p-6 lg:grid-cols-12">
            <div class="space-y-4 lg:col-span-5">
                <div>
                    <label for="tipo-destino-push" class="form-label">Destino</label>
                    <select id="tipo-destino-push"
                            wire:model.live="tipoDestino"
                            wire:change="previewNow"
                            class="form-select">
                        <option value="alumno">Un alumno</option>
                        <option value="curso">Un curso</option>
                        <option value="colegio">Todo el colegio (contexto)</option>
                    </select>
                    @error('tipoDestino') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                @if ($tipoDestino === 'alumno')
                    <div>
                        <span class="form-label">Alumno</span>
                        @if ($alumnoId && $alumnoLabel)
                            <div class="flex items-center justify-between gap-2 rounded-2xl border border-accent-200 bg-white px-3 py-2.5 shadow-sm">
                                <div class="min-w-0 truncate text-sm text-neutral-900">{{ $alumnoLabel }}</div>
                                <button type="button" wire:click="clearAlumno" class="btn-secondary btn-sm shrink-0">
                                    Quitar
                                </button>
                            </div>
                        @else
                            <input type="text"
                                   wire:model.live.debounce.250ms="alumnoSearch"
                                   placeholder="Buscar por apellido, nombre o DNI…"
                                   class="form-input mt-1.5" />
                            @error('alumnoId') <p class="form-error">{{ $message }}</p> @enderror

                            @if (! empty($alumnoResults))
                                <div class="mt-2 max-h-64 overflow-auto rounded-2xl border border-accent-200 bg-white shadow-sm divide-y divide-accent-100">
                                    @foreach ($alumnoResults as $r)
                                        <button type="button"
                                                class="block w-full px-3 py-2.5 text-left text-sm transition hover:bg-accent-50"
                                                wire:click="selectAlumno({{ $r['id'] }}, @js($r['label']))">
                                            <span class="font-semibold text-neutral-900">{{ $r['label'] }}</span>
                                            @if (! empty($r['dni']))
                                                <span class="mt-0.5 block text-xs text-neutral-500">DNI {{ $r['dni'] }}</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

                @if ($tipoDestino === 'curso')
                    <div>
                        <label for="curso-push" class="form-label">Curso</label>
                        <select id="curso-push"
                                wire:model.live="cursoId"
                                wire:change="previewNow"
                                class="form-select">
                            <option value="">Seleccionar…</option>
                            @foreach ($cursos as $c)
                                <option value="{{ $c['id'] }}">{{ $c['label'] }}</option>
                            @endforeach
                        </select>
                        @error('cursoId') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            <div class="space-y-4 lg:col-span-7">
                <div>
                    <label for="push-title" class="form-label">Título</label>
                    <input id="push-title" type="text" wire:model.live="title" maxlength="80" class="form-input">
                    @error('title') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="push-body" class="form-label">
                        Mensaje (máx. {{ \App\Push\WebPushService::MAX_MENSAJE_CARACTERES }} caracteres)
                    </label>
                    <textarea id="push-body" wire:model.live="body" rows="6" class="form-input leading-relaxed"></textarea>
                    <p class="mt-1 text-xs text-neutral-500">
                        {{ mb_strlen($body ?? '') }} / {{ \App\Push\WebPushService::MAX_MENSAJE_CARACTERES }}
                    </p>
                    @error('body') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="push-url" class="form-label">URL al hacer clic (opcional)</label>
                    <input id="push-url" type="text" wire:model.live="url" class="form-input" placeholder="https://…">
                    @error('url') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <button type="button" wire:click="send" class="btn-primary">
                        Enviar
                    </button>
                    <span wire:loading wire:target="send" class="text-sm text-neutral-600">Enviando…</span>
                </div>

                @if (is_array($lastSend))
                    <div class="rounded-2xl border border-accent-200 bg-white p-4 text-sm text-neutral-800 shadow-sm">
                        <p>
                            Resultado:
                            <span class="font-semibold text-primary-700">{{ $lastSend['sent'] ?? 0 }}</span> enviados,
                            <span class="font-semibold text-red-700">{{ $lastSend['failed'] ?? 0 }}</span> fallidos.
                        </p>

                        @if (! empty($lastSend['failed_user_keys']))
                            <div class="mt-3 border-t border-accent-100 pt-3">
                                <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-neutral-500">Fallidos por alumno</p>
                                <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-neutral-700">
                                    @foreach ($lastSend['failed_user_keys'] as $uk => $motivo)
                                        <li><span class="font-semibold font-mono">{{ $uk }}</span>: {{ $motivo }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
