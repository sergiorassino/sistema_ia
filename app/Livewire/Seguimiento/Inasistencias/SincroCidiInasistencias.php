<?php

namespace App\Livewire\Seguimiento\Inasistencias;

use App\Models\InasistenciaValor;
use App\Services\SincroCidiInasistencias\CidiInasistenciasCsvImporter;
use App\Services\SincroCidiInasistencias\CidiInasistenciasCsvImportResult;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use RuntimeException;
use Throwable;

/**
 * Importación de inasistencias desde CSV CIDI/GE (InasistenciasDetalle).
 */
class SincroCidiInasistencias extends Component
{
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $archivoCsv = null;

    public ?string $archivoNombre = null;

    public ?int $archivoTamanioKb = null;

    public bool $encabezadoValido = false;

    /** @var array<string, mixed>|null */
    public ?array $ultimoResultado = null;

    /** @var array<int, string> id inasistencias_valores => texto CIDI tal como viene en el CSV */
    public array $textosCidi = [];

    private ?string $storedCsvRelativePath = null;

    public function mount(): void
    {
        abort_unless(tienePermiso(24), 403, 'Sin permiso para importar inasistencias desde CIDI/GE.');
        $this->cargarTextosCidi();
    }

    public function guardarTextosCidi(): void
    {
        abort_unless(tienePermiso(24), 403);

        $key = 'sincroCidiInasistencias:textos:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('textosCidi', 'Demasiados guardados seguidos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $reglas = [];
        foreach (array_keys($this->textosCidi) as $id) {
            $reglas['textosCidi.'.$id] = ['nullable', 'string', 'max:120'];
        }
        $this->validate($reglas);

        $idsPermitidos = InasistenciaValor::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($this->textosCidi as $id => $texto) {
            $id = (int) $id;
            if (! in_array($id, $idsPermitidos, true)) {
                continue;
            }

            InasistenciaValor::query()->whereKey($id)->update([
                'texto_cidi' => trim((string) $texto) !== '' ? trim((string) $texto) : null,
            ]);
        }

        $this->cargarTextosCidi();
        session()->flash('success', 'Textos CIDI del catálogo guardados.');
    }

    private function cargarTextosCidi(): void
    {
        $this->textosCidi = InasistenciaValor::query()
            ->orderBy('concepto')
            ->get(['id', 'texto_cidi'])
            ->mapWithKeys(fn (InasistenciaValor $v) => [(int) $v->id => (string) ($v->texto_cidi ?? '')])
            ->all();
    }

    public function updatedArchivoCsv(): void
    {
        $this->resetValidation('archivoCsv');
        $this->archivoNombre = null;
        $this->archivoTamanioKb = null;
        $this->encabezadoValido = false;
        $this->ultimoResultado = null;

        if ($this->archivoCsv === null) {
            return;
        }

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

        if (! $this->validarEncabezadoCidi($this->archivoCsv)) {
            $this->addError(
                'archivoCsv',
                'El archivo no coincide con el formato CIDI de inasistencias (separador «;», columnas Grado/Año, Tipo, Fecha). Verifique que exportó InasistenciasDetalle.'
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

    public function importar(CidiInasistenciasCsvImporter $importer): void
    {
        abort_unless(tienePermiso(24), 403);

        if (! $this->archivoCsv instanceof TemporaryUploadedFile) {
            $this->addError('archivoCsv', 'Seleccione un archivo CSV antes de importar.');

            return;
        }

        $errorArchivo = $this->validarArchivoCsvSubido($this->archivoCsv);
        if ($errorArchivo !== null) {
            $this->addError('archivoCsv', $errorArchivo);

            return;
        }

        if (! $this->encabezadoValido) {
            $this->addError('archivoCsv', 'El archivo no tiene un encabezado CIDI válido. Vuelva a seleccionarlo.');

            return;
        }

        $key = 'sincroCidiInasistencias:import:'.(auth()->id() ?? 'guest');
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
            $result = $importer->import($path, $idTerlec, $idNivel);
            $this->ultimoResultado = $this->serializeResult($result);

            if ($result->committed && $result->filasModificadas() > 0) {
                session()->flash('success', $result->successMessage());
            } elseif ($result->hasIssues() || $result->filasModificadas() === 0) {
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

    private function validarEncabezadoCidi(TemporaryUploadedFile $file): bool
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

        if (! is_array($header) || count($header) < 7) {
            return false;
        }

        $joined = mb_strtoupper(implode(';', array_map('trim', $header)), 'UTF-8');

        return str_contains($joined, 'GRADO')
            && str_contains($joined, 'DIVIS')
            && str_contains($joined, 'TIPO')
            && str_contains($joined, 'FECHA')
            && (str_contains($joined, 'DOCUMENTO') || str_contains($joined, 'DNI'));
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
            'imports/sincro-cidi-inasistencias',
            'cidi_inas_'.$userId.'_'.uniqid('', true).'.'.$ext,
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
    private function serializeResult(CidiInasistenciasCsvImportResult $result): array
    {
        return [
            'totalDataRows' => $result->totalDataRows,
            'insertedRows' => $result->insertedRows,
            'updatedRows' => $result->updatedRows,
            'skippedRows' => $result->skippedRows,
            'skippedPresenteRows' => $result->skippedPresenteRows,
            'skippedSinCambioRows' => $result->skippedSinCambioRows,
            'committed' => $result->committed,
            'message' => $result->successMessage(),
            'issues' => $result->issues,
            'issuesTruncated' => $result->issuesTruncated,
        ];
    }

    public function render()
    {
        $tiposInasistencia = InasistenciaValor::query()
            ->orderBy('concepto')
            ->get(['id', 'concepto', 'cantidad', 'texto_cidi']);

        $textosCidiConfigurados = $tiposInasistencia->contains(
            fn (InasistenciaValor $v) => trim((string) ($v->texto_cidi ?? '')) !== ''
        );

        return view('livewire.seguimiento.inasistencias.sincro-cidi', [
            'tiposInasistencia' => $tiposInasistencia,
            'textosCidiConfigurados' => $textosCidiConfigurados,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Descargar inasistencias desde CIDI']);
    }
}
