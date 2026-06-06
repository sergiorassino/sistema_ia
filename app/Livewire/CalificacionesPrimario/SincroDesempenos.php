<?php

namespace App\Livewire\CalificacionesPrimario;

use App\Services\SincroDesempenos\DesempenosCsvImporter;
use App\Services\SincroDesempenos\DesempenosCsvImportResult;
use App\Support\NivelSistema;
use App\Support\SincroDesempenos\DesempenosCsvColumnMapper;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use RuntimeException;
use Throwable;

/**
 * Descargar Desempeños desde GE (CSV) — nivel primario.
 */
class SincroDesempenos extends Component
{
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $archivoCsv = null;

    public ?string $archivoNombre = null;

    public ?int $archivoTamanioKb = null;

    public bool $encabezadoValido = false;

    /** 1 = Primera etapa · 2 = Segunda etapa */
    public int $etapa = 1;

    /** @var array<string, mixed>|null */
    public ?array $ultimoResultado = null;

    private ?string $storedCsvRelativePath = null;

    public function mount(): void
    {
        abort_unless(tienePermiso(9), 403, 'Sin permiso para descargar desempeños desde GE.');
        abort_unless(
            NivelSistema::esPrimario((int) (schoolCtx()->idNivel ?? 0)),
            403,
            'Este módulo solo está disponible con el nivel Primario activo en la sesión.'
        );
    }

    public function updatedArchivoCsv(): void
    {
        $this->resetValidation('archivoCsv');
        $this->archivoNombre = null;
        $this->archivoTamanioKb = null;
        $this->encabezadoValido = false;
        $this->ultimoResultado = null;

        if (! $this->archivoCsv instanceof TemporaryUploadedFile) {
            return;
        }

        $errorArchivo = $this->validarArchivoCsvSubido($this->archivoCsv);
        if ($errorArchivo !== null) {
            $this->addError('archivoCsv', $errorArchivo);
            $this->archivoCsv = null;

            return;
        }

        $this->archivoNombre = $this->archivoCsv->getClientOriginalName();
        $bytes = (int) ($this->archivoCsv->getSize() ?? 0);
        $this->archivoTamanioKb = $bytes > 0 ? (int) ceil($bytes / 1024) : null;

        if (! $this->validarEncabezadoDesempenos($this->archivoCsv)) {
            $this->addError(
                'archivoCsv',
                'El archivo no coincide con el formato de desempeños (separador «;», columnas de grado, DNI y desempeño).'
            );
            $this->archivoCsv = null;
            $this->archivoNombre = null;
            $this->archivoTamanioKb = null;

            return;
        }

        $this->encabezadoValido = true;
    }

    public function quitarArchivo(): void
    {
        $this->archivoCsv = null;
        $this->archivoNombre = null;
        $this->archivoTamanioKb = null;
        $this->encabezadoValido = false;
        $this->resetValidation('archivoCsv');
    }

    public function importar(DesempenosCsvImporter $importer): void
    {
        abort_unless(tienePermiso(9), 403);

        $this->validate([
            'etapa' => 'required|in:1,2',
        ], [
            'etapa.in' => 'Seleccione Primera etapa o Segunda etapa.',
        ]);

        if (! $this->archivoCsv instanceof TemporaryUploadedFile) {
            $this->addError('archivoCsv', 'Seleccione un archivo CSV antes de descargar.');

            return;
        }

        $errorArchivo = $this->validarArchivoCsvSubido($this->archivoCsv);
        if ($errorArchivo !== null) {
            $this->addError('archivoCsv', $errorArchivo);

            return;
        }

        if (! $this->encabezadoValido) {
            $this->addError('archivoCsv', 'El archivo no tiene un encabezado válido. Vuelva a seleccionarlo.');

            return;
        }

        $key = 'sincroDesempenos:import:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 6)) {
            $this->addError('archivoCsv', 'Demasiados intentos seguidos. Espere un minuto e intente de nuevo.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->ultimoResultado = null;

        $ctx = schoolCtx();
        $idTerlec = (int) $ctx->idTerlec;
        $idNivel = (int) $ctx->idNivel;

        if ($idTerlec < 1 || $idNivel < 1) {
            $this->addError('archivoCsv', 'No hay contexto de nivel o ciclo lectivo activo en la sesión.');

            return;
        }

        try {
            $path = $this->resolveCsvAbsolutePath($this->archivoCsv);
            $result = $importer->import($path, $idTerlec, $idNivel, $this->etapa);
            $this->ultimoResultado = $this->serializeResult($result);

            if ($result->committed && $result->updatedRows > 0) {
                session()->flash('success', $result->successMessage());
            } elseif ($result->hasIssues() || $result->updatedRows === 0) {
                session()->flash('warning', $result->successMessage());
            }

            $this->quitarArchivo();
        } catch (RuntimeException $e) {
            $this->addError('archivoCsv', $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->addError('archivoCsv', 'Error inesperado al importar. No se guardaron cambios. Contacte al administrador si persiste.');
        } finally {
            $this->deleteStoredCopy();
        }
    }

    public function limpiarResultado(): void
    {
        $this->ultimoResultado = null;
        $this->resetValidation();
    }

    private function validarArchivoCsvSubido(TemporaryUploadedFile $file): ?string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt'], true)) {
            return 'El archivo debe ser .csv o .txt.';
        }

        $bytes = (int) ($file->getSize() ?? 0);
        if ($bytes < 1) {
            return 'El archivo está vacío o no se terminó de subir. Espere un momento y vuelva a seleccionarlo.';
        }

        if ($bytes > 15 * 1024 * 1024) {
            return 'El archivo no puede superar 15 MB.';
        }

        return null;
    }

    private function validarEncabezadoDesempenos(TemporaryUploadedFile $file): bool
    {
        $path = $file->getRealPath();
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            $path = $file->path();
        }
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return false;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fgetcsv($handle, 0, ';');
        fclose($handle);

        if (! is_array($header) || count($header) < 4) {
            return false;
        }

        $mapper = new DesempenosCsvColumnMapper($header);

        return $mapper->esEncabezadoValido();
    }

    private function resolveCsvAbsolutePath(TemporaryUploadedFile $file): string
    {
        $candidates = array_filter([
            $file->getRealPath(),
            method_exists($file, 'path') ? $file->path() : null,
        ], fn ($p) => is_string($p) && $p !== '' && is_readable($p));

        if ($candidates !== []) {
            return (string) reset($candidates);
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt'], true)) {
            $ext = 'csv';
        }

        $userId = (int) (auth()->id() ?? 0);
        $relative = $file->storeAs(
            'imports/sincro-desempenos',
            'desemp_'.$userId.'_'.uniqid('', true).'.'.$ext,
            'local'
        );

        if ($relative === false || $relative === '') {
            throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
        }

        $this->storedCsvRelativePath = $relative;
        $absolute = storage_path('app/'.$relative);

        if (! is_readable($absolute)) {
            throw new RuntimeException('No se pudo leer el archivo subido.');
        }

        return $absolute;
    }

    private function deleteStoredCopy(): void
    {
        if ($this->storedCsvRelativePath === null) {
            return;
        }

        Storage::disk('local')->delete($this->storedCsvRelativePath);
        $this->storedCsvRelativePath = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeResult(DesempenosCsvImportResult $result): array
    {
        return [
            'totalDataRows' => $result->totalDataRows,
            'updatedRows' => $result->updatedRows,
            'skippedRows' => $result->skippedRows,
            'committed' => $result->committed,
            'etapa' => $result->etapa,
            'message' => $result->successMessage(),
            'issues' => $result->issues,
            'issuesTruncated' => $result->issuesTruncated,
        ];
    }

    public function render()
    {
        return view('livewire.calificaciones-primario.sincro-desempenos')
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Descargar Desempeños desde GE']);
    }
}
