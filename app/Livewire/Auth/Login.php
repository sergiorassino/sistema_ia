<?php

namespace App\Livewire\Auth;

use App\Models\Nivel;
use App\Models\Profesor;
use App\Models\Terlec;
use App\Support\DniInput;
use App\Support\EntoTerlecVerNotas;
use App\Support\ProfesorMenuPortal;
use App\Support\SchoolContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $dni = '';

    public string $pwrd = '';

    public int|string $idNivel = '';

    public int|string $idTerlec = '';

    /** Mensaje a pantalla completa cuando un docente elige un año no autorizado. */
    public ?string $mensajeBloqueoDocenteTerlec = null;

    public function mount(): void
    {
        $request = request();

        if ($request->hasAny(['password', 'pwrd'])) {
            session()->flash(
                'error',
                'Por seguridad no se puede iniciar sesión con contraseña en la dirección web. Ingrese sus datos nuevamente.',
            );

            $this->redirectRoute('login', navigate: false);

            return;
        }

        if ($this->dni === '' && $request->filled('username')) {
            $dni = DniInput::digitsOnly((string) $request->query('username'));
            if ($dni !== '') {
                $this->dni = $dni;
                $this->updatedDni($dni);
            }
        }
    }

    public function updatedDni(string $value): void
    {
        $this->resetErrorBag('dni');

        $dni = DniInput::digitsOnly($value);
        if ($dni !== $this->dni) {
            $this->dni = $dni;
        }

        // Evitar consultas si el DNI todavía no es válido
        if ($dni === '' || strlen($dni) < 7) {
            return;
        }

        $profesor = Profesor::query()
            ->where('dni', $dni)
            ->orderBy('id', 'asc')
            ->first(['ult_idNivel', 'ult_idTerlec']);

        if (! $profesor) {
            return;
        }

        if ($this->idNivel === '' && (int) $profesor->ult_idNivel > 0) {
            $ultNivel = (int) $profesor->ult_idNivel;
            if (Nivel::query()->whereKey($ultNivel)->exists()) {
                $this->idNivel = $ultNivel;
            }
        }

        if ($this->idTerlec === '' && (int) $profesor->ult_idTerlec > 0) {
            $ultTerlec = (int) $profesor->ult_idTerlec;
            if (Terlec::query()->whereKey($ultTerlec)->exists()) {
                $this->idTerlec = $ultTerlec;
            }
        }
    }

    public function updatedPwrd(): void
    {
        $this->resetErrorBag('dni');
    }

    public function rules(): array
    {
        return [
            'dni' => ['required', 'digits_between:7,11'],
            'pwrd' => ['required', 'min:1'],
            'idNivel' => ['required', 'integer', 'min:1'],
            'idTerlec' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'dni.required' => 'El DNI es obligatorio.',
            'dni.digits_between' => 'El DNI debe tener entre 7 y 11 dígitos.',
            'pwrd.required' => 'La contraseña es obligatoria.',
            'idNivel.required' => 'Seleccione un nivel.',
            'idNivel.integer' => 'Seleccione un nivel válido.',
            'idTerlec.required' => 'Seleccione un año lectivo.',
            'idTerlec.integer' => 'Seleccione un año lectivo válido.',
        ];
    }

    public function login()
    {
        $this->dni = DniInput::digitsOnly($this->dni);

        // Si el usuario no seleccionó nivel/año, intentar sugerirlos desde el último acceso guardado.
        $dni = $this->dni;
        if (
            $dni !== ''
            && strlen($dni) >= 7
            && ($this->idNivel === '' || $this->idTerlec === '')
        ) {
            $profesor = Profesor::query()
                ->where('dni', $dni)
                ->orderBy('id', 'asc')
                ->first(['ult_idNivel', 'ult_idTerlec']);

            if ($profesor) {
                if ($this->idNivel === '' && (int) $profesor->ult_idNivel > 0) {
                    $ultNivel = (int) $profesor->ult_idNivel;
                    if (Nivel::query()->whereKey($ultNivel)->exists()) {
                        $this->idNivel = $ultNivel;
                    }
                }

                if ($this->idTerlec === '' && (int) $profesor->ult_idTerlec > 0) {
                    $ultTerlec = (int) $profesor->ult_idTerlec;
                    if (Terlec::query()->whereKey($ultTerlec)->exists()) {
                        $this->idTerlec = $ultTerlec;
                    }
                }
            }
        }

        $this->validate();

        $throttleKey = 'login:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'dni' => 'Demasiados intentos de acceso. Intente nuevamente en '.RateLimiter::availableIn($throttleKey).' segundos.',
            ]);
        }

        $credentials = [
            'dni' => $this->dni,
            'pwrd' => $this->pwrd,
            'nivel' => (int) $this->idNivel,
        ];

        if (Auth::attempt($credentials, false)) {
            /** @var Profesor $profesor */
            $profesor = Auth::user();

            if (ProfesorMenuPortal::usaMenuDocentes($profesor)
                && ! EntoTerlecVerNotas::terlecPermitido((int) $this->idNivel, (int) $this->idTerlec)) {
                Auth::logout();
                RateLimiter::clear($throttleKey);
                $this->mensajeBloqueoDocenteTerlec = EntoTerlecVerNotas::mensajeSoloAnoAutorizado((int) $this->idNivel);

                return;
            }

            // Regenerar sesión primero (seguridad anti-session-fixation)
            session()->regenerate();

            // Guardar el contexto en la sesión ya regenerada
            SchoolContext::set(
                idProfesor: $profesor->id,
                idNivel: (int) $this->idNivel,
                idTerlec: (int) $this->idTerlec,
            );

            // Actualizar último nivel/año para TODOS los registros del mismo DNI
            // (hay usuarios con múltiples filas en `profesores`, una por nivel).
            Profesor::query()->where('dni', $profesor->dni)->update([
                'ult_idNivel' => (int) $this->idNivel,
                'ult_idTerlec' => (int) $this->idTerlec,
            ]);

            RateLimiter::clear($throttleKey);

            // Redirección completa (no SPA wire:navigate) para garantizar
            // que las cookies de sesión se propaguen correctamente
            return $this->redirectRoute(ProfesorMenuPortal::rutaInicio($profesor), navigate: false);
        }

        RateLimiter::hit($throttleKey, 60);

        $this->addError('dni', 'DNI o contraseña incorrectos. Verifique sus datos.');
    }

    public function cerrarMensajeBloqueoDocenteTerlec(): void
    {
        $this->mensajeBloqueoDocenteTerlec = null;
    }

    public function render()
    {
        $niveles = Nivel::orderBy('id')->get(['id', 'nivel']);

        return view('livewire.auth.login', compact('niveles'))
            ->layout('layouts.guest');
    }
}
