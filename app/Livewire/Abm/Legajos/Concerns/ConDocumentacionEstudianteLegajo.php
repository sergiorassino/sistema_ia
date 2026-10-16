<?php

namespace App\Livewire\Abm\Legajos\Concerns;

use App\Models\Legajo;
use App\Support\Alumnos\DocumentosEstudianteAutogestion;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Carga y consulta de la documentación del estudiante en el ABM de legajos.
 * Los archivos son los mismos que sube la familia en actualización de datos.
 */
trait ConDocumentacionEstudianteLegajo
{
    /**
     * @var array<string, TemporaryUploadedFile|array<int, TemporaryUploadedFile|null>|null>
     */
    public array $archivosDocumento = [];

    /**
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

    public function subirDocumento(string $clave): void
    {
        $this->requireModificarLegajo();

        if (! DocumentosEstudianteAutogestion::habilitadoConTipos()) {
            return;
        }

        if (! DocumentosEstudianteAutogestion::claveValida($clave)) {
            $this->addError('archivosDocumento.'.$clave, 'Tipo de documento no válido.');

            return;
        }

        $legajo = $this->legajoParaDocumentacion();
        $dni = trim((string) ($legajo->dni ?? ''));
        if (DocumentosEstudianteAutogestion::dniSanitizado($dni) === 'sin-dni') {
            $this->addError('archivosDocumento.'.$clave, 'Guarde el DNI del legajo antes de subir documentación.');

            return;
        }

        $key = 'legajos-doc-estudiante:'.$clave.':'.(auth()->id() ?? 'guest');
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

        if ((DocumentosEstudianteAutogestion::estadoDocumento($dni, $clave)['existe'] ?? false) === true) {
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
            DocumentosEstudianteAutogestion::guardarDesdeUploads($dni, $clave, $archivos);
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

        $def = DocumentosEstudianteAutogestion::definicion($clave);
        $label = $def['label'] ?? 'Documento';
        $this->dispatch('se-swal-exito', mensaje: $label.' guardado correctamente.');
    }

    public function eliminarDocumento(string $clave): void
    {
        $this->requireModificarLegajo();

        if (! DocumentosEstudianteAutogestion::habilitadoConTipos()) {
            return;
        }

        if (! DocumentosEstudianteAutogestion::claveValida($clave)) {
            $this->addError('archivosDocumento.'.$clave, 'Tipo de documento no válido.');

            return;
        }

        $legajo = $this->legajoParaDocumentacion();
        $dni = trim((string) ($legajo->dni ?? ''));
        if (DocumentosEstudianteAutogestion::dniSanitizado($dni) === 'sin-dni') {
            $this->addError('archivosDocumento.'.$clave, 'El legajo no tiene DNI guardado.');

            return;
        }

        $key = 'legajos-doc-estudiante-del:'.$clave.':'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('archivosDocumento.'.$clave, 'Demasiados intentos. Espere un momento.');

            return;
        }

        if (! (DocumentosEstudianteAutogestion::estadoDocumento($dni, $clave)['existe'] ?? false)) {
            $this->addError('archivosDocumento.'.$clave, 'No hay un documento subido para eliminar.');

            return;
        }

        RateLimiter::hit($key, 120);

        try {
            DocumentosEstudianteAutogestion::eliminar($dni, $clave);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('archivosDocumento.'.$clave, 'No se pudo eliminar el documento. Intente nuevamente.');

            return;
        }

        $this->limpiarSeleccionArchivosDocumento($clave);
        $this->cerrarVistaDocumento();

        $def = DocumentosEstudianteAutogestion::definicion($clave);
        $label = $def['label'] ?? 'Documento';
        $this->dispatch('se-swal-exito', mensaje: $label.' eliminado correctamente.');
    }

    public function abrirVistaDocumento(string $clave, int $indice): void
    {
        abort_unless(puedeConsultarLegajosEstudiantes(), 403);

        if (! DocumentosEstudianteAutogestion::claveValida($clave)) {
            return;
        }

        $legajo = $this->legajoParaDocumentacion();
        $dni = trim((string) ($legajo->dni ?? ''));
        $indice = max(1, $indice);
        $vista = DocumentosEstudianteAutogestion::srcVista(
            $dni,
            $clave,
            (int) $legajo->id,
            $indice,
            DocumentosEstudianteAutogestion::AUDIENCIA_LEGAJO,
        );
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
        $estado = DocumentosEstudianteAutogestion::estadoDocumento($dni, $clave, (int) $legajo->id);
        if (! empty($estado['actualizado_en'])) {
            $fecha = (string) $estado['actualizado_en'];
        }

        $this->vistaDocumentoTitulo = $titulo;
        $this->vistaDocumentoFecha = $fecha;
        $this->vistaDocumentoEsPdf = $vista['es_pdf'];
        $this->vistaDocumentoSrc = $vista['src'];
        $this->vistaDocumentoPuedeRotar = ! $vista['es_pdf'] && puedeModificarLegajosEstudiantes();
        $this->vistaDocumentoRotacionAviso = '';
        $this->vistaDocumentoClave = $clave;
        $this->vistaDocumentoIndice = $indice;
        $this->vistaDocumentoAbierta = true;
    }

    public function rotarVistaDocumento(): void
    {
        $this->requireModificarLegajo();

        if (! $this->vistaDocumentoAbierta || $this->vistaDocumentoEsPdf || $this->vistaDocumentoClave === '') {
            return;
        }

        if (! DocumentosEstudianteAutogestion::claveValida($this->vistaDocumentoClave)) {
            return;
        }

        $legajo = $this->legajoParaDocumentacion();
        $dni = trim((string) ($legajo->dni ?? ''));
        $clave = $this->vistaDocumentoClave;
        $indice = max(1, $this->vistaDocumentoIndice);

        $key = 'legajos-doc-estudiante-rot:'.$clave.':'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        RateLimiter::hit($key, 60);

        try {
            DocumentosEstudianteAutogestion::rotarImagenGuardada($dni, $clave, $indice);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('se-swal-error', mensaje: 'No se pudo rotar la imagen. Intente nuevamente.');

            return;
        }

        $this->recargarVistaDocumentoTrasRotar(
            $dni,
            $clave,
            $indice,
            (int) $legajo->id,
            DocumentosEstudianteAutogestion::AUDIENCIA_LEGAJO,
        );
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

    private function recargarVistaDocumentoTrasRotar(
        string $dni,
        string $clave,
        int $indice,
        int $idLegajo,
        string $audiencia,
    ): void {
        $vista = DocumentosEstudianteAutogestion::srcVista($dni, $clave, $idLegajo, $indice, $audiencia);
        if ($vista === null || $vista['es_pdf']) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo mostrar la imagen rotada.');

            return;
        }

        $this->vistaDocumentoSrc = $vista['src'];
        $this->vistaDocumentoEsPdf = false;
        $this->vistaDocumentoRevision++;
        $this->vistaDocumentoRotacionAviso = 'Orientación guardada.';

        $estado = DocumentosEstudianteAutogestion::estadoDocumento($dni, $clave, $idLegajo, $audiencia);
        if (! empty($estado['actualizado_en'])) {
            $this->vistaDocumentoFecha = (string) $estado['actualizado_en'];
        }

        $this->dispatch('documento-estudiante-rotado');
    }

    protected function procesarCambioArchivoDocumento(string $property): bool
    {
        if (preg_match('/^archivosDocumento\.([^.]+)$/', $property, $coincidencias) === 1) {
            $this->subirDocumento($coincidencias[1]);

            return true;
        }

        if (preg_match('/^archivosDocumento\.([^.]+)\.(\d+)$/', $property, $coincidencias) === 1) {
            $this->validarArchivoDocumentoSlotEnVivo($coincidencias[1], (int) $coincidencias[2]);

            return true;
        }

        return false;
    }

    private function dniDocumentacionPersistido(): string
    {
        $id = (int) ($this->id ?? 0);
        if ($id < 1) {
            return '';
        }

        return trim((string) (Legajo::query()->whereKey($id)->value('dni') ?? ''));
    }

    private function legajoParaDocumentacion(): Legajo
    {
        $id = (int) ($this->id ?? 0);
        abort_unless($id > 0, 404);

        return Legajo::query()->findOrFail($id);
    }

    private function validarArchivoDocumentoSlotEnVivo(string $clave, int $indice): void
    {
        if (! DocumentosEstudianteAutogestion::claveValida($clave)) {
            return;
        }

        $archivo = $this->archivosDocumento[$clave][$indice] ?? null;
        if (! $archivo instanceof TemporaryUploadedFile) {
            return;
        }

        $error = DocumentosEstudianteAutogestion::validarArchivoIndividual($clave, $archivo);
        if ($error !== null) {
            $this->addError('archivosDocumento.'.$clave, $error);
        }
    }

    private function reiniciarInputDocumento(string $clave): void
    {
        $this->limpiarSeleccionArchivosDocumento($clave);
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
}
