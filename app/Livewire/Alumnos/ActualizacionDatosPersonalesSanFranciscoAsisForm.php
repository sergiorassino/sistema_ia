<?php

namespace App\Livewire\Alumnos;

use App\Livewire\Alumnos\Concerns\ConFotoCarnetActualizacionDatos;
use App\Models\Legajo;
use App\Support\Alumnos\ActualizacionDatosPersonalesComun;
use App\Support\Alumnos\ActualizacionDatosPersonalesSanFranciscoAsis;
use App\Support\DniInput;
use App\Support\MatriculaWeb\MatriculaWebDocumentos;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

/**
 * Actualización de datos personales — variante San Francisco de Asís (completo con documentos).
 */
class ActualizacionDatosPersonalesSanFranciscoAsisForm extends Component
{
    use ConFotoCarnetActualizacionDatos;
    use WithFileUploads;

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

    public string $respAdmiNom = '';

    public string $respAdmiDni = '';

    /** @var array<string, bool> */
    public array $aceptaciones = [];

    public bool $bloqueado = false;

    public string $mensajeBloqueo = '';

    public bool $mostrarAvisoDocumentosPendientes = false;

    public bool $mostrarAvisoCamposIncompletos = false;

    /** @var list<array{campo: string, etiqueta: string}> */
    public array $camposIncompletosAviso = [];

    public function updated(string $property): void
    {
        $campos = array_keys(ActualizacionDatosPersonalesSanFranciscoAsis::etiquetasCampos());
        if (! in_array($property, $campos, true) || ! property_exists($this, $property)) {
            return;
        }

        if ($property === 'respAdmiDni') {
            $this->respAdmiDni = DniInput::digitsOnly($this->respAdmiDni);
        } elseif (is_string($this->{$property})) {
            $this->{$property} = ActualizacionDatosPersonalesComun::normalizarTextoInput($this->{$property});
        }

        $this->resetValidation($property);
        if ($this->getErrorBag()->isEmpty()) {
            $this->mostrarAvisoCamposIncompletos = false;
            $this->camposIncompletosAviso = [];
        }
    }

    public function mount(): void
    {
        abort_unless(tenantAutogestionActualizacionDatosHabilitada(), 404);
        abort_unless(tenantAutogestionActualizacionDatosImplementacion() === 'sanfranciscoasis', 404);

        $ctx = ActualizacionDatosPersonalesSanFranciscoAsis::contexto();
        if ($ctx === null) {
            abort(404, 'No se encontró la matrícula del ciclo de autogestión.');
        }

        $legajo = $ctx['legajo'];
        $matricula = $ctx['matricula'];

        $this->apellido = (string) ($legajo->apellido ?? '');
        $this->nombre = (string) ($legajo->nombre ?? '');
        $this->dni = (string) ($legajo->dni ?? '');
        $estadoBloqueo = ActualizacionDatosPersonalesSanFranciscoAsis::estadoBloqueo($matricula);
        $this->bloqueado = $estadoBloqueo['bloqueado'];
        $this->mensajeBloqueo = $estadoBloqueo['mensaje'];

        foreach (ActualizacionDatosPersonalesSanFranciscoAsis::atributosDesdeLegajo($legajo) as $k => $v) {
            if (property_exists($this, $k)) {
                $this->{$k} = (string) $v;
            }
        }

        $this->montarFotoCarnetDesdeLegajo($legajo);
        $this->aceptaciones = ActualizacionDatosPersonalesSanFranciscoAsis::aceptacionesDesdeMatricula($matricula);
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

        $ctx = ActualizacionDatosPersonalesSanFranciscoAsis::contexto();
        if ($ctx === null) {
            return;
        }

        ActualizacionDatosPersonalesSanFranciscoAsis::marcarAceptacion($ctx['matricula'], $clave, false);
        $ctx['matricula']->refresh();
        $this->aceptaciones = ActualizacionDatosPersonalesSanFranciscoAsis::aceptacionesDesdeMatricula($ctx['matricula']);
    }

    public function guardar(): void
    {
        if ($this->bloqueado) {
            $this->addError('reglamApenom', $this->mensajeBloqueo !== ''
                ? $this->mensajeBloqueo
                : 'La actualización de datos no está habilitada. Contacte a secretaría.');

            return;
        }

        $key = 'alumnos-act-datos-sfa:'.(auth('alumno')->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('reglamApenom', 'Demasiados intentos. Espere un momento.');

            return;
        }

        $ctx = ActualizacionDatosPersonalesSanFranciscoAsis::contexto();
        if ($ctx === null) {
            abort(404);
        }

        $matricula = $ctx['matricula']->fresh();
        if (! ActualizacionDatosPersonalesSanFranciscoAsis::todasAceptadas($matricula)) {
            $this->mostrarAvisoDocumentosPendientes = true;

            return;
        }

        $keys = array_keys(ActualizacionDatosPersonalesSanFranciscoAsis::atributosDesdeLegajo($ctx['legajo']));
        foreach ($keys as $campo) {
            if (! property_exists($this, $campo) || ! is_string($this->{$campo})) {
                continue;
            }

            if ($campo === 'respAdmiDni') {
                $this->{$campo} = DniInput::digitsOnly($this->{$campo});

                continue;
            }

            $this->{$campo} = ActualizacionDatosPersonalesComun::normalizarTextoInput($this->{$campo});
        }
        $validator = Validator::make(
            $this->only($keys),
            ActualizacionDatosPersonalesSanFranciscoAsis::reglasValidacion($this->needes),
            ActualizacionDatosPersonalesSanFranciscoAsis::mensajesValidacion(),
        );

        if ($validator->fails()) {
            $this->resetErrorBag();
            foreach ($validator->errors()->messages() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError($campo, $mensaje);
                }
            }
            $this->camposIncompletosAviso = ActualizacionDatosPersonalesSanFranciscoAsis::camposIncompletosDesdeErrores($validator->errors());
            $this->mostrarAvisoCamposIncompletos = true;

            return;
        }

        if (! $this->validarFotoCarnetAntesDeGuardar()) {
            return;
        }

        RateLimiter::hit($key, 120);

        $state = $this->only($keys);

        try {
            ActualizacionDatosPersonalesSanFranciscoAsis::guardar($ctx['legajo'], $matricula, $state);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'documentos institucionales')) {
                $this->mostrarAvisoDocumentosPendientes = true;

                return;
            }
            $this->addError('reglamApenom', $e->getMessage());

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->addError('reglamApenom', 'No se pudieron guardar los datos. Intente nuevamente o contacte a secretaría.');

            return;
        }

        $legajo = Legajo::query()->findOrFail((int) $ctx['legajo']->id);

        if (! $this->persistirFotoCarnetTrasGuardar($legajo)) {
            return;
        }

        foreach (ActualizacionDatosPersonalesSanFranciscoAsis::atributosParaFormulario($legajo) as $k => $v) {
            if (property_exists($this, $k)) {
                $this->{$k} = $v;
            }
        }

        $this->resetErrorBag();
        $this->mostrarAvisoCamposIncompletos = false;
        $this->camposIncompletosAviso = [];
        $this->mostrarAvisoDocumentosPendientes = false;

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
                'disponible' => ActualizacionDatosPersonalesSanFranciscoAsis::documentoDisponible($clave),
            ];
        }

        return view('livewire.alumnos.actualizacion-datos-personales-sanfranciscoasis-form', array_merge([
            'documentos' => $documentos,
            'esSecundario' => studentEsNivelSecundario(),
            'textoCompromiso' => ActualizacionDatosPersonalesSanFranciscoAsis::TEXTO_COMPROMISO_PARENTAL,
        ], $this->datosVistaFotoCarnet()))->layout('layouts.alumno', ['pageTitle' => 'Actualización de Datos Personales']);
    }
}
