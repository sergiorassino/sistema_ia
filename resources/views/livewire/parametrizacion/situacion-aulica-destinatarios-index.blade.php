<div>
    <div class="se-page max-w-4xl">
        <section class="se-hero">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-2">
                    <p class="se-eyebrow">Configuración · {{ schoolCtx()->nivelNombre() }}</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Destinatarios de situación áulica</h2>
                    <p class="max-w-2xl text-sm text-white/80">
                        Reciben el mismo aviso que el preceptor del curso cuando un docente registra una situación áulica.
                    </p>
                </div>
            </div>
        </section>

        @if (! $tablaOk)
            <div class="se-card px-5 py-8 text-center text-sm text-neutral-600">
                Falta la tabla de destinatarios en este colegio. Aplique el SQL del módulo antes de cargar personas.
            </div>
        @else
            <div class="se-card overflow-hidden">
                <div class="se-toolbar-pocos-campos border-b border-accent-100 bg-white px-5 py-4">
                    <div class="min-w-[16rem]">
                        <label for="sa-dest-profesor" class="form-label">Profesor</label>
                        <select id="sa-dest-profesor" wire:model="idProfesor"
                                class="form-select mt-1.5 @error('idProfesor') border-red-400 @enderror">
                            <option value="">Elegir…</option>
                            @foreach ($profesores as $p)
                                <option value="{{ $p->id }}">{{ $p->nombre_completo }}</option>
                            @endforeach
                        </select>
                        @error('idProfesor') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="agregar" wire:loading.attr="disabled"
                            class="btn-primary self-end">
                        <span wire:loading.remove wire:target="agregar">Agregar</span>
                        <span wire:loading wire:target="agregar">Agregando…</span>
                    </button>
                </div>

                <div class="w-full overflow-x-auto se-grid-angosta-wrap px-3 py-4 sm:px-4">
                    <table class="se-grid-pocos-campos w-auto table-auto text-sm">
                        <thead class="border-b border-accent-200 bg-accent-50">
                            <tr>
                                <th class="text-left text-[10px] font-semibold uppercase tracking-wider text-neutral-600">Destinatario</th>
                                <th class="text-right text-[10px] font-semibold uppercase tracking-wider text-neutral-600">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-100">
                            @forelse ($destinatarios as $fila)
                                <tr class="transition-colors hover:bg-accent-50/60" wire:key="sa-dest-{{ $fila->id }}">
                                    <td class="font-medium text-neutral-800">
                                        {{ $fila->profesor?->nombre_completo ?? ('ID '.$fila->idProfesor) }}
                                    </td>
                                    <td class="whitespace-nowrap text-right">
                                        <button type="button"
                                                class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2"
                                                x-on:click="seSwalConfirmar('¿Quitar a esta persona de los avisos?', 'Quitar destinatario').then(ok => ok && $wire.quitar({{ $fila->id }}))">
                                            Quitar
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-10 text-center text-sm text-neutral-500">
                                        No hay destinatarios adicionales. El aviso al preceptor del curso sigue igual.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    @script
    <script>
        $wire.on('se-swal-exito', (e) => { window.seSwalExito?.(e.mensaje ?? e[0]?.mensaje ?? ''); });
        $wire.on('se-swal-error', (e) => { window.seSwalError?.(e.mensaje ?? e[0]?.mensaje ?? ''); });
    </script>
    @endscript
</div>
