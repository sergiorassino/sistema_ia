<div>
    <div class="se-page max-w-6xl mx-auto">

        <section class="se-hero mb-6">
            <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Material Didáctico</p>
                    <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Gestión de recursos</h1>
                    <p class="text-sm text-white/80">{{ schoolCtx()->nivelNombre() }}</p>
                </div>
                <a href="{{ route('material-didactico.index') }}"
                   class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white/20 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/30">
                    ← Listado de reservas
                </a>
            </div>
        </section>

        {{-- Tabs de panel --}}
        <div class="mb-5 flex gap-2 flex-wrap">
            <button type="button"
                    wire:click="$set('panel', 'grupos')"
                    @class(['px-4 py-2 rounded-xl text-sm font-semibold transition',
                        'bg-primary-600 text-white shadow' => $panel === 'grupos',
                        'bg-white border border-accent-200 text-primary-700 hover:border-primary-400' => $panel !== 'grupos'])>
                Grupos
            </button>
            <button type="button"
                    wire:click="$set('panel', 'recursos')"
                    @class(['px-4 py-2 rounded-xl text-sm font-semibold transition',
                        'bg-primary-600 text-white shadow' => $panel === 'recursos',
                        'bg-white border border-accent-200 text-primary-700 hover:border-primary-400' => $panel !== 'recursos'])>
                Recursos
            </button>
            @if($panel === 'disponibilidad' && $recursoActual)
                <button type="button"
                        @class(['px-4 py-2 rounded-xl text-sm font-semibold transition bg-primary-600 text-white shadow'])>
                    Disponibilidad: {{ $recursoActual->nombre }}
                </button>
            @endif
        </div>

        {{-- ============================================================ --}}
        {{-- PANEL GRUPOS                                                   --}}
        {{-- ============================================================ --}}
        @if($panel === 'grupos')
            <div class="se-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-accent-200 px-4 py-3">
                    <p class="se-section-title">Grupos de recursos</p>
                    <button type="button" wire:click="abrirFormGrupo()" class="btn-primary btn-sm">+ Nuevo grupo</button>
                </div>
                <div class="w-full overflow-x-auto p-2 sm:p-3 se-grid-angosta-wrap">
                    <div class="gf min-w-[36rem]">
                        <div class="gf-head">
                            <div class="gf-th w-10 shrink-0">Ord.</div>
                            <div class="gf-th w-64 shrink-0">Nombre</div>
                            <div class="gf-th w-16 shrink-0 text-center">Activo</div>
                            <div class="gf-th w-20 shrink-0 text-center">Recursos</div>
                            <div class="gf-th w-52 shrink-0 text-center">Acciones</div>
                        </div>
                        @forelse($grupos as $grupo)
                            <div class="gf-row gf-row-hover" wire:key="grupo-{{ $grupo->id }}">
                                <div class="gf-td w-10 shrink-0 tabular-nums text-neutral-400">{{ $grupo->orden }}</div>
                                <div class="gf-td w-64 shrink-0 font-medium break-words">{{ $grupo->nombre }}</div>
                                <div class="gf-td w-16 shrink-0 text-center">
                                    @if($grupo->activo)
                                        <span class="se-pill bg-green-100 text-green-700">Sí</span>
                                    @else
                                        <span class="se-pill bg-neutral-100 text-neutral-500">No</span>
                                    @endif
                                </div>
                                <div class="gf-td w-20 shrink-0 text-center">
                                    <button type="button"
                                            wire:click="verRecursosDeGrupo({{ $grupo->id }})"
                                            class="text-primary-600 hover:underline text-sm font-medium">
                                        {{ $grupo->recursos_count }}
                                    </button>
                                </div>
                                <div class="gf-td-actions w-52 shrink-0 flex-nowrap justify-center whitespace-nowrap">
                                    <button type="button" wire:click="abrirFormGrupo({{ $grupo->id }})" class="btn-secondary btn-sm">Editar</button>
                                    <button type="button"
                                            x-on:click="seSwalConfirmar(@js('¿Eliminar este grupo?')).then(ok => ok && $wire.eliminarGrupo({{ $grupo->id }}))"
                                            class="btn-danger btn-sm">
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="gf-empty">No hay grupos configurados para este nivel.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        {{-- ============================================================ --}}
        {{-- PANEL RECURSOS                                                --}}
        {{-- ============================================================ --}}
        @if($panel === 'recursos')
            <div class="se-card overflow-hidden">
                <div class="flex items-center justify-between gap-3 flex-wrap border-b border-accent-200 px-4 py-3">
                    <div class="flex items-center gap-3">
                        <p class="se-section-title">Recursos</p>
                        <select wire:model.live="filtroGrupoId" class="form-input w-56">
                            <option value="">— Todos los grupos —</option>
                            @foreach($gruposFiltro as $g)
                                <option value="{{ $g->id }}">{{ $g->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" wire:click="abrirFormRecurso()" class="btn-primary btn-sm">+ Nuevo recurso</button>
                </div>
                <div class="w-full overflow-x-auto p-2 sm:p-3 se-grid-angosta-wrap">
                    <div class="gf min-w-[64rem]">
                        <div class="gf-head">
                            <div class="gf-th w-10 shrink-0">Ord.</div>
                            <div class="gf-th w-44 shrink-0">Nombre</div>
                            <div class="gf-th w-32 shrink-0">Grupo</div>
                            <div class="gf-th w-28 shrink-0">Antelación</div>
                            <div class="gf-th w-28 shrink-0 text-center">Disponibilidad</div>
                            <div class="gf-th w-16 shrink-0 text-center">Activo</div>
                            <div class="gf-th w-72 shrink-0 text-center">Acciones</div>
                        </div>
                        @forelse($recursos as $recurso)
                            <div class="gf-row gf-row-hover" wire:key="recurso-{{ $recurso->id }}">
                                <div class="gf-td w-10 shrink-0 tabular-nums text-neutral-400">{{ $recurso->orden }}</div>
                                <div class="gf-td w-44 shrink-0 font-medium break-words">{{ $recurso->nombre }}</div>
                                <div class="gf-td w-32 shrink-0 text-xs text-neutral-500 break-words">{{ $recurso->grupo?->nombre }}</div>
                                <div class="gf-td w-28 shrink-0 text-xs">
                                    @if($recurso->antelacion_min_horas > 0)
                                        <span class="se-pill bg-yellow-100 text-yellow-700">{{ $recurso->antelacion_min_horas }}h mín.</span>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </div>
                                <div class="gf-td w-28 shrink-0 text-center">
                                    @if($recurso->siempre_disponible)
                                        <span class="se-pill bg-primary-100 text-primary-700">Siempre</span>
                                    @else
                                        <span class="se-pill bg-neutral-100 text-neutral-500">Por horario</span>
                                    @endif
                                </div>
                                <div class="gf-td w-16 shrink-0 text-center">
                                    @if($recurso->activo)
                                        <span class="se-pill bg-green-100 text-green-700">Sí</span>
                                    @else
                                        <span class="se-pill bg-neutral-100 text-neutral-500">No</span>
                                    @endif
                                </div>
                                <div class="gf-td-actions w-72 shrink-0 flex-nowrap justify-center whitespace-nowrap">
                                    @if(!$recurso->siempre_disponible)
                                        <button type="button" wire:click="verDisponibilidad({{ $recurso->id }})" class="btn-secondary btn-sm">Horarios</button>
                                    @endif
                                    <button type="button" wire:click="abrirFormRecurso({{ $recurso->id }})" class="btn-secondary btn-sm">Editar</button>
                                    <button type="button"
                                            x-on:click="seSwalConfirmar(@js('¿Eliminar este recurso?')).then(ok => ok && $wire.eliminarRecurso({{ $recurso->id }}))"
                                            class="btn-danger btn-sm">
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="gf-empty">No hay recursos en este grupo.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        {{-- ============================================================ --}}
        {{-- PANEL DISPONIBILIDAD                                          --}}
        {{-- ============================================================ --}}
        @if($panel === 'disponibilidad' && $recursoActual)
            <div class="se-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-accent-200 px-4 py-3">
                    <div>
                        <p class="se-section-title">Ventanas de disponibilidad</p>
                        <p class="text-xs text-neutral-500 mt-0.5">Recurso: <strong>{{ $recursoActual->nombre }}</strong> · {{ $recursoActual->grupo?->nombre }}</p>
                    </div>
                    <button type="button" wire:click="abrirFormDisp()" class="btn-primary btn-sm">+ Agregar ventana</button>
                </div>
                <div class="p-2 sm:p-3">
                    @if($recursoActual && $recursoActual->siempre_disponible)
                        <div class="mb-3 flex items-start gap-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm text-primary-800">
                                Este recurso está marcado como <strong>siempre disponible</strong>: las ventanas de disponibilidad no se aplican. Para activarlas, desmarque esa opción en la configuración del recurso.
                            </p>
                        </div>
                    @endif
                    @if($disponibilidades->isEmpty())
                        <p class="py-6 text-center text-sm text-neutral-400">
                            @if($recursoActual && $recursoActual->siempre_disponible)
                                No hay ventanas configuradas (no son necesarias con "siempre disponible" activo).
                            @else
                                Sin ventanas de disponibilidad. El recurso no podrá reservarse hasta configurar al menos una.
                            @endif
                        </p>
                    @else
                        <div class="space-y-2">
                            @foreach($disponibilidades as $disp)
                                <div class="flex items-center gap-3 rounded-xl border border-accent-200 px-4 py-3" wire:key="disp-{{ $disp->id }}">
                                    <div class="w-24 font-medium text-sm">{{ $disp->nombreDia() }}</div>
                                    <div class="tabular-nums text-sm">{{ substr($disp->hora_inicio, 0, 5) }} – {{ substr($disp->hora_fin, 0, 5) }}</div>
                                    <div class="ml-auto flex gap-2">
                                        <button type="button" wire:click="abrirFormDisp({{ $disp->id }})" class="btn-secondary btn-sm">Editar</button>
                                        <button type="button"
                                                x-on:click="seSwalConfirmar(@js('¿Eliminar esta ventana?')).then(ok => ok && $wire.eliminarDisp({{ $disp->id }}))"
                                                class="btn-danger btn-sm">
                                            Eliminar
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif

    </div>

    {{-- Modales --}}
    @teleport('body')
    <div>
        {{-- Modal Grupo --}}
        @if($mostrarFormGrupo)
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3"
                 role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarFormGrupo"></div>
                <div class="relative z-10 my-auto flex w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                    <div class="flex items-center justify-between border-b border-accent-200 px-5 py-4">
                        <h2 class="text-base font-semibold text-neutral-800">{{ $grupoEditId ? 'Editar grupo' : 'Nuevo grupo' }}</h2>
                        <button type="button" wire:click="cerrarFormGrupo" class="text-neutral-400 hover:text-neutral-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="px-5 py-4 space-y-4">
                        <div>
                            <label class="form-label">Nombre <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="grupoNombre" maxlength="120" class="form-input mt-1.5">
                            @error('grupoNombre') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Orden</label>
                            <input type="number" wire:model="grupoOrden" min="0" class="form-input mt-1.5 w-24">
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="checkbox" id="grupoActivo" wire:model="grupoActivo" class="h-4 w-4 rounded border-neutral-300 text-primary-600">
                            <label for="grupoActivo" class="text-sm font-medium text-neutral-700">Activo</label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50 px-5 py-4">
                        <button type="button" wire:click="cerrarFormGrupo" class="btn-secondary">Cancelar</button>
                        <button type="button" wire:click="guardarGrupo" class="btn-primary">Guardar</button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal Recurso --}}
        @if($mostrarFormRecurso)
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3"
                 role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarFormRecurso"></div>
                <div class="relative z-10 my-auto flex w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                    <div class="flex items-center justify-between border-b border-accent-200 px-5 py-4">
                        <h2 class="text-base font-semibold text-neutral-800">{{ $recursoEditId ? 'Editar recurso' : 'Nuevo recurso' }}</h2>
                        <button type="button" wire:click="cerrarFormRecurso" class="text-neutral-400 hover:text-neutral-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="px-5 py-4 space-y-4">
                        <div>
                            <label class="form-label">Nombre <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="recursoNombre" maxlength="120" class="form-input mt-1.5">
                            @error('recursoNombre') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Grupo <span class="text-red-500">*</span></label>
                            <select wire:model="recursoGrupoId" class="form-input mt-1.5">
                                <option value="0">— Seleccione —</option>
                                @foreach($gruposFiltro as $g)
                                    <option value="{{ $g->id }}">{{ $g->nombre }}</option>
                                @endforeach
                            </select>
                            @error('recursoGrupoId') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Antelación mínima (horas)</label>
                            <input type="number" wire:model="recursoAntelacion" min="0" class="form-input mt-1.5 w-28">
                            <p class="mt-1 text-[11px] text-neutral-400">0 = sin restricción de antelación</p>
                        </div>
                        <div>
                            <label class="form-label">Orden</label>
                            <input type="number" wire:model="recursoOrden" min="0" class="form-input mt-1.5 w-24">
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="checkbox" id="recursoActivo" wire:model="recursoActivo" class="h-4 w-4 rounded border-neutral-300 text-primary-600">
                            <label for="recursoActivo" class="text-sm font-medium text-neutral-700">Activo</label>
                        </div>
                        <div class="rounded-xl border border-primary-200 bg-primary-50 p-3 space-y-1">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" id="recursoSiempreDisponible" wire:model.live="recursoSiempreDisponible" class="h-4 w-4 rounded border-neutral-300 text-primary-600">
                                <label for="recursoSiempreDisponible" class="text-sm font-medium text-primary-800">Siempre disponible</label>
                            </div>
                            <p class="text-[11px] text-primary-600 leading-relaxed">
                                Al activar esta opción, el recurso podrá reservarse en cualquier horario sin necesidad de configurar ventanas de disponibilidad.
                            </p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50 px-5 py-4">
                        <button type="button" wire:click="cerrarFormRecurso" class="btn-secondary">Cancelar</button>
                        <button type="button" wire:click="guardarRecurso" class="btn-primary">Guardar</button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal Disponibilidad --}}
        @if($mostrarFormDisp)
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3"
                 role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarFormDisp"></div>
                <div class="relative z-10 my-auto flex w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                    <div class="flex items-center justify-between border-b border-accent-200 px-5 py-4">
                        <h2 class="text-base font-semibold text-neutral-800">{{ $dispEditId ? 'Editar ventana' : 'Nueva ventana de disponibilidad' }}</h2>
                        <button type="button" wire:click="cerrarFormDisp" class="text-neutral-400 hover:text-neutral-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="px-5 py-4 space-y-4">
                        <div>
                            <label class="form-label">Día de la semana <span class="text-red-500">*</span></label>
                            <select wire:model="dispDia" class="form-input mt-1.5">
                                @foreach($dias as $num => $nombre)
                                    <option value="{{ $num }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                            @error('dispDia') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex gap-4">
                            <div class="flex-1">
                                <label class="form-label">Hora inicio <span class="text-red-500">*</span></label>
                                <input type="time" wire:model="dispHoraInicio" class="form-input mt-1.5">
                                @error('dispHoraInicio') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex-1">
                                <label class="form-label">Hora fin <span class="text-red-500">*</span></label>
                                <input type="time" wire:model="dispHoraFin" class="form-input mt-1.5">
                                @error('dispHoraFin') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50 px-5 py-4">
                        <button type="button" wire:click="cerrarFormDisp" class="btn-secondary">Cancelar</button>
                        <button type="button" wire:click="guardarDisp" class="btn-primary">Guardar</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
    @endteleport

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje }) => {
            if (typeof seSwalExito === 'function') {
                seSwalExito(mensaje ?? 'Operación completada.');
            }
        });
        $wire.on('se-swal-error', ({ mensaje }) => {
            if (typeof seSwalError === 'function') {
                seSwalError(mensaje ?? 'No se pudo completar la operación.');
            }
        });
    </script>
    @endscript
</div>
