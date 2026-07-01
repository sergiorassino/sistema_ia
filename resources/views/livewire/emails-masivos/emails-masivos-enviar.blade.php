<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-2">
                    <p class="se-eyebrow">Enviar mensaje</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $asunto }}</h2>
                    <p class="text-sm text-white/80">Copia oculta (BCC) · {{ schoolCtx()->terlecAno() }}</p>
                </div>
                <a href="{{ route('emails-masivos.index') }}" class="rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white">Volver</a>
            </div>
        </section>

        @if (! $credencialesOk)
            <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Configure su email y contraseña de aplicación Gmail (emailPass) en su legajo docente.
            </div>
        @endif
        @if ($simulado)
            <div class="mb-4 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">Modo simulado: no se envía SMTP real.</div>
        @endif

        @if ($envioCompletado)
            <div class="se-card mb-4 p-5">
                <p class="font-semibold text-green-800">Envío registrado correctamente.</p>
                <ul class="mt-3 max-h-48 list-decimal overflow-y-auto pl-5 text-sm">
                    @foreach ($resultadoDestinatarios as $email)
                        <li>{{ $email }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('emails-masivos.historial') }}" class="mt-4 inline-block text-sm font-semibold text-primary-700">Ver historial</a>
            </div>
        @endif

        <div class="grid gap-4 xl:grid-cols-2">
            {{-- Columna izquierda: mensaje + destinatarios --}}
            <div class="space-y-4">
                <div class="se-card p-5">
                    <p class="se-section-title">Mensaje</p>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div><dt class="text-[10px] font-semibold uppercase text-neutral-500">Asunto</dt><dd>{{ $asunto }}</dd></div>
                        @if ($adjuntosLista !== [])
                            <div><dt class="text-[10px] font-semibold uppercase text-neutral-500">Adjuntos</dt><dd>{{ implode(' · ', $adjuntosLista) }}</dd></div>
                        @endif
                    </dl>
                    <div class="prose prose-sm mt-4 max-h-40 overflow-y-auto rounded-xl border border-accent-100 bg-accent-50/40 p-3">{!! $contenidoHtml !!}</div>
                    <a href="{{ route('emails-masivos.form', ['id' => $idEscrito]) }}" class="mt-3 inline-block text-xs font-semibold text-primary-700">Editar mensaje</a>
                </div>

                <div class="se-card overflow-hidden">
                    @include('livewire.emails-masivos.partials.panel-destinatarios')
                </div>
            </div>

            {{-- Columna derecha: preview + envío --}}
            <div class="se-card overflow-hidden">
                <div class="border-b border-accent-200 bg-white px-5 py-4">
                    <p class="se-section-title">Destinatarios del envío (BCC)</p>
                    <p class="mt-1 text-sm text-neutral-600">
                        <strong>{{ $nEnvios }}</strong> correo(s) · Remitente: {{ $profesor?->email }}
                    </p>
                    @if ($superaTope)
                        <p class="mt-2 text-sm font-semibold text-red-700">Supera el máximo de {{ $maxEnvio }}. Reduzca la selección.</p>
                    @elseif ($superaAviso)
                        <p class="mt-2 text-sm text-amber-800">Envío grande (más de {{ $avisoEnvio }} destinatarios).</p>
                    @endif
                </div>
                <div class="max-h-[28rem] overflow-y-auto">
                    <table class="se-matriz-list-tabla w-full">
                        <thead><tr><th>#</th><th>Alumno</th><th>Tipo</th><th>Email</th></tr></thead>
                        <tbody>
                            @forelse ($destinatariosPreview as $i => $d)
                                <tr wire:key="dest-{{ $i }}-{{ $d['email'] }}">
                                    <td>{{ $i + 1 }}</td>
                                    <td class="text-sm">{{ $d['alumnoLabel'] }}</td>
                                    <td class="capitalize text-xs">{{ $d['tipo'] }}</td>
                                    <td class="font-mono text-xs">{{ $d['email'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-8 text-center text-sm text-neutral-500">Elija cursos o alumnos para ver la lista.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-accent-100 bg-accent-50/40 px-5 py-4">
                    <p class="mb-3 text-xs text-neutral-600">Los destinatarios no verán el email de otras familias (copia oculta).</p>
                    <button type="button"
                            @disabled($superaTope || ! $credencialesOk || $nEnvios === 0 || $envioCompletado)
                            wire:loading.attr="disabled"
                            wire:target="enviar"
                            x-data
                            x-on:click="seSwalConfirmar('¿Confirmar envío a {{ $nEnvios }} destinatarios en copia oculta (BCC)?', 'Enviar correo').then(ok => ok && $wire.enviar())"
                            class="w-full rounded-xl bg-primary-600 px-5 py-3 text-sm font-semibold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">
                        <span wire:loading.remove wire:target="enviar">Enviar correo (BCC)</span>
                        <span wire:loading wire:target="enviar">Enviando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @include('livewire.emails-masivos.partials.modales-destinatarios')

    <div wire:loading.flex
         wire:target="enviar"
         class="fixed inset-0 z-[100] items-center justify-center bg-neutral-900/45 backdrop-blur-sm px-4">
        <div class="max-w-md rounded-2xl bg-white px-6 py-5 text-center shadow-xl ring-1 ring-black/5">
            <div class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-2 border-primary-200 border-t-primary-600"></div>
            <p class="text-sm font-semibold text-neutral-800">Enviando correo electrónico</p>
            <p class="mt-2 text-sm text-neutral-600">
                {{ $nEnvios }} destinatario(s) en copia oculta (BCC)
            </p>
            @if ($profesor?->email)
                <p class="mt-1 break-all text-sm text-primary-700">Desde: {{ $profesor->email }}</p>
            @endif
            <p class="mt-3 text-xs text-neutral-500">No cierre esta ventana hasta que finalice.</p>
        </div>
    </div>

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje }) => window.seSwalExito(mensaje));
        $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
    </script>
    @endscript
</div>
