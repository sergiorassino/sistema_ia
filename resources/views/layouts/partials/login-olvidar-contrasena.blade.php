@php
    $nivelesRecuperar = isset($niveles)
        ? $niveles->map(fn ($n) => ['id' => (int) $n->id, 'label' => (string) $n->nivel])->values()->all()
        : [];
    $requiereNivel = ($variant ?? 'staff') === 'staff' && $nivelesRecuperar !== [];
@endphp

<div class="text-center {{ ($variant ?? 'staff') === 'alumno' ? 'pt-0.5' : 'pt-1' }}">
    <button type="button"
            class="{{ ($variant ?? 'staff') === 'alumno' ? 'text-xs font-semibold text-primary-700 hover:text-primary-800 underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 rounded' : 'se-auth-link' }}"
            wire:loading.attr="disabled"
            wire:target="recuperarContrasena"
            x-on:click="
                if (typeof window.seSwalPromptRecuperarContrasena !== 'function') {
                    window.seSwalError && window.seSwalError('No se pudo abrir el formulario. Recargue la página.');
                    return;
                }
                window.seSwalPromptRecuperarContrasena({
                    mensaje: @js($requiereNivel
                        ? 'Ingrese su DNI y seleccione el nivel para recibir la contraseña en el correo registrado.'
                        : 'Ingrese su DNI para recibir la contraseña en el correo registrado.'),
                    niveles: @js($nivelesRecuperar),
                }).then(datos => {
                    if (!datos) {
                        return;
                    }
                    if (datos.idNivel) {
                        $wire.recuperarContrasena(datos.dni, datos.idNivel);
                    } else {
                        $wire.recuperarContrasena(datos.dni);
                    }
                });
            ">
        <span wire:loading.remove wire:target="recuperarContrasena">Olvidé mi contraseña</span>
        <span wire:loading wire:target="recuperarContrasena">Enviando…</span>
    </button>
</div>

@script
<script>
    $wire.on('se-swal-exito', ({ mensaje, titulo }) => window.seSwalExito(mensaje, titulo ?? 'Listo'));
    $wire.on('se-swal-error', ({ mensaje, titulo, confirmButtonText }) => window.seSwalError(
        mensaje,
        titulo ?? 'Error',
        confirmButtonText ? { confirmButtonText } : {}
    ));
    $wire.on('se-swal-aviso', ({ mensaje, titulo }) => window.seSwalAviso(mensaje, titulo ?? 'Atención'));
</script>
@endscript
