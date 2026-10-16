<?php

namespace App\Livewire\Alumnos;

use App\Livewire\Alumnos\Concerns\ConFotoCarnetActualizacionDatos;
use App\Models\Legajo;
use App\Support\Alumnos\ActualizacionDatosPersonalesComun;
use App\Support\Alumnos\ActualizacionDatosPersonalesEstandar;
use App\Support\Alumnos\DocumentosEstudianteAutogestion;
use App\Support\DniInput;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;

/**
 * Actualización de datos personales — variante estándar (padre, madre y tutor).
 */
class ActualizacionDatosPersonalesEstandarForm extends Component
{
    use ConFotoCarnetActualizacionDatos;
    use WithFileUploads;

    public string $apellido = '';

    public string $nombre = '';

    public string $dni = '';

    public string $cursoEstudiante = '';

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

    public string $respAdmiNom = '';

    public string $respAdmiDni = '';

    public bool $bloqueado = false;

    public string $mensajeBloqueo = '';

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

    public bool $vistaDocumentoAbierta = false;

    public string $vistaDocumentoTitulo = '';

    public string $vistaDocumentoFecha = '';

    #[Locked]
    public string $vistaDocumentoSrc = '';

    public bool $vistaDocumentoEsPdf = false;

    public bool $vistaDocumentoPuedeRotar = false;

    public string $vistaDocumentoRotacionAviso = '';

    public int $vistaDocumentoRevision = 0;

    #[Locked]
    public string $vistaDocumentoClave = '';

    #[Locked]
    public int $vistaDocumentoIndice = 1;

    public function updated(string $property): void
    {
        if (preg_match('/^archivosDocumento\.([^.]+)$/', $property, $coincidencias) === 1) {
            $this->subirDocumento($coincidencias[1]);

            return;
        }

        if (preg_match('/^archivosDocumento\.([^.]+)\.(\d+)$/', $property, $coincidencias) === 1) {
            $this->validarArchivoDocumentoSlotEnVivo($coincidencias[1], (int) $coincidencias[2]);

            return;
        }

        $campos = array_keys(ActualizacionDatosPersonalesEstandar::etiquetasCampos());
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
        abort_if(tenantAutogestionActualizacionDatosImplementacion() === 'sanfranciscoasis', 404);

        $sinMatricula = ActualizacionDatosPersonalesComun::avisoSinMatriculaAnioSiguiente();
        if ($sinMatricula !== null) {
            $legajo = $sinMatricula['legajo'];
            $this->apellido = (string) ($legajo->apellido ?? '');
            $this->nombre = (string) ($legajo->nombre ?? '');
            $this->dni = (string) ($legajo->dni ?? '');
            $this->bloqueado = true;
            $this->mensajeBloqueo = $sinMatricula['mensaje'];

            return;
        }

        $ctx = ActualizacionDatosPersonalesEstandar::contexto();
        if ($ctx === null) {
            abort(404, 'No se encontró la matrícula del ciclo de autogestión.');
        }

        $legajo = $ctx['legajo'];

        $this->apellido = (string) ($legajo->apellido ?? '');
        $this->nombre = (string) ($legajo->nombre ?? '');
        $this->dni = (string) ($legajo->dni ?? '');
        $this->cursoEstudiante = trim((string) ($ctx['matricula']->curso?->nombreParaListado() ?? ''));
        $estadoBloqueo = ActualizacionDatosPersonalesEstandar::estadoBloqueo($ctx['matricula']);
        $this->bloqueado = $estadoBloqueo['bloqueado'];
        $this->mensajeBloqueo = $estadoBloqueo['mensaje'];

        foreach (ActualizacionDatosPersonalesEstandar::atributosDesdeLegajo($legajo) as $k => $v) {
            if (property_exists($this, $k)) {
                $this->{$k} = (string) $v;
            }
        }

        $this->montarFotoCarnetDesdeLegajo($legajo);
        $this->refrescarEstadoDocumentos();
    }

    public function cerrarAvisoCamposIncompletos(): void
    {
        $this->mostrarAvisoCamposIncompletos = false;
    }

    public function subirDocumento(string $clave): void
    {
        if ($this->bloqueado) {
            $this->addError('archivosDocumento.'.$clave, $this->mensajeBloqueo !== ''
                ? $this->mensajeBloqueo
                : 'La subida de documentos no está habilitada. Contacte a secretaría.');

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
        if ($slots instanceof TemporaryUploadedFile) {
            $slots = [$slots];
        } elseif (! is_array($slots)) {
            $slots = [];
        }

        $archivos = DocumentosEstudianteAutogestion::archivosDesdeSlots($slots);
        if ($archivos === []) {
            return;
        }

        if ((DocumentosEstudianteAutogestion::estadoDocumento($this->dni, $clave)['existe'] ?? false) === true) {
            $this->addError('archivosDocumento.'.$clave, 'Elimine el documento actual antes de subir otro.');
            $this->reiniciarInputDocumento($clave);

            return;
        }

        $error = DocumentosEstudianteAutogestion::validarArchivos($clave, $archivos);
        if ($error !== null) {
            $this->addError('archivosDocumento.'.$clave, $error);
            $this->reiniciarInputDocumento($clave);

            return;
        }

        RateLimiter::hit($key, 120);

        try {
            DocumentosEstudianteAutogestion::guardarDesdeUploads($this->dni, $clave, $archivos);
        } catch (\InvalidArgumentException $e) {
            $this->addError('archivosDocumento.'.$clave, $e->getMessage());
            $this->reiniciarInputDocumento($clave);

            return;
        } catch (\RuntimeException $e) {
            report($e);
            $this->addError('archivosDocumento.'.$clave, self::mensajeErrorDocumentoParaUsuario($e));
            $this->reiniciarInputDocumento($clave);

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->addError('archivosDocumento.'.$clave, 'No se pudo guardar el documento. Intente nuevamente.');
            $this->reiniciarInputDocumento($clave);

            return;
        }

        $this->limpiarSeleccionArchivosDocumento($clave);
        $this->cerrarVistaDocumento();
        $this->refrescarEstadoDocumentos();

        $def = DocumentosEstudianteAutogestion::definicion($clave);
        $label = $def['label'] ?? 'Documento';
        $this->dispatch('se-swal-exito', mensaje: $label.' guardado correctamente.');
    }

    public function eliminarDocumento(string $clave): void
    {
        if ($this->bloqueado) {
            $this->addError('archivosDocumento.'.$clave, $this->mensajeBloqueo !== ''
                ? $this->mensajeBloqueo
                : 'No puede eliminar documentos. Contacte a secretaría.');

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
        $this->cerrarVistaDocumento();
        $this->refrescarEstadoDocumentos();

        $def = DocumentosEstudianteAutogestion::definicion($clave);
        $label = $def['label'] ?? 'Documento';
        $this->dispatch('se-swal-exito', mensaje: $label.' eliminado correctamente.');
    }

    public function abrirVistaDocumento(string $clave, int $indice): void
    {
        if ($this->bloqueado) {
            return;
        }

        if (! DocumentosEstudianteAutogestion::claveValida($clave)) {
            return;
        }

        $indice = max(1, $indice);
        $idLegajo = (int) (studentCtx()->idLegajo ?? 0);
        $vista = DocumentosEstudianteAutogestion::srcVista($this->dni, $clave, $idLegajo, $indice);
        if ($vista === null) {
            $this->addError('archivosDocumento.'.$clave, 'No hay un documento subido para visualizar.');

            return;
        }

        $def = DocumentosEstudianteAutogestion::definicion($clave);
        $titulo = $def['label'] ?? 'Documento';
        if ($indice > 1) {
            $titulo .= ' · archivo '.$indice;
        }

        $fecha = '';
        $estado = DocumentosEstudianteAutogestion::estadoDocumento($this->dni, $clave, $idLegajo);
        if (! empty($estado['actualizado_en'])) {
            $fecha = (string) $estado['actualizado_en'];
        }

        $this->vistaDocumentoTitulo = $titulo;
        $this->vistaDocumentoFecha = $fecha;
        $this->vistaDocumentoEsPdf = $vista['es_pdf'];
        $this->vistaDocumentoSrc = $vista['src'];
        $this->vistaDocumentoPuedeRotar = ! $vista['es_pdf'] && ! $this->bloqueado;
        $this->vistaDocumentoRotacionAviso = '';
        $this->vistaDocumentoClave = $clave;
        $this->vistaDocumentoIndice = $indice;
        $this->vistaDocumentoAbierta = true;
    }

    public function rotarVistaDocumento(): void
    {
        if ($this->bloqueado || ! $this->vistaDocumentoAbierta || $this->vistaDocumentoEsPdf || $this->vistaDocumentoClave === '') {
            return;
        }

        if (! DocumentosEstudianteAutogestion::claveValida($this->vistaDocumentoClave)) {
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

        $clave = $this->vistaDocumentoClave;
        $indice = max(1, $this->vistaDocumentoIndice);
        $key = 'alumnos-doc-estudiante-rot:'.$clave.':'.(auth('alumno')->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        RateLimiter::hit($key, 60);

        try {
            DocumentosEstudianteAutogestion::rotarImagenGuardada($dniLegajo, $clave, $indice);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('se-swal-error', mensaje: 'No se pudo rotar la imagen. Intente nuevamente.');

            return;
        }

        $idLegajo = (int) ($ctx['legajo']->id ?? studentCtx()->idLegajo ?? 0);
        $vista = DocumentosEstudianteAutogestion::srcVista($dniLegajo, $clave, $idLegajo, $indice);
        if ($vista === null || $vista['es_pdf']) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo mostrar la imagen rotada.');

            return;
        }

        $this->vistaDocumentoSrc = $vista['src'];
        $this->vistaDocumentoRevision++;
        $this->vistaDocumentoRotacionAviso = 'Orientación guardada.';

        $estado = DocumentosEstudianteAutogestion::estadoDocumento($dniLegajo, $clave, $idLegajo);
        if (! empty($estado['actualizado_en'])) {
            $this->vistaDocumentoFecha = (string) $estado['actualizado_en'];
        }

        $this->refrescarEstadoDocumentos();
        $this->dispatch('documento-estudiante-rotado');
    }

    public function cerrarVistaDocumento(): void
    {
        $this->vistaDocumentoAbierta = false;
        $this->vistaDocumentoTitulo = '';
        $this->vistaDocumentoFecha = '';
        $this->vistaDocumentoSrc = '';
        $this->vistaDocumentoEsPdf = false;
        $this->vistaDocumentoPuedeRotar = false;
        $this->vistaDocumentoRotacionAviso = '';
        $this->vistaDocumentoClave = '';
        $this->vistaDocumentoIndice = 1;
    }

    public function guardar(): void
    {
        if ($this->bloqueado) {
            $this->addError('nombrepad', $this->mensajeBloqueo !== ''
                ? $this->mensajeBloqueo
                : 'La actualización de datos no está habilitada. Contacte a secretaría.');

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
        $this->normalizarCamposTexto($keys);
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

        if (! $this->validarFotoCarnetAntesDeGuardar()) {
            return;
        }

        RateLimiter::hit($key, 120);

        $state = $this->only($keys);

        try {
            ActualizacionDatosPersonalesEstandar::guardar($ctx['legajo'], $state);
        } catch (\RuntimeException $e) {
            $this->addError('nombrepad', $e->getMessage());

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->addError('nombrepad', 'No se pudieron guardar los datos. Intente nuevamente o contacte a secretaría.');

            return;
        }

        $legajo = Legajo::query()->findOrFail((int) $ctx['legajo']->id);

        if (! $this->persistirFotoCarnetTrasGuardar($legajo)) {
            return;
        }

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

    /**
     * @param  list<string>  $keys
     */
    private function normalizarCamposTexto(array $keys): void
    {
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

    private function reiniciarInputDocumento(string $clave): void
    {
        unset($this->archivosDocumento[$clave]);
        $this->revisionInputsDocumento[$clave] = ($this->revisionInputsDocumento[$clave] ?? 0) + 1;
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
            'No se pudo comprimir la imagen.',
            'La imagen no es un JPG válido.',
            'La imagen no tiene dimensiones válidas.',
            'No se pudo guardar el documento en el servidor.',
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
        return view('livewire.alumnos.actualizacion-datos-personales-estandar-form', array_merge([
            'documentosEstudianteHabilitados' => DocumentosEstudianteAutogestion::habilitadoConTipos(),
            'tiposDocumentoEstudiante' => DocumentosEstudianteAutogestion::tiposConfigurados(),
        ], $this->datosVistaFotoCarnet()))->layout('layouts.alumno', ['pageTitle' => 'Actualización de Datos Personales']);
    }
}
