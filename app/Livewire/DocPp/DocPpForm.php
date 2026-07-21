<?php

namespace App\Livewire\DocPp;

use App\Models\DocPp;
use App\Support\DocPp\DocPpConsulta;
use App\Support\DocPp\DocPpStorage;
use App\Support\PermisosIaCatalog;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class DocPpForm extends Component
{
    use WithFileUploads;

    public int $idMateria;

    public string $tipo;

    public ?int $idDoc = null;

    public string $materiaNombre = '';

    public string $cursecEtiqueta = '';

    public bool $aprobado = false;

    public string $observaciones = '';

    public bool $tieneArchivo = false;

    public string $nombreArchivo = '';

    /** @var TemporaryUploadedFile|null */
    public $archivoPdf = null;

    public function mount(int $id, string $tipo): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::PLANIFICACIONES_PROGRAMAS), 403);
        abort_unless(tenantDocPpHabilitado(), 404);
        abort_unless(DocPpStorage::tipoValido($tipo), 404);
        abort_unless(DocPpConsulta::tablaDisponible(), 503);

        $ctx = schoolCtx();
        $fila = DocPpConsulta::materiaEnContexto($id, (int) $ctx->idNivel, (int) $ctx->idTerlec);
        abort_if($fila === null, 404);

        $this->idMateria = $id;
        $this->tipo = $tipo;
        $this->materiaNombre = trim((string) $fila->materia);
        $this->cursecEtiqueta = DocPpConsulta::etiquetaCurso($fila);

        $doc = DocPpConsulta::documentoDeMateria($id, $tipo);
        if ($doc !== null) {
            $this->idDoc = (int) $doc->id;
            $this->aprobado = (int) $doc->aprobado === 1;
            $this->observaciones = trim((string) ($doc->observaciones ?? ''));
            $this->nombreArchivo = trim((string) ($doc->nombre_archivo ?? ''));
            $this->tieneArchivo = $this->nombreArchivo !== '';
        }
    }

    public function updatedArchivoPdf(): void
    {
        $this->resetValidation('archivoPdf');
        $error = DocPpStorage::validarPdf($this->archivoPdf);
        if ($error !== null) {
            $this->addError('archivoPdf', $error);
            $this->archivoPdf = null;
        }
    }

    public function guardar(): void
    {
        if (! RateLimiter::attempt('doc-pp-guardar-'.(auth()->id() ?? 0), 30, fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        $ctx = schoolCtx();
        $fila = DocPpConsulta::materiaEnContexto($this->idMateria, (int) $ctx->idNivel, (int) $ctx->idTerlec);
        if ($fila === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró la materia en el contexto activo.');

            return;
        }

        $this->validate([
            'observaciones' => ['nullable', 'string', 'max:500'],
            'aprobado' => ['boolean'],
        ]);

        $anio = (int) ($fila->ano_lectivo ?? $ctx->terlecAno() ?? 0);
        $idNivel = (int) ($fila->idNivel ?? 0);
        $idCursos = (int) ($fila->idCursos ?? 0);

        if ($anio <= 0 || $idNivel < 1 || $idCursos < 1) {
            $this->dispatch('se-swal-error', mensaje: 'Faltan datos de contexto (año, nivel o curso).');

            return;
        }

        if (DocPpStorage::codCol($idNivel) === '') {
            $this->dispatch('se-swal-error', mensaje: 'Falta configurar el campo ento.codCol para este nivel.');

            return;
        }

        $docExistente = DocPpConsulta::documentoDeMateria($this->idMateria, $this->tipo);
        $nombreAnterior = trim((string) ($docExistente?->nombre_archivo ?? ''));
        $nuevoNombre = $nombreAnterior;
        $subioArchivo = false;

        if ($this->archivoPdf instanceof TemporaryUploadedFile) {
            $errorPdf = DocPpStorage::validarPdf($this->archivoPdf);
            if ($errorPdf !== null) {
                $this->addError('archivoPdf', $errorPdf);

                return;
            }

            $nuevoNombre = DocPpStorage::generarNombreArchivo(
                $anio,
                $idNivel,
                $this->tipo,
                DocPpConsulta::etiquetaCurso($fila),
                $this->materiaNombre,
            );

            try {
                DocPpStorage::guardarPdf($anio, $this->tipo, $idNivel, $nuevoNombre, $this->archivoPdf);
            } catch (\Throwable $e) {
                $this->dispatch(
                    'se-swal-error',
                    mensaje: $e->getMessage() !== '' ? $e->getMessage() : 'No se pudo guardar el archivo en el servidor.',
                );

                return;
            }

            if ($nombreAnterior !== '' && $nombreAnterior !== $nuevoNombre) {
                DocPpStorage::eliminarArchivo($anio, $this->tipo, $idNivel, $nombreAnterior);
            }

            $subioArchivo = true;
        } elseif ($docExistente === null) {
            $this->addError('archivoPdf', 'Debe seleccionar un archivo PDF para subir.');

            return;
        }

        $payload = [
            'idNivel' => $idNivel,
            'idTerlec' => (int) $fila->idTerlec,
            'idMaterias' => $this->idMateria,
            'idCursos' => $idCursos,
            'tipo' => $this->tipo,
            'nombre_archivo' => $nuevoNombre,
            'aprobado' => $this->aprobado ? 1 : 0,
            'observaciones' => trim($this->observaciones) !== '' ? trim($this->observaciones) : null,
        ];

        if ($subioArchivo || $docExistente === null) {
            $payload['subido_por'] = (int) (auth()->id() ?? 0) ?: null;
            $payload['subido_en'] = now();
        }

        try {
            if ($docExistente !== null) {
                $docExistente->fill($payload);
                $docExistente->save();
            } else {
                DocPp::query()->create($payload);
            }
        } catch (QueryException $e) {
            if ($subioArchivo) {
                DocPpStorage::eliminarArchivo($anio, $this->tipo, $idNivel, $nuevoNombre);
            }
            $this->dispatch('se-swal-error', mensaje: 'No se pudo guardar el registro en la base de datos.');

            return;
        }

        session()->flash('success', $subioArchivo ? 'Archivo subido y datos guardados.' : 'Datos guardados.');
        $this->redirectRoute('doc-pp.index', navigate: true);
    }

    public function eliminar(): void
    {
        if (! RateLimiter::attempt('doc-pp-eliminar-'.(auth()->id() ?? 0), 15, fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        $ctx = schoolCtx();
        $fila = DocPpConsulta::materiaEnContexto($this->idMateria, (int) $ctx->idNivel, (int) $ctx->idTerlec);
        $doc = DocPpConsulta::documentoDeMateria($this->idMateria, $this->tipo);

        if ($fila === null || $doc === null) {
            $this->dispatch('se-swal-error', mensaje: 'No hay archivo cargado para eliminar.');

            return;
        }

        $anio = (int) ($fila->ano_lectivo ?? $ctx->terlecAno() ?? 0);
        $idNivel = (int) ($fila->idNivel ?? 0);
        $nombre = trim((string) ($doc->nombre_archivo ?? ''));

        if ($nombre !== '' && $idNivel > 0 && $anio > 0) {
            DocPpStorage::eliminarArchivo($anio, $this->tipo, $idNivel, $nombre);
        }

        try {
            $doc->delete();
        } catch (QueryException $e) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo eliminar el registro en la base de datos.');

            return;
        }

        session()->flash('success', 'Archivo eliminado.');
        $this->redirectRoute('doc-pp.index', navigate: true);
    }

    public function urlArchivo(): ?string
    {
        if ($this->idDoc === null || $this->idDoc <= 0 || ! $this->tieneArchivo) {
            return null;
        }

        return se_route_url('doc-pp.archivo', [
            'ref' => OpaqueRouteToken::forDocPpArchivo($this->idDoc),
        ]);
    }

    public function render()
    {
        return view('livewire.doc-pp.form', [
            'etiquetaTipo' => $this->tipo === DocPpStorage::TIPO_PLAN ? 'Planificación' : 'Programa',
            'urlArchivo' => $this->urlArchivo(),
        ]);
    }
}
