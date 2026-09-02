<?php

namespace App\Livewire\Listados;

use App\Models\Familia;
use App\Support\Database\PersistenciaColumnas;
use App\Support\Listados\ListadoFamiliasConsulta;
use App\Support\Listados\ListadoFamiliasEdicion;
use App\Support\Listados\ListadoFamiliasFiltros;
use App\Support\PermisosIaCatalog;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de familias con estudiantes del ciclo lectivo activo.
 * Con permiso de gestión, Familia / Responsable / DNI / Email se editan en la grilla.
 */
class ListadoFamiliasIndex extends Component
{
    use WithPagination;

    public string $search = '';

    /** Vacío = todos los niveles del alcance (en Administración). */
    public string $idNivel = '';

    /**
     * Borrador por familia de la página visible.
     *
     * @var array<string, array{apellido: string, responsable: string, dniResp: string, email: string}>
     */
    public array $filas = [];

    /** Evita reentrada cuando el blur dispara más de una vez en el mismo request. */
    protected bool $persistiendo = false;

    /**
     * Hash del último payload guardado por familia.
     *
     * @var array<string, string>
     */
    public array $ultimoGuardadoHashes = [];

    /** @var array<string, array{except?: mixed, as?: string}> */
    protected $queryString = [
        'search' => ['except' => '', 'as' => 'buscar'],
        'idNivel' => ['except' => '', 'as' => 'filtro_nivel'],
    ];

    public function mount(): void
    {
        abort_unless(puedeConsultarLegajosEstudiantes(), 403);
        abort_unless((int) schoolCtx()->idTerlec > 0, 403);
    }

    public function updatedSearch(): void
    {
        $this->filas = [];
        $this->ultimoGuardadoHashes = [];
        $this->resetPage();
    }

    public function updatedIdNivel(): void
    {
        $this->idNivel = $this->idNivelNormalizadoParaVista();
        $this->filas = [];
        $this->ultimoGuardadoHashes = [];
        $this->resetPage();
    }

    public function guardarFamilia(int $id): void
    {
        abort_unless($this->puedeEditar(), 403, 'Sin permiso para modificar familias.');

        if ($this->persistiendo || $id < 1) {
            return;
        }

        $key = (string) $id;
        $this->persistiendo = true;

        try {
            $tieneDniResp = ListadoFamiliasConsulta::tieneDniResp();
            $familia = ListadoFamiliasConsulta::familiaEnAlcance(
                $id,
                $this->idNivel === '' ? 0 : (int) $this->idNivel,
            );
            if ($familia === null) {
                $this->dispatch('se-swal-error', mensaje: 'No se pudo guardar: la familia no está en el alcance del listado.');

                return;
            }

            $desdeDb = ListadoFamiliasEdicion::filaDesdeModelo($familia, $tieneDniResp);
            $desdeGrilla = $this->filas[$key] ?? $this->filas[$id] ?? [];
            $mezcla = ListadoFamiliasEdicion::mezclar(
                $desdeDb,
                is_array($desdeGrilla) ? $desdeGrilla : [],
            );
            $normalizada = ListadoFamiliasEdicion::normalizar($mezcla, $tieneDniResp);
            $this->filas[$key] = ListadoFamiliasEdicion::filaParaGrilla($normalizada, $tieneDniResp);

            $hash = ListadoFamiliasEdicion::hash($normalizada);
            if (($this->ultimoGuardadoHashes[$key] ?? '') === $hash) {
                $this->skipRender();

                return;
            }

            $rateKey = 'listado-familias:save:'.(auth()->id() ?? 'guest');
            if (RateLimiter::tooManyAttempts($rateKey, 180)) {
                $this->addError(
                    'filas.'.$key.'.apellido',
                    'Demasiados intentos. Espere un momento e intente nuevamente.',
                );

                return;
            }
            RateLimiter::hit($rateKey, 60);

            $this->resetValidation([
                'filas.'.$key.'.apellido',
                'filas.'.$key.'.responsable',
                'filas.'.$key.'.dniResp',
                'filas.'.$key.'.email',
            ]);

            // Validar el DNI ya sin puntos; $this->validate() leería el valor de la grilla.
            $validador = Validator::make(
                ['filas' => [$key => $normalizada]],
                ListadoFamiliasEdicion::reglas($key, $normalizada, $tieneDniResp),
                ListadoFamiliasEdicion::mensajes($key),
            );
            if ($validador->fails()) {
                foreach ($validador->errors()->getMessages() as $campo => $mensajes) {
                    foreach ($mensajes as $mensaje) {
                        $this->addError($campo, $mensaje);
                    }
                }

                return;
            }

            $payload = ListadoFamiliasEdicion::payload($normalizada, $tieneDniResp);
            $payload = PersistenciaColumnas::reemplazarNulosExplicitos('familias', $payload);
            $preparado = PersistenciaColumnas::prepararPayload('familias', $payload);

            if ($preparado['columnas_con_valor_sin_columna'] !== []) {
                $this->dispatch(
                    'se-swal-error',
                    mensaje: PersistenciaColumnas::mensajeColumnasInexistentes(
                        'familias',
                        $preparado['columnas_con_valor_sin_columna'],
                    ),
                );

                return;
            }

            try {
                Familia::query()->whereKey($id)->update($preparado['payload']);
            } catch (QueryException $e) {
                $this->dispatch(
                    'se-swal-error',
                    mensaje: PersistenciaColumnas::mensajeDesdeQueryException($e)
                        ?? 'No se pudo guardar la familia. Verifique los datos e intente nuevamente.',
                );

                return;
            }

            $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
                'familias',
                ['id' => $id],
                $preparado['payload'],
            );
            if ($noPersistidas !== []) {
                $this->dispatch(
                    'se-swal-error',
                    mensaje: PersistenciaColumnas::mensajeColumnasNoPersistidas('familias', $noPersistidas),
                );

                return;
            }

            $this->ultimoGuardadoHashes[$key] = $hash;
            $this->skipRender();
        } finally {
            $this->persistiendo = false;
        }
    }

    public function render()
    {
        $this->idNivel = $this->idNivelNormalizadoParaVista();
        $idNivel = $this->idNivel === '' ? 0 : (int) $this->idNivel;
        $familias = ListadoFamiliasConsulta::listar($this->search, $idNivel);
        $puedeEditar = $this->puedeEditar();
        $tieneDniResp = ListadoFamiliasConsulta::tieneDniResp();

        if ($puedeEditar) {
            $this->hidratarFilas($familias->getCollection(), $tieneDniResp);
        }

        return view('listados::livewire.listados.familias-index', [
            'familias' => $familias,
            'niveles' => ListadoFamiliasConsulta::nivelesParaSelector(),
            'mostrarFiltroNivel' => true,
            'tieneDniResp' => $tieneDniResp,
            'puedeEditar' => $puedeEditar,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Listado de familias']);
    }

    public function urlListadoPdf(): string
    {
        return route('listados.familias.pdf', [
            'ref' => OpaqueRouteToken::forListadoFamiliasPdf($this->filtrosExportacion()->aPayload()),
        ]);
    }

    public function urlListadoExcel(): string
    {
        return route('listados.familias.excel', [
            'ref' => OpaqueRouteToken::forListadoFamiliasExcel($this->filtrosExportacion()->aPayload()),
        ]);
    }

    private function puedeEditar(): bool
    {
        return tienePermiso(PermisosIaCatalog::LEGAJOS_FAMILIAS_GESTION);
    }

    /**
     * @param  Collection<int, \App\Models\Familia>  $familias
     */
    private function hidratarFilas(Collection $familias, bool $tieneDniResp): void
    {
        foreach ($familias as $familia) {
            $key = (string) $familia->id;
            if (isset($this->filas[$key])) {
                continue;
            }

            $desdeDb = ListadoFamiliasEdicion::filaDesdeModelo($familia, $tieneDniResp);
            $this->ultimoGuardadoHashes[$key] = ListadoFamiliasEdicion::hash($desdeDb);
            $this->filas[$key] = ListadoFamiliasEdicion::filaParaGrilla($desdeDb, $tieneDniResp);
        }
    }

    private function filtrosExportacion(): ListadoFamiliasFiltros
    {
        return ListadoFamiliasFiltros::desdeLivewire($this->search, $this->idNivel);
    }

    private function idNivelNormalizadoParaVista(): string
    {
        $id = ListadoFamiliasConsulta::normalizarIdNivel((int) $this->idNivel);

        return $id > 0 ? (string) $id : '';
    }
}
