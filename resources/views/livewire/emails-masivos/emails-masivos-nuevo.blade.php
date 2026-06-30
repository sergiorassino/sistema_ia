<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-3">
                    <p class="se-eyebrow">Correo masivo</p>
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Nuevo envío</h2>
                        <p class="mt-2 max-w-2xl text-sm text-white/80">
                            {{ schoolCtx()->nivelNombre() }} · Ciclo {{ schoolCtx()->terlecAno() }} · Solo matriculados regulares · BCC
                        </p>
                    </div>
                </div>
                <a href="{{ route('emails-masivos.index') }}"
                   class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                    Volver al historial
                </a>
            </div>
        </section>

        @if (! $credencialesOk)
            <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Configure su <strong>email</strong> y contraseña de aplicación Gmail (<strong>emailPass</strong>) en su legajo docente antes de enviar.
            </div>
        @endif

        @if ($simulado)
            <div class="mb-4 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                Modo simulado activo: se registran los envíos en la base de datos sin SMTP real.
            </div>
        @endif

        {{-- Paso indicador --}}
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach (['redactar' => '1. Mensaje', 'destinatarios' => '2. Destinatarios', 'confirmar' => '3. Confirmar', 'resultado' => '4. Resultado'] as $key => $label)
                <span @class([
                    'rounded-full px-3 py-1 text-xs font-semibold',
                    'bg-primary-600 text-white' => $paso === $key,
                    'bg-accent-100 text-neutral-600' => $paso !== $key,
                ])>{{ $label }}</span>
            @endforeach
        </div>

        @if ($paso === 'redactar')
            @include('livewire.emails-masivos.partials.nuevo-paso-redactar')
        @elseif ($paso === 'destinatarios')
            @include('livewire.emails-masivos.partials.nuevo-paso-destinatarios')
        @elseif ($paso === 'confirmar')
            @include('livewire.emails-masivos.partials.nuevo-paso-confirmar')
        @else
            @include('livewire.emails-masivos.partials.nuevo-paso-resultado')
        @endif
    </div>

    @include('livewire.emails-masivos.partials.modales-destinatarios')

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje }) => window.seSwalExito(mensaje));
        $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
    </script>
    @endscript
</div>
