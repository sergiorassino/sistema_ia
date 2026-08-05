<?php

namespace App\Livewire\Alumnos\Auth;

use App\Models\Ento;
use App\Models\Legajo;
use App\Models\Matricula;
use App\Models\Terlec;
use App\Support\Alumnos\SinMatriculaAutogestionException;
use App\Support\DniInput;
use App\Support\InformeInasistencias;
use App\Support\StudentContext;
use App\Support\Auth\RecuperacionContrasenaOrigen;
use App\Livewire\Concerns\RecuperaContrasenaOlvidada;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    use RecuperaContrasenaOlvidada;

    public string $dni = '';

    public string $pwrd = '';

    public function mount(): void
    {
        $request = request();

        if ($request->hasAny(['password', 'pwrd'])) {
            session()->flash(
                'error',
                'Por seguridad no se puede iniciar sesión con contraseña en la dirección web. Ingrese sus datos nuevamente.',
            );

            $this->redirectRoute('alumnos.login', navigate: false);

            return;
        }

        if ($this->dni === '' && $request->filled('username')) {
            $dni = DniInput::digitsOnly((string) $request->query('username'));
            if ($dni !== '') {
                $this->dni = $dni;
            }
        }
    }

    public function updatedDni(?string $value = null): void
    {
        $this->resetErrorBag('dni');

        $dni = DniInput::digitsOnly($value ?? $this->dni);
        if ($dni !== $this->dni) {
            $this->dni = $dni;
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
        ];
    }

    public function messages(): array
    {
        return [
            'dni.required' => 'El DNI es obligatorio.',
            'dni.digits_between' => 'El DNI debe tener entre 7 y 11 dígitos.',
            'pwrd.required' => 'La contraseña es obligatoria.',
        ];
    }

    public function login()
    {
        $this->dni = DniInput::digitsOnly($this->dni);

        $this->validate();

        $throttleKey = 'alumnos:login:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'dni' => 'Demasiados intentos de acceso. Intente nuevamente en '.RateLimiter::availableIn($throttleKey).' segundos.',
            ]);
        }

        $credentials = [
            'dni' => $this->dni,
            'pwrd' => $this->pwrd,
        ];

        if (Auth::guard('alumno')->attempt($credentials, false)) {
            /** @var Legajo $alumno */
            $alumno = Auth::guard('alumno')->user();

            session(['auth.pending_session_regenerate' => true]);

            $idNivel = (int) ($alumno->idnivel ?? 0);
            if ($idNivel <= 0) {
                $idNivel = (int) (Matricula::query()
                    ->where('idLegajos', (int) $alumno->id)
                    ->orderByDesc('idTerlec')
                    ->orderByDesc('id')
                    ->value('idNivel') ?? 0);
            }

            $idTerlec = (int) (Ento::query()
                ->where('idNivel', $idNivel)
                ->value('idTerlecVerNotas') ?? 0);

            if ($idNivel <= 0 || $idTerlec <= 0 || ! Terlec::query()->whereKey($idTerlec)->exists()) {
                Auth::guard('alumno')->logout();
                StudentContext::clear();
                RateLimiter::clear($throttleKey);

                throw ValidationException::withMessages([
                    'dni' => 'No se pudo determinar el ciclo lectivo para autogestión. Contacte a secretaría.',
                ]);
            }

            StudentContext::set(
                idLegajo: (int) $alumno->id,
                idNivel: $idNivel,
                idTerlec: $idTerlec,
            );

            if (! InformeInasistencias::tieneMatriculaCursoAutogestion()) {
                Auth::guard('alumno')->logout();
                StudentContext::clear();
                RateLimiter::clear($throttleKey);
                $this->pwrd = '';
                $this->dispatch(
                    'se-swal-error',
                    mensaje: SinMatriculaAutogestionException::MENSAJE,
                    titulo: 'Acceso no disponible',
                    confirmButtonText: 'Volver al inicio de sesión',
                );

                return;
            }

            RateLimiter::clear($throttleKey);

            return $this->redirectRoute(tenantAutogestionRutaInicio(), navigate: false);
        }

        RateLimiter::hit($throttleKey, 60);

        $this->addError('dni', 'DNI o contraseña incorrectos. Verifique sus datos.');
    }

    protected function origenRecuperacionContrasena(): RecuperacionContrasenaOrigen
    {
        return RecuperacionContrasenaOrigen::Alumno;
    }

    public function render()
    {
        return view('livewire.alumnos.auth.login')
            ->layout('layouts.guest', ['guestPortal' => 'alumno']);
    }
}
