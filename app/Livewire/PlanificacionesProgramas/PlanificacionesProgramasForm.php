<?php

namespace App\Livewire\PlanificacionesProgramas;

use App\Support\Database\PersistenciaColumnas;
use App\Support\PermisosIaCatalog;
use App\Support\PlanificacionesProgramas\PlanificacionesProgramasConsulta;
use App\Support\PlanificacionesProgramas\PlanificacionesProgramasStorage;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class PlanificacionesProgramasForm extends Component
{
    use WithFileUploads;

    public int $idMateria;

    public string $tipo;

    public ?int $idTerlec = null;

    public ?int $idCurso = null;

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
        abort_unless(PlanificacionesProgramasStorage::tipoValido($tipo), 404);
        abort_if(PlanificacionesProgramasConsulta::columnasFaltantes() !== [], 503);

        $ctx = schoolCtx();
        $fila = PlanificacionesProgramasConsulta::materiaEnContexto($id, (int) $ctx->idNivel, (int) $ctx->idTerlec);
        abort_if($fila === null, 404);

        $cols = PlanificacionesProgramasStorage::columnasPorTipo($tipo);

        $this->idMateria = $id;
        $this->tipo = $tipo;
        $this->idTerlec = (int) $fila->idTerlec;
        $this->idCurso = (int) $fila->idCursos;
        $this->materiaNombre = trim((string) $fila->materia);
        $this->cursecEtiqueta = PlanificacionesProgramasConsulta::etiquetaCurso($fila);
        $this->aprobado = (int) ($fila->{$cols['aprob']} ?? 0) === 1;
        $this->observaciones = trim((string) ($fila->{$cols['obs']} ?? ''));
        $this->nombreArchivo = trim((string) ($fila->{$cols['nombre']} ?? ''));
        $this->tieneArchivo = (int) ($fila->{$cols['flag']} ?? 0) === 1 && $this->nombreArchivo !== '';
    }

    public function updatedArchivoPdf(): void
    {
        $this->resetValidation('archivoPdf');
        $error = PlanificacionesProgramasStorage::validarPdf($this->archivoPdf);
        if ($error !== null) {
            $this->addError('archivoPdf', $error);
            $this->archivoPdf = null;
        }
    }

    public function guardar(): void
    {
        if (! RateLimiter::attempt('planificaciones-programas-guardar-'.(auth()->id() ?? 0), 30, fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        $cols = PlanificacionesProgramasStorage::columnasPorTipo($this->tipo);
        $ctx = schoolCtx();
        $fila = PlanificacionesProgramasConsulta::materiaEnContexto($this->idMateria, (int) $ctx->idNivel, (int) $ctx->idTerlec);
        if ($fila === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró la materia en el contexto activo.');

            return;
        }

        $this->validate([
            'idTerlec' => ['required', 'integer', 'min:1'],
            'idCurso' => ['required', 'integer', 'min:1'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'aprobado' => ['boolean'],
        ]);

        $anio = (int) ($fila->ano_lectivo ?? $ctx->terlecAno() ?? 0);
        if ($anio <= 0) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo determinar el año lectivo.');

            return;
        }

        $cursec = PlanificacionesProgramasConsulta::etiquetaCurso($fila);
        $idNivel = (int) ($fila->idNivel ?? $ctx->idNivel ?? 0);
        if ($idNivel < 1) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo determinar el nivel pedagógico de la materia.');

            return;
        }

        if (PlanificacionesProgramasStorage::codCol($idNivel) === '') {
            $this->dispatch('se-swal-error', mensaje: 'Falta configurar el campo ento.codCol para este nivel.');

            return;
        }

        $nombreAnterior = trim((string) ($fila->{$cols['nombre']} ?? ''));
        $nuevoNombre = $nombreAnterior;
        $subioArchivo = false;

        if ($this->archivoPdf instanceof TemporaryUploadedFile) {
            $errorPdf = PlanificacionesProgramasStorage::validarPdf($this->archivoPdf);
            if ($errorPdf !== null) {
                $this->addError('archivoPdf', $errorPdf);

                return;
            }

            $nuevoNombre = PlanificacionesProgramasStorage::generarNombreArchivo($anio, $idNivel, $cursec, $this->materiaNombre);
            try {
                PlanificacionesProgramasStorage::guardarPdf($anio, $this->tipo, $idNivel, $nuevoNombre, $this->archivoPdf);
            } catch (\Throwable $e) {
                $this->dispatch('se-swal-error', mensaje: $e->getMessage() !== '' ? $e->getMessage() : 'No se pudo guardar el archivo en el servidor.');

                return;
            }

            if ($nombreAnterior !== '' && $nombreAnterior !== $nuevoNombre) {
                PlanificacionesProgramasStorage::eliminarArchivo($anio, $this->tipo, $idNivel, $nombreAnterior);
            }

            $subioArchivo = true;
        } elseif (! $this->tieneArchivo) {
            $this->addError('archivoPdf', 'Debe seleccionar un archivo PDF para subir.');

            return;
        }

        $payload = [
            $cols['flag'] => 1,
            $cols['aprob'] => $this->aprobado ? 1 : 0,
            $cols['obs'] => trim($this->observaciones),
            $cols['nombre'] => $nuevoNombre,
        ];

        $preparado = PersistenciaColumnas::prepararPayload('materias', $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            if ($subioArchivo) {
                PlanificacionesProgramasStorage::eliminarArchivo($anio, $this->tipo, $idNivel, $nuevoNombre);
            }
            $this->dispatch(
                'se-swal-error',
                mensaje: PersistenciaColumnas::mensajeColumnasInexistentes('materias', $preparado['columnas_con_valor_sin_columna']),
            );

            return;
        }

        try {
            DB::table('materias')
                ->where('id', $this->idMateria)
                ->where('idNivel', (int) $ctx->idNivel)
                ->where('idTerlec', (int) $ctx->idTerlec)
                ->update($preparado['payload']);
        } catch (QueryException $e) {
            if ($subioArchivo) {
                PlanificacionesProgramasStorage::eliminarArchivo($anio, $this->tipo, $idNivel, $nuevoNombre);
            }
            $this->dispatch('se-swal-error', mensaje: PersistenciaColumnas::mensajeDesdeQueryException($e));

            return;
        }

        $verificacion = PersistenciaColumnas::columnasNoPersistidas(
            'materias',
            ['id' => $this->idMateria],
            $preparado['payload'],
        );
        if ($verificacion !== []) {
            $this->dispatch(
                'se-swal-error',
                mensaje: 'El guardado no se verificó correctamente: '.implode(', ', $verificacion),
            );

            return;
        }

        session()->flash('success', $subioArchivo ? 'Archivo subido y datos guardados.' : 'Datos guardados.');
        $this->redirectRoute('planificaciones-programas.index', navigate: true);
    }

    public function eliminar(): void
    {
        if (! RateLimiter::attempt('planificaciones-programas-eliminar-'.(auth()->id() ?? 0), 15, fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        if (! $this->tieneArchivo) {
            $this->dispatch('se-swal-error', mensaje: 'No hay archivo cargado para eliminar.');

            return;
        }

        $cols = PlanificacionesProgramasStorage::columnasPorTipo($this->tipo);
        $ctx = schoolCtx();
        $fila = PlanificacionesProgramasConsulta::materiaEnContexto($this->idMateria, (int) $ctx->idNivel, (int) $ctx->idTerlec);
        if ($fila === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró la materia en el contexto activo.');

            return;
        }

        $anio = (int) ($fila->ano_lectivo ?? $ctx->terlecAno() ?? 0);
        $idNivel = (int) ($fila->idNivel ?? $ctx->idNivel ?? 0);
        $nombre = trim((string) ($fila->{$cols['nombre']} ?? ''));

        if ($nombre !== '' && $idNivel > 0) {
            PlanificacionesProgramasStorage::eliminarArchivo($anio, $this->tipo, $idNivel, $nombre);
        }

        $payload = [
            $cols['flag'] => 0,
            $cols['aprob'] => 0,
            $cols['obs'] => '',
            $cols['nombre'] => '',
        ];

        $preparado = PersistenciaColumnas::prepararPayload('materias', $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            $this->dispatch(
                'se-swal-error',
                mensaje: PersistenciaColumnas::mensajeColumnasInexistentes('materias', $preparado['columnas_con_valor_sin_columna']),
            );

            return;
        }

        try {
            DB::table('materias')
                ->where('id', $this->idMateria)
                ->where('idNivel', (int) $ctx->idNivel)
                ->where('idTerlec', (int) $ctx->idTerlec)
                ->update($preparado['payload']);
        } catch (QueryException $e) {
            $this->dispatch('se-swal-error', mensaje: PersistenciaColumnas::mensajeDesdeQueryException($e));

            return;
        }

        session()->flash('success', 'Archivo eliminado.');
        $this->redirectRoute('planificaciones-programas.index', navigate: true);
    }

    public function volver(): void
    {
        $this->redirectRoute('planificaciones-programas.index', navigate: true);
    }

    public function urlArchivo(): ?string
    {
        if (! $this->tieneArchivo || trim($this->nombreArchivo) === '') {
            return null;
        }

        return se_route_url('planificaciones-programas.archivo', [
            'ref' => OpaqueRouteToken::forPlanificacionesProgramasArchivo($this->idMateria, $this->tipo),
        ]);
    }

    public function render()
    {
        return view('livewire.planificaciones-programas.form', [
            'etiquetaTipo' => $this->tipo === PlanificacionesProgramasStorage::TIPO_PLAN ? 'Planificación' : 'Programa',
            'urlArchivo' => $this->urlArchivo(),
        ]);
    }
}
