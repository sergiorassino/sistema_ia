<div class="se-page max-w-4xl">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Seguimiento</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Registro múltiple</h2>
                <p class="text-sm text-white/75">La misma sanción se aplica a cada estudiante marcado. Los campos con * son obligatorios.</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('seguimiento.disciplinario') }}"
                   class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                    Cancelar
                </a>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100 disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">Guardar</span>
                    <span wire:loading wire:target="save">Guardando…</span>
                </button>
            </div>
        </div>
    </section>

    <div class="se-card overflow-hidden p-6 sm:p-7">
        <div class="max-w-xl">
            <label for="se-disc-mult-curso" class="form-label">Curso *</label>
            <select id="se-disc-mult-curso" wire:model.live="idCurso" class="form-select mt-1.5 @error('idCurso') border-red-400 @enderror">
                <option value="">— Seleccione —</option>
                @foreach ($cursos as $c)
                    <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                @endforeach
            </select>
            @error('idCurso') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>

    @if ($idCurso)
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50 px-5 py-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Estudiantes del curso</p>
                <p class="mt-1 text-sm text-neutral-600">
                    Marque uno, varios o todos. Cada uno recibe su propio registro.
                </p>
            </div>

            @if ($hayAlumnos)
                <div class="se-toolbar-pocos-campos border-b border-accent-100 bg-white px-5 py-3">
                    <button type="button"
                            wire:click="seleccionarTodasMatriculas"
                            class="rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-300 hover:bg-accent-50">
                        Marcar todos
                    </button>
                    <button type="button"
                            wire:click="quitarTodasMatriculas"
                            class="rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-600 shadow-sm transition hover:border-accent-300 hover:bg-accent-50">
                        Desmarcar todos
                    </button>
                    @if ($cantidadSeleccionados > 0)
                        <span class="se-pill">{{ $cantidadSeleccionados }} seleccionado{{ $cantidadSeleccionados === 1 ? '' : 's' }}</span>
                    @endif
                </div>
            @endif

            <div class="max-h-72 w-full overflow-auto px-4 pb-4 pt-1 se-grid-angosta-wrap">
                <table class="se-grid-pocos-campos w-auto table-auto divide-y divide-accent-200 text-sm">
                    <thead class="bg-white">
                        <tr>
                            <th scope="col" class="w-10 py-2 text-center">
                                @if ($hayAlumnos)
                                    <input type="checkbox"
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                           title="Marcar o desmarcar todos"
                                           @checked($todasMarcadas)
                                           wire:click="toggleSeleccionTodas">
                                @endif
                            </th>
                            <th scope="col" class="py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Apellido y nombre</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100 bg-white">
                        @forelse ($alumnos as $a)
                            <tr class="hover:bg-accent-50/60" wire:key="disc-mult-mat-{{ $a->id }}">
                                <td class="py-2 text-center align-middle">
                                    <input type="checkbox"
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                           wire:model.live="matriculasSeleccionadas"
                                           value="{{ $a->id }}">
                                </td>
                                <td class="py-2 align-middle font-medium text-neutral-800">
                                    {{ trim(($a->apellido ?? '').', '.($a->nombre ?? '')) }}
                                    @if ($a->dni)
                                        <span class="text-xs font-normal text-neutral-500">· DNI {{ $a->dni }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-5 py-10 text-center text-sm text-neutral-500">
                                    No hay alumnos con condición 1 a 4 para ese curso en el año actual.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @error('matriculasSeleccionadas')
            <p class="form-error">{{ $message }}</p>
        @enderror
    @endif

    <div class="se-card overflow-hidden p-6 sm:p-7">
        @if ($cantidadSeleccionados > 0)
            <div class="mb-6 rounded-2xl border border-accent-200 bg-accent-50/50 px-4 py-3">
                <p class="text-sm font-semibold text-neutral-900">
                    Se aplicará a {{ $cantidadSeleccionados }} estudiante{{ $cantidadSeleccionados === 1 ? '' : 's' }}
                </p>
                <p class="mt-0.5 text-xs text-neutral-600">
                    Comunicado, acta y notificación quedan independientes en cada registro.
                </p>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="form-label">Tipo de registro *</label>
                <select wire:model.live="idTipoSancion" class="form-select mt-1.5 @error('idTipoSancion') border-red-400 @enderror">
                    <option value="">— Seleccione —</option>
                    @foreach ($tipos as $t)
                        <option value="{{ $t->id }}">{{ $t->tipo }}</option>
                    @endforeach
                </select>
                @error('idTipoSancion') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Fecha *</label>
                <input wire:model="fecha" type="date" class="form-input mt-1.5 @error('fecha') border-red-400 @enderror">
                @error('fecha') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Fecha de registro</label>
                <input type="text"
                       value="{{ $this->fechaRegistroMostrar }}"
                       readonly
                       tabindex="-1"
                       aria-readonly="true"
                       class="form-input mt-1.5 bg-accent-50 text-neutral-700">
                @error('fechaRegistroMostrar') <p class="form-error">{{ $message }}</p> @enderror
                <p class="mt-1.5 text-xs text-neutral-500">Se guarda automáticamente al crear el registro. No es la fecha del hecho.</p>
            </div>

            <div>
                <label class="form-label">Cantidad</label>
                <input wire:model="cantidad"
                       type="text"
                       inputmode="numeric"
                       maxlength="2"
                       class="form-input mt-1.5 @error('cantidad') border-red-400 @enderror">
                @error('cantidad') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Motivo</label>
                <textarea wire:model="motivo" rows="4" class="form-input mt-1.5 resize-y leading-relaxed @error('motivo') border-red-400 @enderror"></textarea>
                @error('motivo') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Solicitada por</label>
                <input wire:model="solipor" type="text" maxlength="150" class="form-input mt-1.5 @error('solipor') border-red-400 @enderror">
                @error('solipor') <p class="form-error">{{ $message }}</p> @enderror
                <p class="mt-1.5 text-xs text-neutral-500">Si se deja vacío, se toma el profesor del contexto actual.</p>
            </div>
        </div>
    </div>
</div>

@script
<script>
    $wire.on('se-swal-error', (e) => { window.seSwalError?.(e.mensaje ?? e[0]?.mensaje ?? ''); });
</script>
@endscript
