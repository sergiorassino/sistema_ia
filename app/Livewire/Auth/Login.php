<?php

namespace App\Livewire\Auth;

use App\Auth\ProfesorUserProvider;
use App\Models\Profesor;
use App\Models\Terlec;
use App\Support\DniInput;
use App\Support\NivelSistema;
use App\Support\EntoTerlecVerNotas;
use App\Support\ProfesorMenuPortal;
use App\Support\SchoolContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
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

        $this->sugerirUltimoAccesoDesdeDni();
    }

    public function updatedPwrd(): void
    {
        $this->resetErrorBag('dni');
        $this->sugerirUltimoAccesoDesdeDni();
    }

    /** Sugiere nivel y año lectivo según el último acceso guardado en `profesores`. */
    public function sugerirUltimoAccesoDesdeDni(): void
    {
        $dni = DniInput::digitsOnly($this->dni);
        if ($dni === '' || strlen($dni) < 7) {
            return;
        }

        $profesor = Profesor::query()
            ->where('dni', $dni)
            ->orderBy('id', 'asc')
            ->first(['ult_idNivel', 'ult_idTerlec', 'nivel']);

        if (! $profesor) {
            return;
        }

        if ($this->idNivel === '' && (int) $profesor->ult_idNivel > 0) {
            $ultNivel = (int) $profesor->ult_idNivel;
            if (NivelSistema::nivelPermitidoEnLogin($ultNivel)) {
                $this->idNivel = $ultNivel;
            }
        }

        // Primer ingreso (instalación nueva): sugerir nivel del legajo en `profesores.nivel`.
        if ($this->idNivel === '' && (int) ($profesor->nivel ?? 0) > 0) {
            $nivelLegajo = (int) $profesor->nivel;
            if (NivelSistema::nivelPermitidoEnLogin($nivelLegajo)) {
                $this->idNivel = $nivelLegajo;
            }
        }

        if ($this->idTerlec === '' && (int) $profesor->ult_idTerlec > 0) {
            $ultTerlec = (int) $profesor->ult_idTerlec;
            if (Terlec::query()->whereKey($ultTerlec)->exists()) {
                $this->idTerlec = $ultTerlec;
            }
        }

        // Primer ingreso: preseleccionar el ciclo lectivo más reciente si aún no hay uno.
        if ($this->idTerlec === '') {
            $terlecReciente = Terlec::paraSelector()->first();
            if ($terlecReciente !== null) {
                $this->idTerlec = (int) $terlecReciente->id;
            }
        }
    }

    public function rules(): array
    {
        $terlecIds = Terlec::paraSelector()->pluck('id')->all();

        return [
            'dni' => ['required', 'digits_between:7,11'],
            'pwrd' => ['required', 'min:1'],
            'idNivel' => [
                'required',
                'integer',
                'min:1',
                Rule::in(NivelSistema::nivelesParaLogin()->pluck('id')->all()),
            ],
            'idTerlec' => [
                'required',
                'integer',
                'min:1',
                Rule::in($terlecIds),
            ],
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
            'idNivel.in' => 'Seleccione un nivel válido para este colegio.',
            'idTerlec.required' => 'Seleccione un año lectivo.',
            'idTerlec.integer' => 'Seleccione un año lectivo válido.',
            'idTerlec.in' => 'Seleccione un año lectivo válido. Si el desplegable está vacío, cargue ciclos en la tabla terlec.',
        ];
    }

    public function login()
    {
        $this->dni = DniInput::digitsOnly($this->dni);

        $this->sugerirUltimoAccesoDesdeDni();

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

            // No regenerar aquí (POST Livewire): en navegadores nuevos la cookie puede no
            // aplicarse antes del redirect. Ver RegenerarSesionPostLogin.
            session(['auth.pending_session_regenerate' => true]);

            // Guardar el contexto en sesión
            SchoolContext::set(
                idProfesor: $profesor->id,
                idNivel: (int) $this->idNivel,
                idTerlec: (int) $this->idTerlec,
            );

            // Actualizar último nivel/año para TODOS los registros del mismo DNI
            // (hay usuarios con múltiples filas en `profesores`, una por nivel).
            if (Schema::hasColumn('profesores', 'ult_idNivel') && Schema::hasColumn('profesores', 'ult_idTerlec')) {
                Profesor::query()->where('dni', $profesor->dni)->update([
                    'ult_idNivel' => (int) $this->idNivel,
                    'ult_idTerlec' => (int) $this->idTerlec,
                ]);
            }

            RateLimiter::clear($throttleKey);

            // Redirección completa (no SPA wire:navigate) para garantizar
            // que las cookies de sesión se propaguen correctamente
            return $this->redirectRoute(ProfesorMenuPortal::rutaInicio($profesor), navigate: false);
        }

        RateLimiter::hit($throttleKey, 60);

        $this->registrarErrorAutenticacion();
    }

    private function registrarErrorAutenticacion(): void
    {
        $nivelSeleccionado = (int) $this->idNivel;
        $legajos = Profesor::query()
            ->where('dni', $this->dni)
            ->orderBy('id')
            ->get(['id', 'nivel', 'pwrd']);

        $nivelesConClaveValida = $legajos
            ->filter(fn (Profesor $p) => ProfesorUserProvider::verificarPassword($p, $this->pwrd))
            ->map(fn (Profesor $p) => (int) ($p->nivel ?? 0))
            ->filter(fn (int $n) => $n > 0)
            ->unique()
            ->values();

        if ($nivelesConClaveValida->isNotEmpty() && ! $nivelesConClaveValida->contains($nivelSeleccionado)) {
            $nombres = NivelSistema::nivelesParaLogin()
                ->whereIn('id', $nivelesConClaveValida->all())
                ->pluck('nivel')
                ->filter()
                ->unique()
                ->values();

            $lista = $nombres->isNotEmpty()
                ? $nombres->implode('», «')
                : $nivelesConClaveValida->implode(', ');

            $this->addError(
                'idNivel',
                "La contraseña es correcta, pero el nivel seleccionado no coincide con su legajo. Pruebe con: «{$lista}».",
            );

            return;
        }

        $this->addError('dni', 'DNI o contraseña incorrectos. Verifique sus datos.');
    }

    public function cerrarMensajeBloqueoDocenteTerlec(): void
    {
        $this->mensajeBloqueoDocenteTerlec = null;
    }

    public function render()
    {
        $niveles = NivelSistema::nivelesParaLogin();

        return view('livewire.auth.login', compact('niveles'))
            ->layout('layouts.guest');
    }
}
