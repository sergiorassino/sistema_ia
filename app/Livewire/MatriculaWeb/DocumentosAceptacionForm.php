<?php

namespace App\Livewire\MatriculaWeb;

use App\Livewire\Concerns\RequiresPermisoMatriculaWeb;
use App\Models\Ento;
use App\Support\MatriculaWeb\MatriculaWebDocumentos;
use App\Support\PermisosMatriculaWeb;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class DocumentosAceptacionForm extends Component
{
    use RequiresPermisoMatriculaWeb;
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $archivoCompromiso = null;

    /** @var TemporaryUploadedFile|null */
    public $archivoAec = null;

    /** @var TemporaryUploadedFile|null */
    public $archivoNormas = null;

    /** @var TemporaryUploadedFile|null */
    public $archivoTraslado = null;

    public bool $quitarCompromiso = false;

    public bool $quitarAec = false;

    public bool $quitarNormas = false;

    public bool $quitarTraslado = false;

    /** @var array<string, array{nombre: ?string, path: ?string, existe: bool}> */
    public array $estadoActual = [];

    protected function permisoMatriculaWebOrden(): int
    {
        return PermisosMatriculaWeb::DOCUMENTOS_ACEPTACION;
    }

    public function mount(): void
    {
        $this->refrescarEstado();
    }

    public function updatedArchivoCompromiso(): void
    {
        $this->validarSubidaEnVivo('archivoCompromiso', MatriculaWebDocumentos::COMPROMISO);
    }

    public function updatedArchivoAec(): void
    {
        $this->validarSubidaEnVivo('archivoAec', MatriculaWebDocumentos::AEC);
    }

    public function updatedArchivoNormas(): void
    {
        $this->validarSubidaEnVivo('archivoNormas', MatriculaWebDocumentos::NORMAS);
    }

    public function updatedArchivoTraslado(): void
    {
        $this->validarSubidaEnVivo('archivoTraslado', MatriculaWebDocumentos::TRASLADO);
    }

    private function validarSubidaEnVivo(string $propiedad, string $clave): void
    {
        $this->resetValidation($propiedad);
        $archivo = $this->{$propiedad};
        if (! $archivo instanceof TemporaryUploadedFile) {
            return;
        }

        $this->marcarQuitar($clave, false);

        $error = $this->validarPdf($archivo);
        if ($error !== null) {
            $this->addError($propiedad, $error);
            $this->{$propiedad} = null;
        }
    }

    public function save(): void
    {
        $key = 'matricula-web-docs:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('archivoCompromiso', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            abort(403);
        }

        /** @var Ento $ento */
        $ento = Ento::query()->firstOrNew(['idNivel' => $idNivel]);
        $ento->idNivel = $idNivel;

        foreach ($this->mapaPropiedades() as $clave => $config) {
            $error = $this->procesarDocumento($clave, $config['upload'], $config['quitar'], $idNivel, $ento);
            if ($error !== null) {
                $this->addError($config['upload'], $error);

                return;
            }
        }

        $ento->save();

        $this->archivoCompromiso = null;
        $this->archivoAec = null;
        $this->archivoNormas = null;
        $this->archivoTraslado = null;
        $this->quitarCompromiso = false;
        $this->quitarAec = false;
        $this->quitarNormas = false;
        $this->quitarTraslado = false;

        $this->refrescarEstado();

        session()->flash('success', 'Documentos de matrícula web actualizados para este nivel.');
    }

    private function procesarDocumento(string $clave, string $propUpload, string $propQuitar, int $idNivel, Ento $ento): ?string
    {
        $def = MatriculaWebDocumentos::definicion($clave);
        if ($def === null) {
            return null;
        }

        $col = $def['docum_column'];
        $archivo = $this->{$propUpload};
        $quitar = (bool) $this->{$propQuitar};
        $nombreAnterior = trim((string) ($ento->{$col} ?? ''));

        if ($quitar) {
            MatriculaWebDocumentos::eliminarArchivoPorNombre($idNivel, $nombreAnterior);
            $ento->{$col} = null;

            return null;
        }

        if ($archivo instanceof TemporaryUploadedFile) {
            $error = $this->validarPdf($archivo);
            if ($error !== null) {
                return $error;
            }

            $nombreNuevo = $this->persistirPdf($idNivel, $archivo, $nombreAnterior);
            if ($nombreNuevo === null) {
                return 'No se pudo guardar el PDF en el servidor. Verifique permisos en storage/app/public.';
            }

            $ento->{$col} = $nombreNuevo;

            return null;
        }

        if ($archivo !== null) {
            return 'La subida del PDF no finalizó. Espere a que termine «Subiendo archivo…» y vuelva a guardar.';
        }

        return null;
    }

    private function validarPdf(TemporaryUploadedFile $file): ?string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext !== 'pdf') {
            return 'El archivo debe ser PDF.';
        }

        $bytes = (int) ($file->getSize() ?? 0);
        if ($bytes < 1) {
            return 'El archivo está vacío o no se terminó de subir.';
        }

        if ($bytes > MatriculaWebDocumentos::MAX_BYTES) {
            return 'El PDF no puede superar los 15 MB.';
        }

        $mime = strtolower((string) $file->getMimeType());
        if ($mime !== '' && $mime !== 'application/pdf' && $mime !== 'application/x-pdf') {
            return 'El archivo seleccionado no es un PDF válido.';
        }

        return null;
    }

    private function persistirPdf(int $idNivel, TemporaryUploadedFile $file, string $nombreAnterior): ?string
    {
        $nombreArchivo = MatriculaWebDocumentos::nombreArchivoSeguro((string) $file->getClientOriginalName());
        $dir = MatriculaWebDocumentos::storageDir($idNivel);
        $disk = Storage::disk('public');

        try {
            if (! $disk->exists($dir)) {
                $disk->makeDirectory($dir);
            }

            $stored = $file->storeAs($dir, $nombreArchivo, 'public');
        } catch (\Throwable $e) {
            Log::warning('matricula-web-docs: error al guardar PDF', [
                'dir' => $dir,
                'nombre' => $nombreArchivo,
                'message' => $e->getMessage(),
            ]);
            $stored = false;
        }

        if (! is_string($stored) || $stored === '' || ! $disk->exists($stored)) {
            return null;
        }

        $anteriorSeguro = $nombreAnterior !== ''
            ? MatriculaWebDocumentos::nombreArchivoSeguro($nombreAnterior)
            : '';
        if ($anteriorSeguro !== '' && $anteriorSeguro !== $nombreArchivo) {
            MatriculaWebDocumentos::eliminarArchivoPorNombre($idNivel, $nombreAnterior);
        }

        return $nombreArchivo;
    }

    private function marcarQuitar(string $clave, bool $valor): void
    {
        foreach ($this->mapaPropiedades() as $k => $config) {
            if ($k === $clave) {
                $this->{$config['quitar']} = $valor;

                return;
            }
        }
    }

    /**
     * @return array<string, array{upload: string, quitar: string}>
     */
    private function mapaPropiedades(): array
    {
        return [
            MatriculaWebDocumentos::COMPROMISO => ['upload' => 'archivoCompromiso', 'quitar' => 'quitarCompromiso'],
            MatriculaWebDocumentos::AEC => ['upload' => 'archivoAec', 'quitar' => 'quitarAec'],
            MatriculaWebDocumentos::NORMAS => ['upload' => 'archivoNormas', 'quitar' => 'quitarNormas'],
            MatriculaWebDocumentos::TRASLADO => ['upload' => 'archivoTraslado', 'quitar' => 'quitarTraslado'],
        ];
    }

    private function refrescarEstado(): void
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        $estado = [];
        foreach (MatriculaWebDocumentos::claves() as $clave) {
            $estado[$clave] = MatriculaWebDocumentos::estadoDocumento($clave, $idNivel);
        }
        $this->estadoActual = $estado;
    }

    public function render()
    {
        return view('livewire.matricula-web.documentos-aceptacion-form', [
            'nivelNombre' => schoolCtx()->nivelNombre(),
            'definiciones' => MatriculaWebDocumentos::definiciones(),
            'propiedades' => $this->mapaPropiedades(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Documentos — Matrícula web']);
    }
}
