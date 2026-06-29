{{-- SFQ — observaciones pedagógicas y Bellas Artes. --}}
<div class="mx-auto w-full max-w-4xl space-y-6">
    <div class="overflow-hidden rounded-2xl bg-neutral-800 px-5 py-3 text-center">
        <p class="text-sm font-bold uppercase tracking-wide text-white">{{ $alumnoLinea }}</p>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-3">
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
                    <textarea id="sfq-obs01" wire:model="obs01" wire:blur="guardarCampo('obs01', $event.target.value)" rows="4" maxlength="{{ $maxCaracteres }}"
                              class="form-input w-full resize-y text-sm leading-relaxed"></textarea>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-[8rem_1fr] sm:items-start">
                    <label for="sfq-obs02" class="pt-2 text-sm font-medium text-neutral-700">Primera Etapa</label>
                    <textarea id="sfq-obs02" wire:model="obs02" wire:blur="guardarCampo('obs02', $event.target.value)" rows="4" maxlength="{{ $maxCaracteres }}"
                              class="form-input w-full resize-y text-sm leading-relaxed"></textarea>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-[8rem_1fr] sm:items-start">
                    <label for="sfq-obs03" class="pt-2 text-sm font-medium text-neutral-700">Segunda Etapa</label>
                    <textarea id="sfq-obs03" wire:model="obs03" wire:blur="guardarCampo('obs03', $event.target.value)" rows="4" maxlength="{{ $maxCaracteres }}"
                              class="form-input w-full resize-y text-sm leading-relaxed"></textarea>
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
                    <textarea id="sfq-baObs01" wire:model="baObs01" wire:blur="guardarCampo('baObs01', $event.target.value)" rows="4" maxlength="{{ $maxCaracteres }}"
                              class="form-input w-full resize-y text-sm leading-relaxed"></textarea>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-[8rem_1fr] sm:items-start">
                    <label for="sfq-baObs02" class="pt-2 text-sm font-medium text-neutral-700">Primera Etapa</label>
                    <textarea id="sfq-baObs02" wire:model="baObs02" wire:blur="guardarCampo('baObs02', $event.target.value)" rows="4" maxlength="{{ $maxCaracteres }}"
                              class="form-input w-full resize-y text-sm leading-relaxed"></textarea>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-[8rem_1fr] sm:items-start">
                    <label for="sfq-baObs03" class="pt-2 text-sm font-medium text-neutral-700">Segunda Etapa</label>
                    <textarea id="sfq-baObs03" wire:model="baObs03" wire:blur="guardarCampo('baObs03', $event.target.value)" rows="4" maxlength="{{ $maxCaracteres }}"
                              class="form-input w-full resize-y text-sm leading-relaxed"></textarea>
                </div>
            </div>
        </section>
    </div>
</div>

@script
<script>
    $wire.on('se-swal-error', ({ mensaje }) => window.seSwalError(mensaje));
</script>
@endscript
