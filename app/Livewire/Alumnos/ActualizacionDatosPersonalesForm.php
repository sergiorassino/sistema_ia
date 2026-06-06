<?php

namespace App\Livewire\Alumnos;

use App\Models\Legajo;
use App\Support\Alumnos\ActualizacionDatosPersonales;
use App\Support\MatriculaWeb\MatriculaWebDocumentos;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

class ActualizacionDatosPersonalesForm extends Component
{
    public string $apellido = '';

    public string $nombre = '';

    public string $dni = '';

    public string $reglamApenom = '';

    public string $reglamDni = '';

    public string $reglamEmail = '';

    public string $fechnaci = '';

    public string $ln_depto = '';

    public string $ln_provincia = '';

    public string $ln_pais = '';

    public string $callenum = '';

    public string $barrio = '';

    public string $localidad = '';

    public string $telefono = '';

    public string $email = '';

    public string $escori = '';

    public string $needes = '';

    public string $needes_detalle = '';

    public string $nombrepad = '';

    public string $dnipad = '';

    public string $telepad = '';

    public string $emailpad = '';

    public string $ocupacpad = '';

    public string $lugtrapad = '';

    public string $telltp = '';

    public string $nombremad = '';

    public string $dnimad = '';

    public string $telemad = '';

    public string $emailmad = '';

    public string $ocupacmad = '';

    public string $lugtramad = '';

    public string $telltm = '';

    public string $nombretut = '';

    public string $dnitut = '';

    public string $teletut = '';

    public string $emailtut = '';

    public string $lugtratut = '';

    public string $telltt = '';

    public string $ec_padres = '';

    public string $vivecon = '';

    public string $contacto1 = '';

    public string $contacto2 = '';

    public string $contacto3 = '';

    public string $retira1 = '';

    public string $obs_web = '';

    /** @var array<string, bool> */
    public array $aceptaciones = [];

    public bool $bloqueado = false;

    public bool $mostrarAvisoDocumentosPendientes = false;

    public bool $mostrarAvisoCamposIncompletos = false;

    /** @var list<array{campo: string, etiqueta: string}> */
    public array $camposIncompletosAviso = [];

    /** @var list<string> */
    private const CAMPOS_EMAIL = ['reglamEmail', 'email', 'emailpad', 'emailmad', 'emailtut'];

    public function updated(string $property): void
    {
        if (in_array($property, self::CAMPOS_EMAIL, true)) {
            $this->resetValidation($property);
        }
    }

    public function mount(): void
    {
        abort_unless(tenantAutogestionActualizacionDatosHabilitada(), 404);

        $ctx = ActualizacionDatosPersonales::contexto();
        if ($ctx === null) {
            abort(404, 'No se encontró la matrícula del ciclo de autogestión.');
        }

        $legajo = $ctx['legajo'];
        $matricula = $ctx['matricula'];

        $this->apellido = (string) ($legajo->apellido ?? '');
        $this->nombre = (string) ($legajo->nombre ?? '');
        $this->dni = (string) ($legajo->dni ?? '');
        $this->bloqueado = ActualizacionDatosPersonales::estaBloqueado($legajo);

        foreach (ActualizacionDatosPersonales::atributosDesdeLegajo($legajo) as $k => $v) {
            if (property_exists($this, $k)) {
                $this->{$k} = (string) $v;
            }
        }

        $this->aceptaciones = ActualizacionDatosPersonales::aceptacionesDesdeMatricula($matricula);
    }

    public function updatedNeedes(string $value): void
    {
        if ($value !== 'si') {
            $this->needes_detalle = '';

            return;
        }

        $detalle = trim($this->needes_detalle);
        if ($detalle === '' || $detalle === '-') {
            $this->needes_detalle = '';
        }
    }

    public function cerrarAvisoDocumentosPendientes(): void
    {
        $this->mostrarAvisoDocumentosPendientes = false;
    }

    public function cerrarAvisoCamposIncompletos(): void
    {
        $this->mostrarAvisoCamposIncompletos = false;
    }

    public function revocarAceptacion(string $clave): void
    {
        if ($this->bloqueado || ! MatriculaWebDocumentos::claveValida($clave)) {
            return;
        }

        $ctx = ActualizacionDatosPersonales::contexto();
        if ($ctx === null) {
            return;
        }

        ActualizacionDatosPersonales::marcarAceptacion($ctx['matricula'], $clave, false);
        $ctx['matricula']->refresh();
        $this->aceptaciones = ActualizacionDatosPersonales::aceptacionesDesdeMatricula($ctx['matricula']);
    }

    public function guardar(): void
    {
        if ($this->bloqueado) {
            $this->addError('reglamApenom', 'La actualización de datos no está habilitada. Contacte a secretaría.');

            return;
        }

        $key = 'alumnos-act-datos:'.(auth('alumno')->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('reglamApenom', 'Demasiados intentos. Espere un momento.');

            return;
        }
        $ctx = ActualizacionDatosPersonales::contexto();
        if ($ctx === null) {
            abort(404);
        }

        $matricula = $ctx['matricula']->fresh();
        if (! ActualizacionDatosPersonales::todasAceptadas($matricula)) {
            $this->mostrarAvisoDocumentosPendientes = true;

            return;
        }

        $keys = array_keys(ActualizacionDatosPersonales::atributosDesdeLegajo($ctx['legajo']));
        $validator = Validator::make(
            $this->only($keys),
            ActualizacionDatosPersonales::reglasValidacion($this->needes),
            ActualizacionDatosPersonales::mensajesValidacion(),
        );

        if ($validator->fails()) {
            $this->resetErrorBag();
            foreach ($validator->errors()->messages() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError($campo, $mensaje);
                }
            }
            $this->camposIncompletosAviso = ActualizacionDatosPersonales::camposIncompletosDesdeErrores($validator->errors());
            $this->mostrarAvisoCamposIncompletos = true;

            return;
        }

        RateLimiter::hit($key, 120);

        $state = $this->only($keys);

        try {
            ActualizacionDatosPersonales::guardar($ctx['legajo'], $state);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('reglamApenom', 'No se pudieron guardar los datos. Intente nuevamente o contacte a secretaría.');

            return;
        }

        $legajo = Legajo::query()->findOrFail((int) $ctx['legajo']->id);
        foreach (ActualizacionDatosPersonales::atributosParaFormulario($legajo) as $k => $v) {
            if (property_exists($this, $k)) {
                $this->{$k} = $v;
            }
        }

        $this->dispatch('se-swal-exito', mensaje: 'Datos personales actualizados correctamente.');
    }

    public function render()
    {
        $definiciones = MatriculaWebDocumentos::definiciones();
        $documentos = [];
        foreach (MatriculaWebDocumentos::claves() as $clave) {
            $documentos[$clave] = [
                'def' => $definiciones[$clave],
                'aceptado' => (bool) ($this->aceptaciones[$clave] ?? false),
                'disponible' => ActualizacionDatosPersonales::documentoDisponible($clave),
            ];
        }

        return view('livewire.alumnos.actualizacion-datos-personales-form', [
            'documentos' => $documentos,
            'esSecundario' => studentEsNivelSecundario(),
            'textoCompromiso' => ActualizacionDatosPersonales::TEXTO_COMPROMISO_PARENTAL,
        ])->layout('layouts.alumno', ['pageTitle' => 'Actualización de Datos Personales']);
    }
}
