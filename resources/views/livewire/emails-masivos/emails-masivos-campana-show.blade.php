<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0">
                <p class="se-eyebrow">Correo masivo</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Detalle de campaña</h2>
                <p class="mt-2 text-sm text-white/80">{{ $seed->fechhora?->format('d/m/Y H:i') }} · {{ $seed->subject }}</p>
            </div>
            <a href="{{ route('emails-masivos.historial') }}" class="rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white">Volver</a>
        </div>
    </section>

    @if (tienePermiso(\App\Support\PermisosIaCatalog::EMAILS_MASIVOS_BORRAR))
        <div class="mb-4 flex justify-end">
            <button type="button"
                    x-data
                    x-on:click="seSwalConfirmar('¿Eliminar este envío del historial? Se borrarán {{ $envios->count() }} registro(s). Luego podrá eliminar el mensaje escrito si no quedan más envíos.', 'Eliminar envío', { icon: 'warning' }).then(ok => ok && $wire.confirmarEliminarCampana())"
                    class="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-100">
                Eliminar envío del historial
            </button>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="se-card p-5">
            <p class="se-section-title">Datos del envío</p>
            <dl class="mt-4 space-y-2 text-sm">
                <div><dt class="text-[10px] font-semibold uppercase text-neutral-500">Remitente</dt><dd>{{ $profesor ? trim($profesor->apellido . ', ' . $profesor->nombre) : '—' }}</dd></div>
                <div><dt class="text-[10px] font-semibold uppercase text-neutral-500">Destinatarios</dt><dd>{{ $envios->count() }}</dd></div>
                <div><dt class="text-[10px] font-semibold uppercase text-neutral-500">Adjuntos</dt>
                    <dd class="mt-1 space-y-1">
                        @forelse ($adjuntosLinks as $adj)
                            @if ($adj['disponible'] && $adj['url'])
                                <a href="{{ $adj['url'] }}" class="block text-primary-700 hover:underline">{{ $adj['nombre'] }}</a>
                            @else
                                <span class="text-neutral-500">{{ $adj['nombre'] }} (no disponible)</span>
                            @endif
                        @empty
                            —
                        @endforelse
                    </dd>
                </div>
            </dl>
        </div>
        <div class="se-card p-5">
            <p class="se-section-title">Vista previa HTML</p>
            <div class="prose prose-sm mt-4 max-w-none rounded-xl border border-accent-200 bg-white p-4">{!! $seed->texto !!}</div>
        </div>
    </div>

    <div class="se-card mt-4 overflow-hidden">
        <div class="border-b border-accent-200 px-5 py-3">
            <p class="se-section-title">Destinatarios</p>
        </div>
        <div class="w-full overflow-x-auto">
            <table class="se-matriz-list-tabla min-w-[36rem]">
                <thead><tr><th>Email</th><th>Alumno</th><th>Curso Id</th></tr></thead>
                <tbody>
                    @foreach ($envios as $e)
                        <tr>
                            <td class="font-mono text-xs">{{ $e->mailDestino }}</td>
                            <td>{{ $e->alumno_label ?? ('#' . $e->idLegajos) }}</td>
                            <td>{{ $e->idCursos ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje }) => window.seSwalExito(mensaje));
        $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
    </script>
    @endscript
</div>
