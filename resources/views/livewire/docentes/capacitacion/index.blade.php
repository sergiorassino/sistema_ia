<div>
    <div class="se-page">
        @if (session('success'))
            <div class="se-soft-card border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800" role="status">
                {{ session('success') }}
            </div>
        @endif

        <section class="se-hero">
            <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Docentes</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Capacitación docente</h2>
                    <p class="text-sm text-white/80">{{ schoolCtx()->nivelNombre() }} · Cursos realizados y certificados</p>
                </div>
                @if ($tablasOk)
                    <div class="flex flex-wrap gap-2">
                        <button type="button"
                                wire:click="abrirNuevo"
                                class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-50">
                            Registrar Nueva Capacitación Docente
                        </button>
                    </div>
                @endif
            </div>
        </section>

        @if (! $tablasOk)
            <div class="se-card px-5 py-8 text-center text-sm text-neutral-600">
                {{ $mensajeTabla }}
            </div>
        @else
            <div class="flex flex-wrap gap-2">
                <button type="button"
                        wire:click="$set('vista', 'listado')"
                        @class([
                            'rounded-xl px-4 py-2 text-sm font-semibold transition',
                            'bg-primary-600 text-white shadow-sm' => $vista === 'listado',
                            'bg-white text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50' => $vista !== 'listado',
                        ])>
                    Listado de cursos
                </button>
                <button type="button"
                        wire:click="$set('vista', 'resumen')"
                        @class([
                            'rounded-xl px-4 py-2 text-sm font-semibold transition',
                            'bg-primary-600 text-white shadow-sm' => $vista === 'resumen',
                            'bg-white text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50' => $vista !== 'resumen',
                        ])>
                    Resumen {{ $anioActual }}
                </button>
            </div>

            @if ($vista === 'listado')
                <div class="se-toolbar">
                    <div class="relative min-w-0 flex-1 sm:max-w-xs">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                        <input type="search"
                               wire:model.live.debounce.400ms="buscar"
                               placeholder="Curso o entidad…"
                               class="form-input w-full pl-9"
                               autocomplete="off"
                               aria-label="Buscar curso">
                    </div>
                    <div class="min-w-0 flex-1 sm:max-w-xs">
                        <select wire:model.live="filtroProfesor" class="form-input w-full" aria-label="Filtrar por docente">
                            <option value="">Todos los docentes</option>
                            @foreach ($profesores as $p)
                                <option value="{{ $p->id }}">{{ $p->apellido }}, {{ $p->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="shrink-0 text-[11px] font-medium tabular-nums text-neutral-500">
                        {{ $registros->total() }} registros
                    </p>
                </div>

                <div class="se-card overflow-hidden">
                    <div class="w-full overflow-x-auto">
                        <div class="gf gf-capacitacion-docente min-w-[56rem]">
                            <div class="gf-head">
                                <div class="gf-th w-24">Fecha</div>
                                <div class="gf-th min-w-[9rem] flex-1">Docente</div>
                                <div class="gf-th min-w-[10rem] flex-1">Curso</div>
                                <div class="gf-th min-w-[8rem] flex-1">Entidad</div>
                                <div class="gf-th w-24">Duración</div>
                                <div class="gf-th w-24">Modalidad</div>
                                <div class="gf-th-right w-44">Acciones</div>
                            </div>
                            @forelse ($registros as $r)
                                <div class="gf-row gf-row-hover" wire:key="cap-doc-{{ $r->id }}">
                                    <div class="gf-td w-24 tabular-nums">{{ $r->fecha?->format('d/m/Y') ?? '—' }}</div>
                                    <div class="gf-td min-w-[9rem] flex-1 font-semibold text-neutral-900">
                                        {{ $r->profesor ? ($r->profesor->apellido.', '.$r->profesor->nombre) : '—' }}
                                    </div>
                                    <div class="gf-td min-w-[10rem] flex-1">{{ $r->nombre }}</div>
                                    <div class="gf-td min-w-[8rem] flex-1 text-neutral-700">{{ $r->entidad_otorgante }}</div>
                                    <div class="gf-td w-24">{{ $r->duracion }}</div>
                                    <div class="gf-td w-24">
                                        <span class="se-pill">{{ $r->etiquetaModalidad() }}</span>
                                    </div>
                                    <div class="gf-td w-44 flex flex-wrap items-center justify-end gap-1.5">
                                        @if ($r->tieneCertificado())
                                            <a href="{{ $this->urlCertificado((int) $r->id) }}"
                                               target="_blank"
                                               rel="noopener"
                                               class="inline-flex items-center rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-primary-700 ring-1 ring-accent-200 transition hover:bg-accent-50">
                                                PDF
                                            </a>
                                        @endif
                                        <button type="button"
                                                wire:click="abrirEditar({{ (int) $r->id }})"
                                                class="inline-flex items-center rounded-lg bg-primary-600 px-2 py-1 text-[11px] font-semibold text-white shadow-sm transition hover:bg-primary-700">
                                            Editar
                                        </button>
                                        <button type="button"
                                                x-on:click="seSwalConfirmar('¿Eliminar este curso de capacitación?', 'Confirmar').then(ok => ok && $wire.eliminar({{ (int) $r->id }}))"
                                                class="inline-flex items-center rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-red-700 ring-1 ring-red-200 transition hover:bg-red-50">
                                            Eliminar
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="px-5 py-12 text-center text-sm text-neutral-500">
                                    No hay cursos que coincidan con los filtros.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    @if ($registros->hasPages())
                        <div class="se-matriz-list-footer">
                            {{ $registros->links('vendor.pagination.se-compact') }}
                        </div>
                    @endif
                </div>
            @else
                <div class="se-toolbar se-matriz-list-toolbar--angosta se-toolbar-pocos-campos">
                    <p class="text-sm text-neutral-600">
                        Cantidad de cursos por docente en <span class="font-semibold text-neutral-800">{{ $anioActual }}</span>
                    </p>
                    <span class="se-pill tabular-nums">Total nivel: {{ $totalAnio }}</span>
                </div>

                <div class="se-card overflow-hidden">
                    <div class="w-full overflow-x-auto se-grid-angosta-wrap">
                        <table class="se-matriz-list-tabla se-grid-pocos-campos">
                            <thead>
                                <tr>
                                    <th scope="col" class="text-left">Docente</th>
                                    <th scope="col" class="text-right">Cursos {{ $anioActual }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($resumen as $fila)
                                    <tr wire:key="cap-res-{{ $fila['id_profesor'] }}">
                                        <td class="font-semibold text-neutral-900">{{ $fila['apellido'] }}, {{ $fila['nombre'] }}</td>
                                        <td class="text-right tabular-nums font-semibold text-primary-700">{{ $fila['cantidad'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="py-8 text-center text-xs text-neutral-500">
                                            No hay cursos registrados en {{ $anioActual }} para este nivel.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    </div>

    @teleport('body')
        <div>
            @if ($modalAbierto)
                <div class="fixed inset-0 z-[1100] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                     role="dialog"
                     aria-modal="true"
                     aria-labelledby="cap-doc-modal-titulo"
                     wire:key="cap-doc-modal">
                    <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModal"></div>
                    <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),40rem)]">
                        <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                            <h3 id="cap-doc-modal-titulo" class="text-lg font-bold text-neutral-900">
                                {{ $editId ? 'Editar capacitación' : 'Nueva capacitación' }}
                            </h3>
                        </div>
                        <form wire:submit.prevent="guardar" class="flex min-h-0 flex-1 flex-col">
                            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                                <div>
                                    <label class="form-label" for="cap-id-profesor">Docente *</label>
                                    <select id="cap-id-profesor" wire:model="id_profesor" class="form-input mt-1 w-full">
                                        <option value="">— Seleccionar —</option>
                                        @foreach ($profesores as $p)
                                            <option value="{{ $p->id }}">{{ $p->apellido }}, {{ $p->nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error('id_profesor') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label" for="cap-fecha">Fecha *</label>
                                    <input id="cap-fecha" type="date" wire:model="fecha" class="form-input mt-1 w-full max-w-[12rem]">
                                    @error('fecha') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label" for="cap-nombre">Nombre del curso *</label>
                                    <input id="cap-nombre" type="text" wire:model="nombre" class="form-input mt-1 w-full" maxlength="255" autocomplete="off">
                                    @error('nombre') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label" for="cap-entidad">Entidad otorgante *</label>
                                    <input id="cap-entidad" type="text" wire:model="entidad_otorgante" class="form-input mt-1 w-full" maxlength="255" autocomplete="off">
                                    @error('entidad_otorgante') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label" for="cap-duracion">Duración *</label>
                                    <input id="cap-duracion" type="text" wire:model="duracion" class="form-input mt-1 w-full max-w-xs" maxlength="80" placeholder="Ej. 8 horas" autocomplete="off">
                                    @error('duracion') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label" for="cap-modalidad">Modalidad *</label>
                                    <select id="cap-modalidad" wire:model="modalidad" class="form-input mt-1 w-full max-w-xs">
                                        @foreach ($modalidades as $valor => $etiqueta)
                                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                    @error('modalidad') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label" for="cap-pdf">Certificado (PDF)</label>
                                    <input id="cap-pdf" type="file" wire:model="certificadoPdf" accept="application/pdf,.pdf" class="form-input mt-1 w-full text-sm">
                                    <div wire:loading wire:target="certificadoPdf" class="mt-1 text-xs font-medium text-primary-700">
                                        Subiendo archivo… el botón Guardar permanecerá bloqueado hasta que termine.
                                    </div>
                                    @error('certificadoPdf') <p class="form-error">{{ $message }}</p> @enderror
                                    @if ($tieneCertificado && ! $certificadoPdf)
                                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-neutral-600">
                                            <input type="checkbox" wire:model="quitarCertificado" class="rounded border-accent-200 text-primary-600 focus:ring-primary-500">
                                            Quitar certificado actual
                                        </label>
                                    @endif
                                </div>
                            </div>
                            <div class="shrink-0 flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50 px-5 py-3">
                                <button type="button"
                                        wire:click="cerrarModal"
                                        wire:loading.attr="disabled"
                                        wire:target="guardar,certificadoPdf"
                                        class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-primary-700 ring-1 ring-accent-200 transition hover:bg-white disabled:opacity-60">
                                    Cancelar
                                </button>
                                <button type="submit"
                                        wire:loading.attr="disabled"
                                        wire:target="guardar,certificadoPdf"
                                        class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60">
                                    <span wire:loading.remove wire:target="guardar,certificadoPdf">Guardar</span>
                                    <span wire:loading wire:target="guardar">Guardando…</span>
                                    <span wire:loading wire:target="certificadoPdf">Subiendo PDF…</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    @endteleport

    @script
    <script>
        $wire.on('se-swal-exito', (e) => {
            const msg = (e && e.mensaje) ? e.mensaje : 'Listo.';
            if (typeof window.seSwalExito === 'function') window.seSwalExito(msg);
        });
        $wire.on('se-swal-error', (e) => {
            const msg = (e && e.mensaje) ? e.mensaje : 'Error.';
            if (typeof window.seSwalError === 'function') window.seSwalError(msg);
        });
    </script>
    @endscript
</div>
