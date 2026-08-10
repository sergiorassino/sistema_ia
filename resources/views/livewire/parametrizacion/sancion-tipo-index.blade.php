<div>
<div class="se-page max-w-5xl">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Configuración</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Tipos de sanción disciplinaria</h2>
                <p class="text-sm text-white/80">
                    Editá cada tipo para configurar el texto de notificación a padres, el remitente y si requiere refuerzo por correo.
                </p>
            </div>
            <div></div>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="w-full overflow-x-auto se-grid-angosta-wrap">
            <div class="gf min-w-[40rem]">
                <div class="gf-head">
                    <div class="gf-th flex-1 min-w-[10rem]">Nombre</div>
                    <div class="gf-th w-28 text-center">Notif. padres</div>
                    <div class="gf-th w-36 text-center">Remitente notif.</div>
                    <div class="gf-th w-28 text-center">Texto config.</div>
                    <div class="gf-th w-28 text-center">Refuerzo mail</div>
                    <div class="gf-th-right w-36">Acciones</div>
                </div>

                @forelse ($tipos as $st)
                    <div class="gf-row gf-row-hover" wire:key="st-{{ $st->id }}">
                        <div class="gf-td flex-1 min-w-[10rem] font-medium">{{ $st->tipo }}</div>
                        <div class="gf-td w-28 text-center">
                            @if ($st->permiteNotifPadres ?? true)
                                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-green-200">Sí</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-500 ring-1 ring-neutral-200">No</span>
                            @endif
                        </div>
                        <div class="gf-td w-36 text-center text-xs">
                            @if ($st->idProfesorNotif)
                                <span class="inline-flex items-center rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-primary-200">
                                    {{ $st->profesorNotif?->nombre_completo ?? 'ID '.$st->idProfesorNotif }}
                                </span>
                            @else
                                <span class="text-neutral-400">—</span>
                            @endif
                        </div>
                        <div class="gf-td w-28 text-center">
                            @if (trim((string) $st->textoNotifPadres) !== '')
                                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-green-200">Sí</span>
                            @else
                                <span class="text-neutral-400 text-xs">—</span>
                            @endif
                        </div>
                        <div class="gf-td w-28 text-center">
                            @if ($st->refuerzoMail)
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-blue-200">Mail</span>
                            @else
                                <span class="text-neutral-400 text-xs">Solo push</span>
                            @endif
                        </div>
                        <div class="gf-td-right w-36 flex items-center justify-end gap-1">
                            <button type="button"
                                    wire:click="openEdit({{ $st->id }})"
                                    class="rounded-lg border border-accent-200 bg-white px-2.5 py-1 text-xs font-semibold text-primary-700 hover:bg-accent-50">
                                Editar
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-12 text-center text-sm text-neutral-500">
                        No hay tipos de sanción cargados. Usá el botón «+ Nuevo tipo» para comenzar.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@teleport('body')
<div>
    @if ($showModal)
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
             role="dialog" aria-modal="true" aria-labelledby="st-modal-titulo">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
            <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                <div class="shrink-0 border-b border-accent-200 px-5 py-3">
                    <p id="st-modal-titulo" class="text-sm font-bold text-neutral-900">
                        Editar tipo de sanción
                    </p>
                </div>
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">

                    <div>
                        <label for="st-tipo" class="form-label">Nombre del tipo *</label>
                        <input id="st-tipo" type="text" wire:model="tipo" maxlength="120"
                               class="form-input mt-1.5 @error('tipo') border-red-400 @enderror" />
                        @error('tipo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="st-texto" class="form-label">Texto inicial del mensaje a padres</label>
                        <textarea id="st-texto" wire:model="textoNotifPadres" rows="4" maxlength="2000"
                                  class="form-input mt-1.5 resize-y leading-relaxed @error('textoNotifPadres') border-red-400 @enderror"
                                  placeholder="Ej: Por medio del presente comunicamos que su hijo/a ha recibido la siguiente sanción…"></textarea>
                        <p class="mt-1 text-xs text-neutral-500">Al enviarse, se agregan automáticamente: fecha, tipo, cantidad, motivo y solicitada por.</p>
                        @error('textoNotifPadres') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="st-profesor" class="form-label">Remitente fijo (profesor)</label>
                        <select id="st-profesor" wire:model="idProfesorNotif"
                                class="form-select mt-1.5 @error('idProfesorNotif') border-red-400 @enderror">
                            <option value="">— Sin remitente fijo —</option>
                            @foreach ($profesores as $p)
                                <option value="{{ $p->id }}">{{ $p->nombre_completo }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-neutral-500">Si no se configura, el botón «Notif. Padres» no estará disponible para este tipo.</p>
                        @error('idProfesorNotif') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="rounded-2xl border border-accent-200 bg-accent-50/50 px-4 py-3 space-y-3">
                        <div class="flex items-center gap-3">
                            <input id="st-permite" type="checkbox" wire:model.live="permiteNotifPadres"
                                   class="h-4 w-4 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                            <label for="st-permite" class="text-sm font-medium text-neutral-700">
                                Permite notificación a padres
                            </label>
                        </div>
                        <p class="-mt-1 text-xs text-neutral-500">
                            Si está desactivado, el botón «Notif. Padres» no estará disponible para sanciones de este tipo.
                        </p>

                        <div x-show="$wire.permiteNotifPadres" class="border-t border-accent-200 pt-3 space-y-3">
                            <div class="flex items-center gap-3">
                                <input id="st-mail" type="checkbox" wire:model="refuerzoMail"
                                       class="h-4 w-4 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                                <label for="st-mail" class="text-sm font-medium text-neutral-700">
                                    Refuerzo por correo electrónico
                                </label>
                            </div>
                            <p class="-mt-1 text-xs text-neutral-500">
                                La notificación siempre incluye push (si la familia tiene dispositivos vinculados). Activá esta opción para agregar también el envío por mail.
                                Además, el canal del remitente → Familia debe tener el medio «email» habilitado en Parametrización → Canales de Comunicación.
                            </p>
                        </div>
                    </div>

                </div>
                <div class="flex shrink-0 justify-end gap-2 border-t border-accent-200 bg-accent-50 px-5 py-3">
                    <button type="button" wire:click="$set('showModal', false)"
                            class="rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-800 hover:bg-accent-50">
                        Cancelar
                    </button>
                    <button type="button" wire:click="save" wire:loading.attr="disabled"
                            class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Guardar</span>
                        <span wire:loading wire:target="save">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
@endteleport

@script
<script>
    $wire.on('se-swal-exito', (e) => { window.seSwalExito?.(e.mensaje ?? e[0]?.mensaje ?? ''); });
    $wire.on('se-swal-error', (e) => { window.seSwalError?.(e.mensaje ?? e[0]?.mensaje ?? ''); });
</script>
@endscript
</div>
