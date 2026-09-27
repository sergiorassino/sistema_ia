<?php

namespace App\Livewire\Cuotas;

use App\Models\Cuota;
use App\Support\Cuotas\CuotasDetalleCatalog;
use App\Support\Cuotas\CuotasImportesCatalog;
use App\Support\Database\PersistenciaColumnas;
use App\Support\Navegacion\ContextoCuotasDetalleSesion;
use App\Support\PermisosCuotas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Listado de plantillas de cuota del ciclo activo — acceso a la discriminación por curso.
 */
class CuotasDetalleIndex extends Component
{
    public string $search = '';

    public bool $tablasListas = false;

    public bool $modalCopiar = false;

    public int $idCuotaOrigen = 0;

    public string $nombreCuotaOrigen = '';

    /** @var list<string> */
    public array $cuotasDestino = [];

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);
        $this->tablasListas = CuotasDetalleCatalog::tablasListas();
    }

    public function abrirEditor(int $idCuotas): void
    {
        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);

        CuotasImportesCatalog::cuotaDelCicloOrFail($idCuotas);
        ContextoCuotasDetalleSesion::fijar($idCuotas);

        $this->redirectRoute('cuotas.detalle.editar', navigate: true);
    }

    public function abrirCopiar(int $idCuotas): void
    {
        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);
        if (! $this->tablasListas) {
            return;
        }

        $cuota = CuotasDetalleCatalog::cuotaDelCicloOrFail($idCuotas);
        $conteo = CuotasDetalleCatalog::conteoCursosConItems([$idCuotas]);
        if (($conteo[$idCuotas] ?? 0) === 0) {
            $this->dispatch('se-swal-error', mensaje: 'Esta cuota no tiene discriminación para copiar.');

            return;
        }

        $this->idCuotaOrigen = $idCuotas;
        $this->nombreCuotaOrigen = (string) $cuota->nombre;
        $this->cuotasDestino = [];
        $this->resetErrorBag('cuotasDestino');
        $this->modalCopiar = true;
    }

    public function cerrarCopiar(): void
    {
        $this->modalCopiar = false;
        $this->idCuotaOrigen = 0;
        $this->nombreCuotaOrigen = '';
        $this->cuotasDestino = [];
    }

    public function marcarTodosDestino(): void
    {
        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);
        if ($this->idCuotaOrigen <= 0) {
            return;
        }

        $this->cuotasDestino = Cuota::query()
            ->where('idTerlec', CuotasImportesCatalog::idTerlecActivo())
            ->where('id', '!=', $this->idCuotaOrigen)
            ->orderBy('orden')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): string => (string) (int) $id)
            ->values()
            ->all();
    }

    public function limpiarDestino(): void
    {
        $this->cuotasDestino = [];
    }

    public function solicitarCopia(): void
    {
        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);
        $this->resetErrorBag('cuotasDestino');

        if ($this->destinosElegidos() === []) {
            $this->addError('cuotasDestino', 'Elija al menos una cuota de destino.');

            return;
        }

        $this->dispatch('cuotas-detalle-confirmar-copia');
    }

    public function copiarACuotas(): void
    {
        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);
        if ($this->idCuotaOrigen <= 0 || ! $this->tablasListas) {
            return;
        }
        if ($this->demasiadosIntentos()) {
            return;
        }

        $destino = $this->destinosElegidos();
        if ($destino === []) {
            $this->addError('cuotasDestino', 'Elija al menos una cuota de destino.');

            return;
        }

        try {
            $copiados = CuotasDetalleCatalog::copiarDiscriminacionACuotas($this->idCuotaOrigen, $destino);
        } catch (QueryException $e) {
            $this->dispatch('se-swal-error', mensaje: PersistenciaColumnas::mensajeDesdeQueryException($e)
                ?? 'No se pudo copiar. Intente nuevamente.');

            return;
        }

        if ($copiados === 0) {
            $this->addError('cuotasDestino', 'Esta cuota no tiene discriminación para copiar.');

            return;
        }

        $this->cerrarCopiar();
        $this->dispatch('se-swal-exito', mensaje: $copiados === 1
            ? 'Discriminación copiada a 1 cuota.'
            : "Discriminación copiada a {$copiados} cuotas.");
    }

    /**
     * Cuotas de destino del ciclo activo, sin la de origen.
     *
     * @return list<int>
     */
    private function destinosElegidos(): array
    {
        if ($this->idCuotaOrigen <= 0) {
            return [];
        }

        $ids = [];
        foreach ($this->cuotasDestino as $id) {
            $id = (int) $id;
            if ($id > 0 && $id !== $this->idCuotaOrigen) {
                $ids[$id] = $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        return Cuota::query()
            ->where('idTerlec', CuotasImportesCatalog::idTerlecActivo())
            ->whereIn('id', array_values($ids))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function demasiadosIntentos(): bool
    {
        $rateKey = 'cuotas-detalle-index:copia:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 20)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return true;
        }
        RateLimiter::hit($rateKey, 60);

        return false;
    }

    public function render()
    {
        $idTerlec = CuotasImportesCatalog::idTerlecActivo();
        $ano = (int) schoolCtx()->terlecAno();

        $cuotas = collect();
        $cursosPorCuota = [];
        if ($this->tablasListas) {
            $query = Cuota::query()
                ->where('idTerlec', $idTerlec)
                ->orderBy('orden')
                ->orderBy('id');

            $q = mb_strtolower(trim($this->search));
            if ($q !== '') {
                $query->whereRaw('LOWER(nombre) LIKE ?', ['%'.$q.'%']);
            }

            $cuotas = $query->get();
            $cursosPorCuota = CuotasDetalleCatalog::conteoCursosConItems(
                $cuotas->pluck('id')->map(fn ($id) => (int) $id)->all()
            );
        }

        $opcionesDestino = collect();
        $cursosDestinoConteo = [];
        if ($this->tablasListas && $this->modalCopiar && $this->idCuotaOrigen > 0) {
            $opcionesDestino = Cuota::query()
                ->where('idTerlec', $idTerlec)
                ->where('id', '!=', $this->idCuotaOrigen)
                ->orderBy('orden')
                ->orderBy('id')
                ->get(['id', 'nombre']);
            $cursosDestinoConteo = CuotasDetalleCatalog::conteoCursosConItems(
                $opcionesDestino->pluck('id')->map(fn ($id) => (int) $id)->all()
            );
        }

        return view('livewire.cuotas.detalle-index', [
            'cuotas' => $cuotas,
            'cursosPorCuota' => $cursosPorCuota,
            'opcionesDestino' => $opcionesDestino,
            'cursosDestinoConteo' => $cursosDestinoConteo,
            'esquemaAnterior' => ! $this->tablasListas && CuotasDetalleCatalog::esquemaAnterior(),
            'ano' => $ano,
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Discriminación de cuotas — {$ano}"]);
    }
}
