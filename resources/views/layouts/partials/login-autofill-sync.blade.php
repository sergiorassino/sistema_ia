{{--
    Autocompletar del navegador rellena el DOM pero no dispara wire:model ni updatedDni.
    Sincroniza valores al componente Livewire del formulario de login.

    $loginAutofillSugerirAcceso: solo login staff (nivel + año lectivo desde último acceso).
--}}
@script
<script>
    (() => {
        const sugerirUltimoAcceso = @json($loginAutofillSugerirAcceso ?? true);

        const form = $wire.$el.querySelector('form');
        if (!form) {
            return;
        }

        const readField = (id) => {
            const el = form.querySelector('#' + id);
            return el ? String(el.value || '') : '';
        };

        let syncTimer = null;

        const syncAutofill = async () => {
            const dniVal = readField('dni').replace(/\D/g, '').slice(0, 11);
            const pwrdVal = readField('pwrd');
            const idNivelVal = readField('idNivel');
            const idTerlecVal = readField('idTerlec');
            let dniCambio = false;

            if (dniVal.length >= 7 && $wire.get('dni') !== dniVal) {
                await $wire.set('dni', dniVal);
                dniCambio = true;
            }

            if (pwrdVal !== '' && $wire.get('pwrd') !== pwrdVal) {
                await $wire.set('pwrd', pwrdVal);
            }

            if (idNivelVal !== '' && String($wire.get('idNivel')) !== idNivelVal) {
                await $wire.set('idNivel', idNivelVal);
            }

            if (idTerlecVal !== '' && String($wire.get('idTerlec')) !== idTerlecVal) {
                await $wire.set('idTerlec', idTerlecVal);
            }

            if (sugerirUltimoAcceso && (dniCambio || (dniVal.length >= 7 && pwrdVal !== ''))) {
                await $wire.sugerirUltimoAccesoDesdeDni();
            }
        };

        const scheduleSyncAutofill = () => {
            if (syncTimer) {
                window.clearTimeout(syncTimer);
            }
            syncTimer = window.setTimeout(syncAutofill, 120);
        };

        ['change', 'input'].forEach((eventName) => {
            form.querySelector('#dni')?.addEventListener(eventName, scheduleSyncAutofill);
            form.querySelector('#pwrd')?.addEventListener(eventName, scheduleSyncAutofill);
            form.querySelector('#idNivel')?.addEventListener(eventName, scheduleSyncAutofill);
            form.querySelector('#idTerlec')?.addEventListener(eventName, scheduleSyncAutofill);
        });

        const boot = () => {
            if (boot.started) {
                return;
            }
            boot.started = true;
            [100, 300, 600, 1200].forEach((ms) => window.setTimeout(scheduleSyncAutofill, ms));
        };

        document.addEventListener('livewire:initialized', boot, { once: true });
        window.setTimeout(boot, 150);
    })();
</script>
@endscript
