<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\CuotasDetalleCatalog;
use App\Support\Database\PersistenciaColumnas;
use App\Support\Navegacion\ContextoCuotasDetalleSesion;
use App\Support\Navegacion\ContextoCuotasImportesSesion;
use App\Support\PermisosCuotas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Ítems de discriminación de una plantilla, propios de cada curso.
 * Se puede copiar la lista completa (ítems e importes) de un curso a otros.
 */
class CuotasDetalleForm extends Component
{
    public int $idCuotas = 0;

    public int $idCursoActivo = 0;

    public string $nuevoNombre = '';

    /** @var array<string, string> */
    public array $nombres = [];

    /** @var array<string, string> */
    public array $montos = [];

    /** @var list<string> */
    public array $cursosDestino = [];

    public bool $modalCopiar = false;

    public bool $tablasListas = false;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);

        $idCuotas = ContextoCuotasDetalleSesion::idCuotas();
        abort_if($idCuotas === null, 404);

        $this->idCuotas = $idCuotas;
        CuotasDetalleCatalog::cuotaDelCicloOrFail($idCuotas);
        $this->tablasListas = CuotasDetalleCatalog::tablasListas();
        $this->recargarEstado();
    }

    public function agregarItem(): void
    {
        $this->autorizar();
        if ($this->demasiadosIntentos('alta', 40)) {
            return;
        }
        if ($this->montos !== [] && $this->montosDifieren() && ! $this->persistirMontos(false)) {
            return;
        }

        try {
            if ($this->idCursoActivo <= 0) {
                $this->dispatch('se-swal-error', mensaje: 'No hay cursos en el ciclo lectivo activo.');

                return;
            }
            CuotasDetalleCatalog::cursoDelCicloOrFail($this->idCursoActivo);
            CuotasDetalleCatalog::crearItem($this->idCuotas, $this->idCursoActivo, $this->nuevoNombre);
        } catch (ValidationException $e) {
            $this->volcarErrores($e);

            return;
        } catch (QueryException $e) {
            $this->dispatch('se-swal-error', mensaje: $this->mensajeQuery($e));

            return;
        }

        $this->nuevoNombre = '';
        $this->resetValidation();
        $this->recargarEstado();
    }

    public function updatedNombres(mixed $value, string $key): void
    {
        $this->autorizar();
        $id = (int) $key;
        if ($id <= 0 || $this->demasiadosIntentos('nombre', 60)) {
            return;
        }

        $item = CuotasDetalleCatalog::detalleDelCursoOrFail($id, $this->idCuotas, $this->idCursoActivo);
        try {
            CuotasDetalleCatalog::renombrar($item, (string) $value);
        } catch (ValidationException $e) {
            $this->nombres[(string) $id] = (string) $item->nombre;
            $mensaje = (string) ($e->validator->errors()->first() ?: 'Nombre inválido.');
            $this->addError('nombres.'.$id, $mensaje);

            return;
        } catch (QueryException $e) {
            $this->nombres[(string) $id] = (string) $item->nombre;
            $this->dispatch('se-swal-error', mensaje: $this->mensajeQuery($e));

            return;
        }

        $this->resetErrorBag('nombres.'.$id);
        $this->nombres[(string) $id] = (string) $item->nombre;
    }

    public function moverItem(int $id, int $delta): void
    {
        $this->autorizar();
        if ($this->demasiadosIntentos('orden', 60)) {
            return;
        }
        if ($this->montos !== [] && $this->montosDifieren() && ! $this->persistirMontos(false)) {
            return;
        }

        $item = CuotasDetalleCatalog::detalleDelCursoOrFail($id, $this->idCuotas, $this->idCursoActivo);
        try {
            CuotasDetalleCatalog::mover($item, $delta);
        } catch (QueryException $e) {
            $this->dispatch('se-swal-error', mensaje: $this->mensajeQuery($e));

            return;
        }

        $this->recargarEstado();
    }

    public function eliminarItem(int $id): void
    {
        $this->autorizar();
        if ($this->demasiadosIntentos('baja', 30)) {
            return;
        }
        if ($this->montos !== [] && $this->montosDifieren() && ! $this->persistirMontos(false)) {
            return;
        }

        $item = CuotasDetalleCatalog::detalleDelCursoOrFail($id, $this->idCuotas, $this->idCursoActivo);
        $nombre = (string) $item->nombre;
        try {
            CuotasDetalleCatalog::eliminarItem($item);
        } catch (QueryException $e) {
            $this->dispatch('se-swal-error', mensaje: $this->mensajeQuery($e));

            return;
        }

        $this->recargarEstado();
        $this->dispatch('se-swal-exito', mensaje: "Ítem «{$nombre}» eliminado.");
    }

    public function seleccionarCurso(int $idCurso): void
    {
        $this->autorizar();
        if ($idCurso === $this->idCursoActivo) {
            return;
        }

        CuotasDetalleCatalog::cursoDelCicloOrFail($idCurso);
        if ($this->idCursoActivo > 0 && $this->montos !== [] && $this->montosDifieren() && ! $this->persistirMontos(false)) {
            return;
        }

        $this->idCursoActivo = $idCurso;
        $this->cargarCurso();
        $this->resetErrorBag('montos');
    }

    public function guardarCurso(): void
    {
        $this->autorizar();
        $this->persistirMontos(true);
    }

    public function abrirCopiar(): void
    {
        $this->autorizar();
        if ($this->idCursoActivo <= 0 || $this->montos === []) {
            $this->dispatch('se-swal-error', mensaje: 'Agregue ítems en este curso antes de copiar.');

            return;
        }
        if (! $this->persistirMontos(false)) {
            return;
        }

        $this->cursosDestino = [];
        $this->resetErrorBag('cursosDestino');
        $this->modalCopiar = true;
    }

    public function cerrarCopiar(): void
    {
        $this->modalCopiar = false;
        $this->cursosDestino = [];
    }

    public function marcarTodosDestino(): void
    {
        $this->autorizar();
        $this->cursosDestino = array_values(array_map(
            fn (int $id): string => (string) $id,
            array_filter(
                CuotasDetalleCatalog::idsCursosDelCiclo(),
                fn (int $id): bool => $id !== $this->idCursoActivo,
            ),
        ));
    }

    public function limpiarDestino(): void
    {
        $this->cursosDestino = [];
    }

    public function copiarACursos(): void
    {
        $this->autorizar();
        if ($this->demasiadosIntentos('copia', 20)) {
            return;
        }

        $destino = [];
        foreach ($this->cursosDestino as $id) {
            $id = (int) $id;
            if ($id > 0 && $id !== $this->idCursoActivo) {
                $destino[$id] = $id;
            }
        }

        $validos = array_flip(CuotasDetalleCatalog::idsCursosDelCiclo());
        $destino = array_values(array_filter(
            $destino,
            fn (int $id): bool => isset($validos[$id]),
        ));

        if ($destino === []) {
            $this->addError('cursosDestino', 'Elija al menos un curso de destino.');

            return;
        }

        try {
            $copiados = CuotasDetalleCatalog::copiarDiscriminacion($this->idCuotas, $this->idCursoActivo, $destino);
        } catch (QueryException $e) {
            $this->dispatch('se-swal-error', mensaje: $this->mensajeQuery($e));

            return;
        }

        if ($copiados === 0) {
            $this->addError('cursosDestino', 'Este curso no tiene ítems para copiar.');

            return;
        }

        $this->cerrarCopiar();
        $this->dispatch('se-swal-exito', mensaje: $copiados === 1
            ? 'Discriminación copiada a 1 curso.'
            : "Discriminación copiada a {$copiados} cursos.");
    }

    public function irAImportes(): void
    {
        $this->autorizar();
        ContextoCuotasImportesSesion::fijar($this->idCuotas);
        $this->redirectRoute('cuotas.importes.editar', navigate: true);
    }

    public function render()
    {
        $cuota = CuotasDetalleCatalog::cuotaDelCicloOrFail($this->idCuotas);
        $ano = (int) schoolCtx()->terlecAno();
        $items = $this->tablasListas
            ? CuotasDetalleCatalog::itemsDelCurso($this->idCuotas, $this->idCursoActivo)
            : [];
        $cursos = $this->tablasListas ? CuotasDetalleCatalog::cursosDelCiclo() : [];
        $conteoPorCurso = $this->tablasListas
            ? CuotasDetalleCatalog::conteoItemsPorCurso($this->idCuotas)
            : [];
        $subtotal = CuotasDetalleCatalog::subtotalTextos($this->montos);
        $importeCuota = ($this->tablasListas && $this->idCursoActivo > 0)
            ? CuotasDetalleCatalog::importeCuotaCurso($this->idCuotas, $this->idCursoActivo)
            : null;

        $cursoActivoLabel = '';
        foreach ($cursos as $curso) {
            if ($curso['id'] === $this->idCursoActivo) {
                $cursoActivoLabel = $curso['label'];
                break;
            }
        }

        return view('livewire.cuotas.detalle-form', [
            'cuota' => $cuota,
            'ano' => $ano,
            'items' => $items,
            'cursos' => $cursos,
            'conteoPorCurso' => $conteoPorCurso,
            'esquemaAnterior' => ! $this->tablasListas && CuotasDetalleCatalog::esquemaAnterior(),
            'subtotal' => $subtotal,
            'importeCuota' => $importeCuota,
            'cursoActivoLabel' => $cursoActivoLabel,
            'difiereImporte' => $subtotal !== null
                && $importeCuota !== null
                && abs($subtotal - $importeCuota) >= 0.01,
        ])->layout(layoutMenuStaff(), [
            'pageTitle' => 'Discriminación — '.trim((string) $cuota->nombre),
        ]);
    }

    private function autorizar(): void
    {
        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);
        abort_unless($this->tablasListas, 404);
        CuotasDetalleCatalog::cuotaDelCicloOrFail($this->idCuotas);
    }

    private function recargarEstado(): void
    {
        if (! $this->tablasListas) {
            return;
        }

        $cursos = CuotasDetalleCatalog::cursosDelCiclo();
        $ids = array_column($cursos, 'id');
        if ($this->idCursoActivo <= 0 || ! in_array($this->idCursoActivo, $ids, true)) {
            $this->idCursoActivo = (int) ($ids[0] ?? 0);
        }

        $this->cargarCurso();
    }

    private function montosDifieren(): bool
    {
        $ids = array_map('intval', array_keys($this->nombres));
        $guardados = CuotasDetalleCatalog::montosTextoDeItems($ids);

        foreach ($ids as $idDetalle) {
            $clave = (string) $idDetalle;
            $escrito = trim((string) ($this->montos[$clave] ?? $this->montos[$idDetalle] ?? ''));
            $previo = trim((string) ($guardados[$clave] ?? ''));
            if ($escrito === $previo) {
                continue;
            }

            try {
                if (CuotasDetalleCatalog::importeDesdeTexto($escrito) !== CuotasDetalleCatalog::importeDesdeTexto($previo)) {
                    return true;
                }
            } catch (ValidationException) {
                return true;
            }
        }

        return false;
    }

    private function cargarCurso(): void
    {
        $items = CuotasDetalleCatalog::itemsDelCurso($this->idCuotas, $this->idCursoActivo);
        $nombres = [];
        foreach ($items as $item) {
            $nombres[(string) $item['id']] = $item['nombre'];
        }
        $this->nombres = $nombres;
        $this->montos = CuotasDetalleCatalog::montosTextoDeItems(array_map('intval', array_keys($nombres)));
    }

    private function persistirMontos(bool $avisarExito): bool
    {
        if ($this->idCursoActivo <= 0) {
            $this->dispatch('se-swal-error', mensaje: 'No hay cursos en el ciclo lectivo activo.');

            return false;
        }
        if ($this->montos === []) {
            $this->dispatch('se-swal-error', mensaje: 'Agregue al menos un ítem para cargar importes.');

            return false;
        }
        if ($this->demasiadosIntentos('guardar', 40)) {
            return false;
        }

        CuotasDetalleCatalog::cursoDelCicloOrFail($this->idCursoActivo);

        try {
            CuotasDetalleCatalog::guardarMontosCurso($this->idCuotas, $this->idCursoActivo, $this->montos);
        } catch (ValidationException $e) {
            $this->volcarErrores($e);

            return false;
        } catch (QueryException $e) {
            $this->dispatch('se-swal-error', mensaje: $this->mensajeQuery($e));

            return false;
        }

        $this->resetErrorBag('montos');
        $this->cargarCurso();
        if ($avisarExito) {
            $this->dispatch('se-swal-exito', mensaje: 'Importes guardados.');
        }

        return true;
    }

    private function demasiadosIntentos(string $accion, int $max): bool
    {
        $rateKey = 'cuotas-detalle:'.$accion.':'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, $max)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return true;
        }
        RateLimiter::hit($rateKey, 60);

        return false;
    }

    private function volcarErrores(ValidationException $e): void
    {
        foreach ($e->errors() as $campo => $mensajes) {
            $this->addError((string) $campo, (string) ($mensajes[0] ?? 'Dato inválido.'));
        }
    }

    private function mensajeQuery(QueryException $e): string
    {
        return PersistenciaColumnas::mensajeDesdeQueryException($e)
            ?? 'No se pudo guardar. Intente nuevamente.';
    }
}
