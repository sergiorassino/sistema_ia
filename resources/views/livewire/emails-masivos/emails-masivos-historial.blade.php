<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0">
                <p class="se-eyebrow">Correo masivo</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Historial de envíos</h2>
            </div>
            <a href="{{ route('emails-masivos.index') }}" class="rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white">Mensajes escritos</a>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="se-toolbar flex flex-wrap gap-4 border-b border-accent-100 bg-accent-50/40 px-5 py-4">
            <div>
                <label class="form-label">Asunto</label>
                <input type="search" wire:model.live.debounce.400ms="filtroAsunto" class="form-input mt-1 w-64">
            </div>
            <div>
                <label class="form-label">Período</label>
                <select wire:model.live="periodo" class="form-select mt-1 w-44 pr-8">
                    <option value="actual">Ciclo actual</option>
                    <option value="todos">Todos</option>
                </select>
            </div>
        </div>
        <div class="w-full overflow-x-auto">
            <table class="se-matriz-list-tabla min-w-[44rem]">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Remitente</th>
                        <th>Asunto</th>
                        <th class="text-center">Envíos</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campanias as $c)
                        @php $prof = $profesores->get($c->idProfesores); @endphp
                        <tr>
                            <td>{{ $c->fechhora?->format('d/m/Y H:i') }}</td>
                            <td>{{ $prof ? trim($prof->apellido . ', ' . $prof->nombre) : '—' }}</td>
                            <td class="max-w-xs truncate">{{ $c->subject }}</td>
                            <td class="text-center">{{ $c->total_envios }}</td>
                            <td class="text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('emails-masivos.campana', ['id' => $c->id_seed]) }}"
                                       class="rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 hover:bg-accent-50">
                                        Detalle
                                    </a>
                                    @if (tienePermiso(\App\Support\PermisosIaCatalog::EMAILS_MASIVOS_BORRAR))
                                        <button type="button"
                                                x-data
                                                x-on:click="seSwalConfirmar('¿Eliminar este envío del historial? Se borrarán {{ $c->total_envios }} registro(s). Luego podrá eliminar el mensaje escrito si no quedan más envíos.', 'Eliminar envío', { icon: 'warning' }).then(ok => ok && $wire.confirmarEliminarCampana({{ $c->id_seed }}))"
                                                class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">
                                            Borrar
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-sm text-neutral-500">Sin envíos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($campanias->hasPages())
            <div class="border-t border-accent-200 bg-accent-50/70 px-5 py-4">
                {{ $campanias->links('vendor.pagination.se') }}
            </div>
        @endif
    </div>

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje }) => window.seSwalExito(mensaje));
        $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
    </script>
    @endscript
</div>
