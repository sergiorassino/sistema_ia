<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Comunicación institucional</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Correo masivo a estudiantes</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo {{ schoolCtx()->terlecAno() }} · Mensajes escritos
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('emails-masivos.historial') }}"
                   class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/20">
                    Historial de envíos
                </a>
                <a href="{{ route('emails-masivos.form') }}"
                   class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm hover:bg-accent-50">
                    Nuevo mensaje
                </a>
            </div>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white px-5 py-4">
            <p class="se-section-title">Mensajes escritos</p>
            <p class="mt-1 text-sm text-neutral-600">Redacte o edite el contenido aquí; use <strong>Enviar</strong> para elegir destinatarios.@if (tienePermiso(\App\Support\PermisosIaCatalog::EMAILS_MASIVOS_BORRAR)) Solo puede borrar un mensaje escrito si no tiene envíos registrados (elimínelos antes desde el historial).@endif Tope por envío: {{ $maxDestinatarios }} correos en BCC.</p>
        </div>

        <div class="se-toolbar border-b border-accent-100 bg-accent-50/40 px-5 py-4">
            <label class="form-label" for="filtro-asunto-em">Buscar asunto</label>
            <input id="filtro-asunto-em" type="search" wire:model.live.debounce.400ms="filtroAsunto" class="form-input mt-1 w-full max-w-md" placeholder="Filtrar…">
        </div>

        <div class="w-full overflow-x-auto">
            <div class="flex justify-start">
                <table class="se-matriz-list-tabla w-full table-fixed">
                    <thead>
                        <tr>
                            <th class="w-14">Id</th>
                            <th>Asunto</th>
                            <th class="w-[9.5rem]">Adjuntos</th>
                            <th class="w-[16rem] text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($escritos as $e)
                            <tr wire:key="escrito-{{ $e->id }}">
                                <td class="text-neutral-500">{{ $e->id }}</td>
                                <td class="min-w-0 align-top !py-1.5">
                                    <p class="truncate font-medium text-neutral-900" title="{{ $e->subject }}">{{ $e->subject }}</p>
                                    <p class="mt-0.5 line-clamp-2 text-xs leading-snug text-neutral-500" title="{{ strip_tags($e->text) }}">{{ \Illuminate\Support\Str::limit(strip_tags($e->text), 200) }}</p>
                                </td>
                                <td class="align-top !py-1.5 text-[10px] leading-snug text-neutral-600">
                                    @php($adjuntosEscrito = \App\Support\EmailsMasivos\DestinatariosEmailsMasivos::parseAttached((string) ($e->attached ?? '')))
                                    @if ($adjuntosEscrito === [])
                                        —
                                    @else
                                        <ul class="space-y-0.5">
                                            @foreach ($adjuntosEscrito as $nombreAdj)
                                                <li class="truncate" title="{{ $nombreAdj }}">{{ $nombreAdj }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                                <td class="align-top !py-1.5 text-right">
                                    <div class="flex flex-nowrap items-center justify-end gap-1.5">
                                        <a href="{{ route('emails-masivos.form', ['id' => $e->id]) }}"
                                           class="shrink-0 whitespace-nowrap rounded-lg border border-accent-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-primary-700 hover:bg-accent-50">
                                            Editar
                                        </a>
                                        <a href="{{ route('emails-masivos.enviar', ['id' => $e->id]) }}"
                                           class="shrink-0 whitespace-nowrap rounded-lg bg-primary-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-primary-700">
                                            Enviar
                                        </a>
                                        @if (tienePermiso(\App\Support\PermisosIaCatalog::EMAILS_MASIVOS_BORRAR))
                                            @if (in_array((int) $e->id, $escritosConEnvios, true))
                                                <button type="button"
                                                        x-data
                                                        x-on:click="seSwalError('Este mensaje ya fue enviado. Elimine primero todos los envíos desde el historial.', 'No se puede borrar')"
                                                        class="shrink-0 whitespace-nowrap rounded-lg border border-neutral-200 bg-neutral-50 px-2.5 py-1.5 text-xs font-semibold text-neutral-400 cursor-not-allowed"
                                                        title="Tiene envíos registrados">
                                                    Borrar
                                                </button>
                                            @else
                                                <button type="button"
                                                        x-data
                                                        x-on:click="seSwalConfirmar('¿Eliminar este mensaje escrito? Esta acción no se puede deshacer.', 'Eliminar').then(ok => ok && $wire.confirmarEliminar({{ $e->id }}))"
                                                        class="shrink-0 whitespace-nowrap rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">
                                                    Borrar
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-12 text-center text-sm text-neutral-500">
                                    No hay mensajes escritos. <a href="{{ route('emails-masivos.form') }}" class="font-semibold text-primary-700">Crear uno</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($escritos->hasPages())
            <div class="border-t border-accent-200 bg-accent-50/70 px-5 py-4">
                {{ $escritos->links('vendor.pagination.se') }}
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
