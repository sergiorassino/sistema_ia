<?php

namespace App\Livewire\CalificacionesSecundario;

use App\Support\CalificacionesSecundario\CierreAnualJournal;
use App\Support\Database\PersistenciaColumnas;
use App\Support\PermisosIaCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Lotes persistidos del cierre anual: listado, detalle y reversión condicional.
 */
class CierreAnualLotes extends Component
{
    use WithPagination;

    public const POR_PAGINA = 50;

    public int $loteId = 0;

    public string $buscar = '';

    public string $filtroTipo = '';

    /**
     * @var array{ok: int, omitidos: int, estado: string}|null
     */
    public ?array $informeReverso = null;

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_CIERRE_ANUAL), 403, 'Sin permiso para cierre anual.');
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_CIERRE_ANUAL_LOTES), 403, 'Sin permiso para lotes de cierre.');
        $ctx = schoolCtx();
        if (! str_contains(mb_strtolower($ctx->nivelNombre()), 'secundari')) {
            abort(403, 'Este módulo requiere contexto de Secundario.');
        }

        $id = CierreAnualJournal::leerSesionLoteId();
        if ($id > 0) {
            $lote = CierreAnualJournal::loteEnAlcance(
                $id,
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec,
            );
            if ($lote !== null) {
                $this->loteId = $id;
            } else {
                CierreAnualJournal::guardarSesionLoteId(0);
            }
        }
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroTipo(): void
    {
        $this->resetPage();
    }

    public function abrirLote(int $id): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_CIERRE_ANUAL), 403);
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_CIERRE_ANUAL_LOTES), 403);
        $ctx = schoolCtx();
        $lote = CierreAnualJournal::loteEnAlcance($id, (int) $ctx->idNivel, (int) $ctx->idTerlec);
        if ($lote === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró ese lote en el ciclo lectivo activo.');

            return;
        }
        $this->loteId = $id;
        $this->buscar = '';
        $this->filtroTipo = '';
        $this->informeReverso = null;
        CierreAnualJournal::guardarSesionLoteId($id);
        $this->resetPage();
    }

    public function volverListado(): void
    {
        $this->loteId = 0;
        $this->buscar = '';
        $this->filtroTipo = '';
        $this->informeReverso = null;
        CierreAnualJournal::guardarSesionLoteId(0);
        $this->resetPage();
    }

    public function revertirLote(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_CIERRE_ANUAL), 403);
        abort_unless(tienePermiso(PermisosIaCatalog::CALIF_CIERRE_ANUAL_LOTES), 403);
        $ctx = schoolCtx();
        $id = $this->loteId;
        if ($id < 1) {
            return;
        }

        $key = 'calificacionesSecundario:cierreAnual:revertir:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 120);

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        try {
            $res = CierreAnualJournal::revertirLote(
                $id,
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec,
            );
        } catch (QueryException $e) {
            $msg = PersistenciaColumnas::mensajeDesdeQueryException($e)
                ?? 'No se pudo revertir el lote.';
            $this->dispatch('se-swal-error', mensaje: $msg);

            return;
        } catch (RuntimeException $e) {
            $this->dispatch('se-swal-error', mensaje: $e->getMessage());

            return;
        }

        $this->informeReverso = $res;
        $msg = 'Se restauraron '.$res['ok'].' calificación(es).';
        if ($res['omitidos'] > 0) {
            $msg .= ' '.$res['omitidos'].' no se tocaron porque ya no coinciden con lo que escribió el cierre.';
        }
        $this->dispatch('se-swal-exito', mensaje: $msg);
    }

    public function render()
    {
        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;
        $journalListo = CierreAnualJournal::tablasListas();

        $lote = null;
        $filas = null;
        $lotes = null;
        $hayPosterior = false;
        $informe = null;

        if ($this->loteId > 0 && $journalListo) {
            $lote = CierreAnualJournal::loteEnAlcance($this->loteId, $idNivel, $idTerlec);
            if ($lote === null) {
                $this->loteId = 0;
                CierreAnualJournal::guardarSesionLoteId(0);
            } else {
                $informe = CierreAnualJournal::armarInformeDesdeLote($lote);
                $hayPosterior = CierreAnualJournal::hayLotePosterior($lote);
                $q = DB::table(CierreAnualJournal::TABLA_FILAS)
                    ->where('id_lote', $this->loteId)
                    ->orderBy('apellido')
                    ->orderBy('nombre')
                    ->orderBy('materia')
                    ->orderBy('id');

                $termino = trim($this->buscar);
                if (mb_strlen($termino) >= 2) {
                    $like = '%'.$termino.'%';
                    $q->where(function ($w) use ($like) {
                        $w->where('apellido', 'like', $like)
                            ->orWhere('nombre', 'like', $like)
                            ->orWhere('dni', 'like', $like)
                            ->orWhere('materia', 'like', $like);
                    });
                }
                if ($this->filtroTipo === CierreAnualJournal::TIPO_MATRIZ
                    || $this->filtroTipo === CierreAnualJournal::TIPO_PREVIA) {
                    $q->where('tipo', $this->filtroTipo);
                }

                $filas = $q->paginate(self::POR_PAGINA);
            }
        }

        if ($this->loteId < 1) {
            if ($journalListo) {
                $lotes = DB::table(CierreAnualJournal::TABLA_LOTES)
                    ->where('id_nivel', $idNivel)
                    ->where('id_terlec', $idTerlec)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->paginate(self::POR_PAGINA);
            } else {
                $lotes = new \Illuminate\Pagination\LengthAwarePaginator([], 0, self::POR_PAGINA);
            }
        }

        return view('livewire.calificaciones-secundario.cierre-anual-lotes', [
            'journalListo' => $journalListo,
            'lote' => $lote,
            'informe' => $informe,
            'filas' => $filas,
            'lotes' => $lotes,
            'hayPosterior' => $hayPosterior,
        ])
            ->layout(layoutMenuStaff(), ['pageTitle' => $this->loteId > 0
                ? 'Lote de cierre anual'
                : 'Lotes de cierre anual']);
    }
}
