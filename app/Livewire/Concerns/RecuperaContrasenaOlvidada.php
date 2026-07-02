<?php

namespace App\Livewire\Concerns;

use App\Support\Auth\RecuperacionContrasenaOrigen;
use App\Support\Auth\RecuperacionContrasenaPorCorreo;
use App\Support\Auth\RecuperacionContrasenaResultado;

trait RecuperaContrasenaOlvidada
{
    abstract protected function origenRecuperacionContrasena(): RecuperacionContrasenaOrigen;

    public function recuperarContrasena(string $dni, int|string|null $idNivel = null): void
    {
        $nivel = ($idNivel !== null && $idNivel !== '') ? (int) $idNivel : null;

        $resultado = RecuperacionContrasenaPorCorreo::enviar(
            $dni,
            $this->origenRecuperacionContrasena(),
            (string) request()->ip(),
            $nivel,
        );

        $this->dispatchRecuperacionContrasenaResultado($resultado);
    }

    private function dispatchRecuperacionContrasenaResultado(RecuperacionContrasenaResultado $resultado): void
    {
        match ($resultado->estado) {
            RecuperacionContrasenaResultado::ESTADO_ENVIADO => $this->dispatch(
                'se-swal-exito',
                mensaje: 'La contraseña fue enviada correctamente a '.$resultado->emailDestino.'.',
                titulo: 'Correo enviado',
            ),
            RecuperacionContrasenaResultado::ESTADO_SIN_EMAIL => $this->dispatch(
                'se-swal-aviso',
                mensaje: 'El usuario no tiene un correo electrónico registrado en el sistema. Contacte a secretaría para actualizar sus datos.',
                titulo: 'Sin correo registrado',
            ),
            RecuperacionContrasenaResultado::ESTADO_DNI_NO_ENCONTRADO => $this->dispatch(
                'se-swal-error',
                mensaje: $this->origenRecuperacionContrasena() === RecuperacionContrasenaOrigen::Profesor
                    ? 'No se encontró un usuario registrado con ese DNI en el nivel seleccionado.'
                    : 'No se encontró un usuario registrado con ese DNI.',
                titulo: 'Usuario no encontrado',
            ),
            RecuperacionContrasenaResultado::ESTADO_CONTRASENA_NO_ENVIABLE => $this->dispatch(
                'se-swal-aviso',
                mensaje: 'Su contraseña fue actualizada con un formato seguro y no puede enviarse por correo. Contacte a secretaría para blanquearla.',
                titulo: 'Contraseña no recuperable',
            ),
            RecuperacionContrasenaResultado::ESTADO_MAIL_NO_CONFIGURADO => $this->dispatch(
                'se-swal-error',
                mensaje: 'El envío de correo no está configurado en el servidor. Contacte al administrador del sistema.',
                titulo: 'Correo no disponible',
            ),
            RecuperacionContrasenaResultado::ESTADO_DNI_INVALIDO => $this->dispatch(
                'se-swal-error',
                mensaje: 'Ingrese un DNI válido (entre 7 y 11 dígitos).',
                titulo: 'DNI inválido',
            ),
            RecuperacionContrasenaResultado::ESTADO_NIVEL_INVALIDO => $this->dispatch(
                'se-swal-error',
                mensaje: 'Seleccione un nivel válido.',
                titulo: 'Nivel inválido',
            ),
            RecuperacionContrasenaResultado::ESTADO_LIMITE_INTENTOS => $this->dispatch(
                'se-swal-error',
                mensaje: 'Demasiados intentos. Espere '.$resultado->mensajeError.' segundos e intente nuevamente.',
                titulo: 'Demasiados intentos',
            ),
            default => $this->dispatch(
                'se-swal-error',
                mensaje: $resultado->mensajeError ?? 'No se pudo completar el envío.',
                titulo: 'Error al enviar',
            ),
        };
    }
}
