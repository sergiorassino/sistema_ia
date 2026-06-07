<?php

namespace App\Livewire\Alumnos;

use App\Models\Legajo;
use App\Support\Alumnos\ActualizacionDatosPersonalesEstandar;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

/**
 * Actualización de datos personales — variante estándar (padre y madre).
 */
class ActualizacionDatosPersonalesEstandarForm extends Component
{
    public string $apellido = '';

    public string $nombre = '';

    public string $dni = '';

    public string $nombrepad = '';

    public string $dnipad = '';

    public string $fechnacpad = '';

    public string $nacionpad = '';

    public string $domipad = '';

    public string $telepad = '';

    public string $emailpad = '';

    public string $ocupacpad = '';

    public string $telltp = '';

    public string $nombremad = '';

    public string $dnimad = '';

    public string $fechnacmad = '';

    public string $nacionmad = '';

    public string $domimad = '';

    public string $telemad = '';

    public string $emailmad = '';

    public string $ocupacmad = '';

    public string $telltm = '';

    public bool $bloqueado = false;

    public bool $mostrarAvisoCamposIncompletos = false;

    /** @var list<array{campo: string, etiqueta: string}> */
    public array $camposIncompletosAviso = [];

    /** @var list<string> */
    private const CAMPOS_EMAIL = ['emailpad', 'emailmad'];

    public function updated(string $property): void
    {
        if (in_array($property, self::CAMPOS_EMAIL, true)) {
            $this->resetValidation($property);
        }
    }

    public function mount(): void
    {
        abort_unless(tenantAutogestionActualizacionDatosHabilitada(), 404);
        abort_if(tenantAutogestionActualizacionDatosImplementacion() === 'sanfranciscoasis', 404);

        $ctx = ActualizacionDatosPersonalesEstandar::contexto();
        if ($ctx === null) {
            abort(404, 'No se encontró la matrícula del ciclo de autogestión.');
        }

        $legajo = $ctx['legajo'];

        $this->apellido = (string) ($legajo->apellido ?? '');
        $this->nombre = (string) ($legajo->nombre ?? '');
        $this->dni = (string) ($legajo->dni ?? '');
        $this->bloqueado = ActualizacionDatosPersonalesEstandar::estaBloqueado($legajo);

        foreach (ActualizacionDatosPersonalesEstandar::atributosDesdeLegajo($legajo) as $k => $v) {
            if (property_exists($this, $k)) {
                $this->{$k} = (string) $v;
            }
        }
    }

    public function cerrarAvisoCamposIncompletos(): void
    {
        $this->mostrarAvisoCamposIncompletos = false;
    }

    public function guardar(): void
    {
        if ($this->bloqueado) {
            $this->addError('nombrepad', 'La actualización de datos no está habilitada. Contacte a secretaría.');

            return;
        }

        $key = 'alumnos-act-datos-estandar:'.(auth('alumno')->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('nombrepad', 'Demasiados intentos. Espere un momento.');

            return;
        }

        $ctx = ActualizacionDatosPersonalesEstandar::contexto();
        if ($ctx === null) {
            abort(404);
        }

        $keys = array_keys(ActualizacionDatosPersonalesEstandar::atributosDesdeLegajo($ctx['legajo']));
        $validator = Validator::make(
            $this->only($keys),
            ActualizacionDatosPersonalesEstandar::reglasValidacion(),
            ActualizacionDatosPersonalesEstandar::mensajesValidacion(),
        );

        if ($validator->fails()) {
            $this->resetErrorBag();
            foreach ($validator->errors()->messages() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError($campo, $mensaje);
                }
            }
            $this->camposIncompletosAviso = ActualizacionDatosPersonalesEstandar::camposIncompletosDesdeErrores($validator->errors());
            $this->mostrarAvisoCamposIncompletos = true;

            return;
        }

        RateLimiter::hit($key, 120);

        $state = $this->only($keys);

        try {
            ActualizacionDatosPersonalesEstandar::guardar($ctx['legajo'], $state);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('nombrepad', 'No se pudieron guardar los datos. Intente nuevamente o contacte a secretaría.');

            return;
        }

        $legajo = Legajo::query()->findOrFail((int) $ctx['legajo']->id);
        foreach (ActualizacionDatosPersonalesEstandar::atributosParaFormulario($legajo) as $k => $v) {
            if (property_exists($this, $k)) {
                $this->{$k} = $v;
            }
        }

        $this->resetErrorBag();
        $this->mostrarAvisoCamposIncompletos = false;
        $this->camposIncompletosAviso = [];

        $this->dispatch('se-swal-exito', mensaje: 'Datos personales actualizados correctamente.');
    }

    public function render()
    {
        return view('livewire.alumnos.actualizacion-datos-personales-estandar-form')
            ->layout('layouts.alumno', ['pageTitle' => 'Actualización de Datos Personales']);
    }
}
