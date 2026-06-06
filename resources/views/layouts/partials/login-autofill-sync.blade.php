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

        form.querySelector('#dni')?.addEventListener('change', syncAutofill);
        form.querySelector('#pwrd')?.addEventListener('change', syncAutofill);

        [50, 150, 400, 800].forEach((ms) => window.setTimeout(syncAutofill, ms));
    })();
</script>
@endscript
