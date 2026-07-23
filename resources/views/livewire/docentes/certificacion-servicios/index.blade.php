<div class="se-page">
    @if (session('success'))
        <div class="se-soft-card border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800" role="status">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="se-soft-card border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Docentes</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Certificación de servicios</h2>
                <p class="text-sm text-white/80">{{ schoolCtx()->nivelNombre() }} · Seleccione un docente para cargar servicios y licencias</p>
            </div>
        </div>
    </section>

    @if (! $tablasOk)
        <div class="se-card px-5 py-8 text-center text-sm text-neutral-600">
            {{ $mensajeTablas }}
        </div>
    @else
        <div class="se-toolbar se-matriz-list-toolbar--angosta">
            <div class="relative min-w-0 flex-1 sm:max-w-sm">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="search"
                       wire:model.live.debounce.400ms="buscar"
                       placeholder="Apellido, nombre o DNI…"
                       class="form-input w-full pl-9"
                       autocomplete="off"
                       aria-label="Buscar docente">
            </div>
            <p class="shrink-0 text-[11px] font-medium tabular-nums text-neutral-500">
                {{ $profesores->total() }} registros
            </p>
        </div>

        <div class="se-card overflow-hidden">
            <div class="se-cierre-anual-body-wrap se-matriz-list-scroll se-grid-angosta-wrap" tabindex="0">
                <table class="se-matriz-list-tabla se-grid-pocos-campos table-fixed">
                    <thead>
                        <tr>
                            <th scope="col" class="text-left">Docente</th>
                            <th scope="col" class="text-left">DNI</th>
                            <th scope="col" class="text-left">Rol</th>
                            <th scope="col" class="text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($profesores as $p)
                            <tr wire:key="cert-serv-prof-{{ $p->id }}">
                                <td class="font-semibold text-neutral-900">{{ $p->apellido }}, {{ $p->nombre }}</td>
                                <td class="tabular-nums text-neutral-700">{{ $p->dni ?: '—' }}</td>
                                <td class="text-neutral-700">{{ $p->tipo?->tipo ?? '—' }}</td>
                                <td class="text-right">
                                    <a href="{{ route('docentes.certificacion-servicios.form', ['idPersonal' => $p->id]) }}"
                                       class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow-sm transition hover:bg-primary-700">
                                        Abrir
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-xs text-neutral-500">
                                    No hay docentes que coincidan con la búsqueda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($profesores->hasPages())
                <div class="se-matriz-list-footer">
                    {{ $profesores->links('vendor.pagination.se-compact') }}
                </div>
            @endif
        </div>
    @endif
</div>
