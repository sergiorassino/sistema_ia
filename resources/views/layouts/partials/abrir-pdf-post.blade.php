<script>
    window.abrirPdfPost = function (detail) {
        if (!detail || !detail.action) {
            return;
        }
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = detail.action;
        form.target = '_blank';
        form.rel = 'noopener noreferrer';

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        if (csrf) {
            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = csrf;
            form.appendChild(token);
        }

        const fields = detail.fields || {};
        Object.entries(fields).forEach(([name, value]) => {
            if (Array.isArray(value)) {
                value.forEach((item) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name + '[]';
                    input.value = item;
                    form.appendChild(input);
                });
            } else if (value !== null && value !== undefined && value !== '') {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }
        });

        document.body.appendChild(form);
        form.submit();
        form.remove();
    };

    window.addEventListener('abrir-pdf-post', (event) => {
        window.abrirPdfPost(event.detail);
    });
</script>
