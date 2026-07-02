<?php

namespace App\Livewire\Auth;

use App\Support\Auth\ActualizarContraseñaUsuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class CambiarContrasenaModal extends Component
{
    public string $guard = 'web';

    public bool $abierto = false;

    public string $pwrdNueva = '';

    public string $pwrdConfirmacion = '';

    public function abrir(): void
    {
        $this->resetValidation();
        $this->pwrdNueva = '';
        $this->pwrdConfirmacion = '';
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->pwrdNueva = '';
        $this->pwrdConfirmacion = '';
        $this->resetValidation();
    }

    public function guardar(): void
    {
        $user = Auth::guard($this->guard)->user();
        if (! $user) {
            $this->dispatch('se-swal-error', mensaje: 'Su sesión expiró. Vuelva a iniciar sesión.');

            return;
        }

        $key = 'cambiar-pwrd:'.$this->guard.':'.$user->getAuthIdentifier().':'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seg = RateLimiter::availableIn($key);
            $this->dispatch('se-swal-error', mensaje: "Demasiados intentos. Espere {$seg} segundos.");

            return;
        }

        $this->validate([
            'pwrdNueva' => ['required', 'string', 'min:4', 'max:50'],
            'pwrdConfirmacion' => ['required', 'string', 'same:pwrdNueva'],
        ], [
            'pwrdNueva.required' => 'Ingrese la nueva contraseña.',
            'pwrdNueva.min' => 'La contraseña debe tener al menos 4 caracteres.',
            'pwrdNueva.max' => 'La contraseña no puede superar 50 caracteres.',
            'pwrdConfirmacion.required' => 'Confirme la nueva contraseña.',
            'pwrdConfirmacion.same' => 'Las contraseñas no coinciden.',
        ]);

        RateLimiter::hit($key, 60);

        if (! ActualizarContraseñaUsuario::aplicar($user, $this->guard, $this->pwrdNueva)) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo actualizar la contraseña. Intente nuevamente.');

            return;
        }

        RateLimiter::clear($key);
        $this->cerrar();
        $this->dispatch('se-swal-exito', mensaje: 'Contraseña actualizada correctamente.');
    }

    public function render()
    {
        return view('livewire.auth.cambiar-contrasena-modal');
    }
}
