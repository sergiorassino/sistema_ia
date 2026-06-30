<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Comunicación institucional</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Correo masivo a estudiantes</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo {{ schoolCtx()->terlecAno() }} · Auditoría de envíos BCC
                    </p>
                </div>
            </div>
            <a href="{{ route('emails-masivos.nuevo') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-50">
                Nuevo envío
            </a>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="se-section-title">Historial de campañas</p>
            <p class="mt-1 text-sm text-neutral-600">Envíos registrados en copia oculta (BCC). Tope por operación: {{ $maxDestinatarios }} destinatarios.</p>
        </div>

        <div class="se-toolbar flex flex-wrap items-end gap-4 border-b border-accent-100 bg-accent-50/40 px-5 py-4">
            <div>
                <label class="form-label" for="filtro-asunto-em">Asunto</label>
                <input id="filtro-asunto-em" type="search" wire:model.live.debounce.400ms="filtroAsunto" class="form-input mt-1 w-64 max-w-full" placeholder="Buscar…">
            </div>
            <div>
                <label class="form-label" for="periodo-em">Período</label>
                <select id="periodo-em" wire:model.live="periodo" class="form-input mt-1">
                    <option value="actual">Ciclo actual</option>
                    <option value="todos">Todos los ciclos</option>
                </select>
            </div>
        </div>

        <div class="w-full overflow-x-auto">
            <div class="flex justify-start">
                <table class="se-matriz-list-tabla min-w-[48rem]">
                    <thead>
                        <tr>
                            <th>Fecha / hora</th>
                            <th>Remitente</th>
                            <th>Asunto</th>
                            <th class="text-center">Envíos</th>
                            <th>Adjuntos</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($campanias as $c)
                            @php
                                $prof = $profesores->get($c->idProfesores);
                                $nombreProf = $prof ? trim($prof->apellido . ', ' . $prof->nombre) : '—';
                            @endphp
                            <tr>
                                <td>{{ $c->fechhora?->format('d/m/Y H:i') }}</td>
                                <td>{{ $nombreProf }}</td>
                                <td class="max-w-xs truncate" title="{{ $c->subject }}">{{ $c->subject }}</td>
                                <td class="text-center">{{ $c->total_envios }}</td>
                                <td class="text-xs text-neutral-600">{{ $c->attached ?: '—' }}</td>
                                <td class="text-right">
                                    <a href="{{ route('emails-masivos.campana', ['id' => $c->id_seed]) }}"
                                       class="text-sm font-semibold text-primary-700 hover:text-primary-800">Ver detalle</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-sm text-neutral-500">No hay envíos registrados con estos filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($campanias->hasPages())
            <div class="border-t border-accent-100 px-5 py-4">{{ $campanias->links() }}</div>
        @endif
    </div>
</div>
