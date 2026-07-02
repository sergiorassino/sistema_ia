{{-- SFQ — observaciones pedagógicas y Bellas Artes. --}}
@php
    $soloLectura = $soloLectura ?? false;
    $mostrarModalNotasOff = $mostrarModalNotasOff ?? false;
    $mensajeNotasOff = $mensajeNotasOff ?? '';
@endphp
<div>
<div class="mx-auto w-full max-w-4xl space-y-6">
    <div class="overflow-hidden rounded-2xl bg-neutral-800 px-5 py-3 text-center">
        <p class="text-sm font-bold uppercase tracking-wide text-white">{{ $alumnoLinea }}</p>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-3">
        @if ($soloLectura)
            <p class="mr-auto text-xs font-semibold uppercase tracking-wide text-amber-700">Solo consulta — la carga está deshabilitada</p>
        @endif
        <a href="{{ \App\Support\PortalDocente\CalificacionesInicialSfqPortalDocente::route('index', ['curso' => $cursoId]) }}"
           class="inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-5 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-50">
            Volver
        </a>
    </div>

    <div class="se-card space-y-6 p-5">
        <section class="overflow-hidden rounded-xl border border-accent-200">
            <div class="border-b border-accent-200 bg-accent-50 px-4 py-2.5">
                <h3 class="text-sm font-semibold text-neutral-800">Observaciones Informes Pedagógicos</h3>
            </div>
            <div class="divide-y divide-accent-100 bg-white">
                <div class="grid gap-3 p-4 sm:grid-cols-[8rem_1fr] sm:items-start">
                    <label for="sfq-obs01" class="pt-2 text-sm font-medium text-neutral-700">Adaptación</label>
                    <textarea id="sfq-obs01" wire:model="obs01" rows="4" maxlength="{{ $maxCaracteres }}"
                              @readonly($soloLectura)
                              @if (! $soloLectura) wire:blur="guardarCampo('obs01', $event.target.value)" @endif
                              @class(['form-input w-full resize-y text-sm leading-relaxed', 'bg-accent-50/80 text-neutral-700 cursor-default' => $soloLectura])></textarea>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-[8rem_1fr] sm:items-start">
                    <label for="sfq-obs02" class="pt-2 text-sm font-medium text-neutral-700">Primera Etapa</label>
                    <textarea id="sfq-obs02" wire:model="obs02" rows="4" maxlength="{{ $maxCaracteres }}"
                              @readonly($soloLectura)
                              @if (! $soloLectura) wire:blur="guardarCampo('obs02', $event.target.value)" @endif
                              @class(['form-input w-full resize-y text-sm leading-relaxed', 'bg-accent-50/80 text-neutral-700 cursor-default' => $soloLectura])></textarea>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-[8rem_1fr] sm:items-start">
                    <label for="sfq-obs03" class="pt-2 text-sm font-medium text-neutral-700">Segunda Etapa</label>
                    <textarea id="sfq-obs03" wire:model="obs03" rows="4" maxlength="{{ $maxCaracteres }}"
                              @readonly($soloLectura)
                              @if (! $soloLectura) wire:blur="guardarCampo('obs03', $event.target.value)" @endif
                              @class(['form-input w-full resize-y text-sm leading-relaxed', 'bg-accent-50/80 text-neutral-700 cursor-default' => $soloLectura])></textarea>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-accent-200">
            <div class="border-b border-accent-200 bg-accent-50 px-4 py-2.5">
                <h3 class="text-sm font-semibold text-neutral-800">Observaciones Bellas Artes</h3>
            </div>
            <div class="divide-y divide-accent-100 bg-white">
                <div class="grid gap-3 p-4 sm:grid-cols-[8rem_1fr] sm:items-start">
                    <label for="sfq-baObs01" class="pt-2 text-sm font-medium text-neutral-700">Adaptación</label>
                    <textarea id="sfq-baObs01" wire:model="baObs01" rows="4" maxlength="{{ $maxCaracteres }}"
                              @readonly($soloLectura)
                              @if (! $soloLectura) wire:blur="guardarCampo('baObs01', $event.target.value)" @endif
                              @class(['form-input w-full resize-y text-sm leading-relaxed', 'bg-accent-50/80 text-neutral-700 cursor-default' => $soloLectura])></textarea>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-[8rem_1fr] sm:items-start">
                    <label for="sfq-baObs02" class="pt-2 text-sm font-medium text-neutral-700">Primera Etapa</label>
                    <textarea id="sfq-baObs02" wire:model="baObs02" rows="4" maxlength="{{ $maxCaracteres }}"
                              @readonly($soloLectura)
                              @if (! $soloLectura) wire:blur="guardarCampo('baObs02', $event.target.value)" @endif
                              @class(['form-input w-full resize-y text-sm leading-relaxed', 'bg-accent-50/80 text-neutral-700 cursor-default' => $soloLectura])></textarea>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-[8rem_1fr] sm:items-start">
                    <label for="sfq-baObs03" class="pt-2 text-sm font-medium text-neutral-700">Segunda Etapa</label>
                    <textarea id="sfq-baObs03" wire:model="baObs03" rows="4" maxlength="{{ $maxCaracteres }}"
                              @readonly($soloLectura)
                              @if (! $soloLectura) wire:blur="guardarCampo('baObs03', $event.target.value)" @endif
                              @class(['form-input w-full resize-y text-sm leading-relaxed', 'bg-accent-50/80 text-neutral-700 cursor-default' => $soloLectura])></textarea>
                </div>
            </div>
        </section>
    </div>
</div>

    @include('livewire.partials.modal-carga-notas-off', [
        'modalWireKey' => 'modal-notas-off-ini-obs',
        'modalTituloId' => 'modal-notas-off-ini-obs-titulo',
    ])
</div>

@script
<script>
    $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
</script>
@endscript
