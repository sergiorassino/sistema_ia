<div class="se-card overflow-hidden">
    <div class="border-b border-accent-200 bg-white px-5 py-4">
        <p class="se-section-title">Envío completado</p>
        <p class="mt-1 text-sm text-green-800">{{ $resultadoMensaje }}</p>
    </div>
    <div class="p-5 sm:p-6">
        <ul class="max-h-96 list-decimal space-y-1 overflow-y-auto pl-5 text-sm">
            @foreach ($resultadoDestinatarios as $email)
                <li><strong>{{ $loop->iteration }}:</strong> {{ $email }}</li>
            @endforeach
        </ul>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('emails-masivos.nuevo') }}" class="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white">Nuevo envío</a>
            <a href="{{ route('emails-masivos.index') }}" class="rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-neutral-700">Ver historial</a>
        </div>
    </div>
</div>
