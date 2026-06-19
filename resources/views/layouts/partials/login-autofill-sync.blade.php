{{--
    Autocompletar del navegador rellena el DOM pero no dispara wire:model ni updatedDni.
    Sincroniza valores al componente Livewire del formulario de login.
--}}
@script
<script>
    (() => {
        const form = $wire.$el.querySelector('form');
        if (!form) {
            return;
        }

        const readField = (id) => {
            const el = form.querySelector('#' + id);
            return el ? String(el.value || '') : '';
        };

        let syncTimer = null;

        const syncAutofill = () => {
            const dniVal = readField('dni').replace(/\D/g, '').slice(0, 11);
            if (dniVal.length >= 7 && $wire.get('dni') !== dniVal) {
                $wire.set('dni', dniVal);
            }

            const pwrdVal = readField('pwrd');
            if (pwrdVal !== '' && $wire.get('pwrd') !== pwrdVal) {
                $wire.set('pwrd', pwrdVal);
            }
        };

        const scheduleSyncAutofill = () => {
            if (syncTimer) {
                window.clearTimeout(syncTimer);
            }
            syncTimer = window.setTimeout(syncAutofill, 120);
        };

        form.querySelector('#dni')?.addEventListener('change', scheduleSyncAutofill);
        form.querySelector('#pwrd')?.addEventListener('change', scheduleSyncAutofill);

        const boot = () => {
            if (boot.started) {
                return;
            }
            boot.started = true;
            [300, 800].forEach((ms) => window.setTimeout(scheduleSyncAutofill, ms));
        };

        document.addEventListener('livewire:initialized', boot, { once: true });
        window.setTimeout(boot, 150);
    })();
</script>
@endscript
