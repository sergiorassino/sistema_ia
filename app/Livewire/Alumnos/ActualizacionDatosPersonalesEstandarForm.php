<?php

namespace App\Livewire\Alumnos;

use App\Models\Legajo;
use App\Support\Alumnos\ActualizacionDatosPersonalesEstandar;
use App\Support\Alumnos\DocumentosEstudianteAutogestion;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;

/**
 * Actualización de datos personales — variante estándar (padre, madre y tutor).
 */
class ActualizacionDatosPersonalesEstandarForm extends Component
{
    use WithFileUploads;

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

    public string $nombretut = '';

    public string $dnitut = '';

    public string $teletut = '';

    public string $emailtut = '';

    public string $ocupactut = '';

    public bool $bloqueado = false;

    public bool $mostrarAvisoCamposIncompletos = false;

    /** @var list<array{campo: string, etiqueta: string}> */
    public array $camposIncompletosAviso = [];

    /** @var array<string, array{existe: bool, path: ?string, nombre: string, actualizado_en: ?string}> */
    public array $estadoDocumentos = [];

    /**
     * Archivos por tipo y slot: clave => [ índice => TemporaryUploadedFile ].
     *
     * @var array<string, array<int, TemporaryUploadedFile|null>>
     */
    public array $archivosDocumento = [];

    /**
     * Incrementa al limpiar inputs file para forzar re-render (wire:key).
     *
     * @var array<string, int>
     */
    public array $revisionInputsDocumento = [];

    /** @var list<string> */
    private const CAMPOS_EMAIL = ['emailpad', 'emailmad', 'emailtut'];

    public function updated(string $property): void
    {
        if (in_array($property, self::CAMPOS_EMAIL, true)) {
            $this->resetValidation($property);
        }

        if (preg_match('/^archivosDocumento\.([^.]+)\.(\d+)$/', $property, $coincidencias) === 1) {
            $this->validarArchivoDocumentoSlotEnVivo($coincidencias[1], (int) $coincidencias[2]);
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

        $this->refrescarEstadoDocumentos();
    }

    public function cerrarAvisoCamposIncompletos(): void
    {
        $this->mostrarAvisoCamposIncompletos = false;
    }

    public function subirDocumento(string $clave): void
    {
        if ($this->bloqueado) {
            $this->addError('archivosDocumento.'.$clave, 'La subida de documentos no está habilitada. Contacte a secretaría.');

            return;
        }

        if (! DocumentosEstudianteAutogestion::habilitadoConTipos()) {
            return;
        }

        if (! DocumentosEstudianteAutogestion::claveValida($clave)) {
            $this->addError('archivosDocumento.'.$clave, 'Tipo de documento no válido.');

            return;
        }

        $key = 'alumnos-doc-estudiante:'.$clave.':'.(auth('alumno')->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 15)) {
            $this->addError('archivosDocumento.'.$clave, 'Demasiados intentos. Espere un momento.');

            return;
        }

        $slots = $this->archivosDocumento[$clave] ?? [];
        if (! is_array($slots)) {
            $slots = [];
        }

        $archivos = DocumentosEstudianteAutogestion::archivosDesdeSlots($slots);
        $error = DocumentosEstudianteAutogestion::validarArchivos($clave, $archivos);
        if ($error !== null) {
            $this->addError('archivosDocumento.'.$clave, $error);

            return;
        }

        RateLimiter::hit($key, 120);

        try {
            DocumentosEstudianteAutogestion::guardarDesdeUploads($this->dni, $clave, $archivos);
        } catch (\InvalidArgumentException $e) {
            $this->addError('archivosDocumento.'.$clave, $e->getMessage());

            return;
        } catch (\RuntimeException $e) {
            report($e);
            $this->addError('archivosDocumento.'.$clave, self::mensajeErrorDocumentoParaUsuario($e));

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->addError('archivosDocumento.'.$clave, 'No se pudo guardar el documento. Intente nuevamente.');

            return;
        }

        $this->limpiarSeleccionArchivosDocumento($clave);
        $this->refrescarEstadoDocumentos();

        $def = DocumentosEstudianteAutogestion::definicion($clave);
        $label = $def['label'] ?? 'Documento';
        $this->dispatch('se-swal-exito', mensaje: $label.' guardado correctamente.');
    }

    public function eliminarDocumento(string $clave): void
    {
        if ($this->bloqueado) {
            $this->addError('archivosDocumento.'.$clave, 'No puede eliminar documentos. Contacte a secretaría.');

            return;
        }

        if (! DocumentosEstudianteAutogestion::habilitadoConTipos()) {
            return;
        }

        if (! DocumentosEstudianteAutogestion::claveValida($clave)) {
            $this->addError('archivosDocumento.'.$clave, 'Tipo de documento no válido.');

            return;
        }

        $ctx = ActualizacionDatosPersonalesEstandar::contexto();
        if ($ctx === null) {
            abort(404);
        }

        $dniLegajo = trim((string) ($ctx['legajo']->dni ?? ''));
        if ($dniLegajo === '' || DocumentosEstudianteAutogestion::dniSanitizado($dniLegajo) !== DocumentosEstudianteAutogestion::dniSanitizado($this->dni)) {
            abort(403);
        }

        $key = 'alumnos-doc-estudiante-del:'.$clave.':'.(auth('alumno')->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('archivosDocumento.'.$clave, 'Demasiados intentos. Espere un momento.');

            return;
        }

        if (! (DocumentosEstudianteAutogestion::estadoDocumento($this->dni, $clave)['existe'] ?? false)) {
            $this->addError('archivosDocumento.'.$clave, 'No hay un documento subido para eliminar.');
            $this->refrescarEstadoDocumentos();

            return;
        }

        RateLimiter::hit($key, 120);

        try {
            DocumentosEstudianteAutogestion::eliminar($this->dni, $clave);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('archivosDocumento.'.$clave, 'No se pudo eliminar el documento. Intente nuevamente.');

            return;
        }

        $this->limpiarSeleccionArchivosDocumento($clave);
        $this->refrescarEstadoDocumentos();

        $def = DocumentosEstudianteAutogestion::definicion($clave);
        $label = $def['label'] ?? 'Documento';
        $this->dispatch('se-swal-exito', mensaje: $label.' eliminado correctamente.');
    }

    public function guardar(): void
    {
        if ($this->bloqueado) {
            $this->addError('nombrepad', 'La actualización de datos no está habilitada. Contacte a secretaría.');

            return;
        }

        if (DocumentosEstudianteAutogestion::habilitadoConTipos()) {
            $pendientes = DocumentosEstudianteAutogestion::obligatoriosPendientes($this->dni);
            if ($pendientes !== []) {
                foreach ($pendientes as $item) {
                    $this->addError(
                        'archivosDocumento.'.$item['clave'],
                        'Debe subir: '.$item['label'].'.',
                    );
                }

                return;
            }
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

    private function validarArchivoDocumentoSlotEnVivo(string $clave, int $indice): void
    {
        if (! DocumentosEstudianteAutogestion::claveValida($clave)) {
            return;
        }

        $campo = 'archivosDocumento.'.$clave.'.'.$indice;
        $this->resetValidation($campo);

        $archivo = $this->archivosDocumento[$clave][$indice] ?? null;
        if (! $archivo instanceof TemporaryUploadedFile) {
            return;
        }

        $error = DocumentosEstudianteAutogestion::validarArchivoIndividual($clave, $archivo);
        if ($error !== null) {
            $this->addError($campo, $error);
            unset($this->archivosDocumento[$clave][$indice]);
        }
    }

    private function limpiarSeleccionArchivosDocumento(string $clave): void
    {
        unset($this->archivosDocumento[$clave]);
        $this->revisionInputsDocumento[$clave] = ($this->revisionInputsDocumento[$clave] ?? 0) + 1;

        $this->resetValidation('archivosDocumento.'.$clave);

        $def = DocumentosEstudianteAutogestion::definicion($clave);
        if ($def === null) {
            return;
        }

        for ($i = 0; $i < $def['max_archivos']; $i++) {
            $this->resetValidation('archivosDocumento.'.$clave.'.'.$i);
        }
    }

    private static function mensajeErrorDocumentoParaUsuario(\RuntimeException $e): string
    {
        $mensaje = trim($e->getMessage());
        $mensajesSeguros = [
            'El archivo temporal ya no está disponible. Vuelva a seleccionarlo.',
            'Uno de los archivos temporales ya no está disponible. Vuelva a seleccionarlos.',
            'No se pudo leer el PDF seleccionado.',
            'No se pudo generar el PDF final.',
            'El PDF no pudo leerse o está protegido.',
            'La imagen no es válida.',
            'La imagen no tiene dimensiones válidas.',
            'Archivo no encontrado:',
        ];

        foreach ($mensajesSeguros as $seguro) {
            if ($mensaje === $seguro || str_starts_with($mensaje, $seguro)) {
                return $mensaje;
            }
        }

        return 'No se pudo guardar el documento. Intente nuevamente.';
    }

    private function refrescarEstadoDocumentos(): void
    {
        if (! DocumentosEstudianteAutogestion::habilitadoConTipos()) {
            $this->estadoDocumentos = [];

            return;
        }

        $this->estadoDocumentos = DocumentosEstudianteAutogestion::estadoTodos(
            $this->dni,
            (int) (studentCtx()->idLegajo ?? 0),
        );
    }

    public function render()
    {
        return view('livewire.alumnos.actualizacion-datos-personales-estandar-form', [
            'documentosEstudianteHabilitados' => DocumentosEstudianteAutogestion::habilitadoConTipos(),
            'tiposDocumentoEstudiante' => DocumentosEstudianteAutogestion::tiposConfigurados(),
        ])->layout('layouts.alumno', ['pageTitle' => 'Actualización de Datos Personales']);
    }
}
