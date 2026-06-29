<script>
    window.nombrePdfDesdeContentDisposition = function (header) {
        if (!header) {
            return null;
        }

        const utf8 = header.match(/filename\*=UTF-8''([^;]+)/i);
        if (utf8) {
            try {
                return decodeURIComponent(utf8[1]);
            } catch {
                return null;
            }
        }

        const quoted = header.match(/filename="([^"]+)"/i);
        if (quoted) {
            return quoted[1];
        }

        const plain = header.match(/filename=([^;]+)/i);
        if (plain) {
            return plain[1].trim();
        }

        return null;
    };

    window.abrirPdfPost = function (detail) {
        if (!detail || !detail.action) {
            return;
        }

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const params = new URLSearchParams();
        if (csrf) {
            params.append('_token', csrf);
        }

        const fields = detail.fields || {};
        Object.entries(fields).forEach(([name, value]) => {
            if (Array.isArray(value)) {
                value.forEach((item) => {
                    params.append(name + '[]', item);
                });
            } else if (value !== null && value !== undefined && value !== '') {
                params.append(name, value);
            }
        });

        const ventana = window.open('', '_blank');
        if (!ventana) {
            window.abrirPdfPostFormFallback(detail.action, params);

            return;
        }

        fetch(detail.action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'Accept': 'application/json, application/pdf',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: params.toString(),
            credentials: 'same-origin',
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('No se pudo generar el PDF.');
                }

                const contentType = response.headers.get('Content-Type') || '';

                if (contentType.includes('application/json')) {
                    const data = await response.json();
                    if (data && data.url) {
                        ventana.location.replace(data.url);

                        return null;
                    }

                    throw new Error('No se pudo generar el PDF.');
                }

                if (!contentType.includes('application/pdf')) {
                    throw new Error('No se pudo generar el PDF.');
                }

                const nombreArchivo = window.nombrePdfDesdeContentDisposition(
                    response.headers.get('Content-Disposition'),
                ) || 'documento.pdf';

                const blob = await response.blob();

                return { blob, nombreArchivo };
            })
            .then((resultado) => {
                if (!resultado) {
                    return;
                }

                const archivo = new File([resultado.blob], resultado.nombreArchivo, { type: 'application/pdf' });
                const url = URL.createObjectURL(archivo);
                ventana.location.replace(url);
                ventana.addEventListener('beforeunload', () => {
                    URL.revokeObjectURL(url);
                }, { once: true });
            })
            .catch((error) => {
                ventana.close();
                const mensaje = error?.message || 'No se pudo generar el PDF.';
                if (typeof window.seSwalError === 'function') {
                    window.seSwalError(mensaje);
                } else {
                    alert(mensaje);
                }
            });
    };

    window.abrirPdfPostFormFallback = function (action, params) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        form.target = '_blank';
        form.rel = 'noopener noreferrer';

        params.forEach((value, name) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        form.remove();
    };

    window.abrirPdfPostFromForm = function (form) {
        if (!form || !form.action) {
            return;
        }

        const fields = {};
        form.querySelectorAll('input[name]').forEach((input) => {
            if (input.name === '_token' || input.disabled) {
                return;
            }

            if (input.name.endsWith('[]')) {
                const baseName = input.name.slice(0, -2);
                if (!Array.isArray(fields[baseName])) {
                    fields[baseName] = [];
                }
                fields[baseName].push(input.value);
            } else {
                fields[input.name] = input.value;
            }
        });

        window.abrirPdfPost({ action: form.action, fields });
    };

    window.addEventListener('abrir-pdf-post', (event) => {
        window.abrirPdfPost(event.detail);
    });
</script>
