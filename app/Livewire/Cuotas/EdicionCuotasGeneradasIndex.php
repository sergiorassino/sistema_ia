<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\EdicionCuotasGeneradasCatalog;
use App\Support\Cuotas\EdicionCuotasGeneradasConsulta;
use App\Support\Cuotas\FiltroComparacionNumerica;
use App\Support\Cuotas\GeneracionMasivaCuotasConsulta;
use App\Support\PermisosCuotas;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Edición masiva de cuotas generadas: filtros, vista previa y varios campos a la vez.
 */
class EdicionCuotasGeneradasIndex extends Component
{
    public int $idNivel = 0;

    public int $idCurso = 0;

    public int $idCuota = 0;

    public string $pagadoOp = '';

    public string $pagadoValor = '';

    public string $saldoOp = '';

    public string $saldoValor = '';

    public string $search = '';

    public bool $mostrandoResultados = false;

    /** @var array<string, mixed> */
    public array $filtrosActivos = [];

    /** @var list<array<string, mixed>> */
    public array $registrosVista = [];

    /** @var list<int> */
    public array $idsRegistros = [];

    /** IDs marcados en la vista previa (por defecto todos tras Buscar). */
    /** @var list<int> */
    public array $idsRegistrosSeleccionados = [];

    public string $nuevoImporte = '';

    public string $nuevoVenc1 = '';

    public string $nuevoVenc2 = '';

    public string $nuevoNueVenc = '';

    public bool $limpiarNueVenc = false;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeEdicionCuotasGeneradas(), 403);
    }

    public function updatedIdNivel(): void
    {
        $this->idCurso = 0;
        $this->ocultarResultados();
    }

    public function updatedIdCurso(): void
    {
        $this->ocultarResultados();
    }

    public function updatedIdCuota(): void
    {
        $this->ocultarResultados();
    }

    public function updatedPagadoOp(): void
    {
        if ($this->pagadoOp === '') {
            $this->pagadoValor = '';
        }
        $this->ocultarResultados();
    }

    public function updatedSaldoOp(): void
    {
        if ($this->saldoOp === '') {
            $this->saldoValor = '';
        }
        $this->ocultarResultados();
    }

    public function updatedPagadoValor(): void
    {
        $this->ocultarResultados();
    }

    public function updatedSaldoValor(): void
    {
        $this->ocultarResultados();
    }

    public function alternarLimpiarNueVenc(): void
    {
        $this->limpiarNueVenc = ! $this->limpiarNueVenc;

        if ($this->limpiarNueVenc) {
            $this->nuevoNueVenc = '';
            $this->resetValidation('nuevoNueVenc');
        }
    }

    public function buscar(): void
    {
        abort_unless(PermisosCuotas::puedeEdicionCuotasGeneradas(), 403);

        try {
            $this->filtrosActivos = EdicionCuotasGeneradasConsulta::normalizarFiltros($this->parametrosFiltro());
        } catch (ValidationException $e) {
            throw $e;
        }

        $registros = EdicionCuotasGeneradasConsulta::registrosParaEdicion($this->filtrosActivos);

        $this->registrosVista = [];
        $this->idsRegistros = [];

        foreach ($registros as $registro) {
            $this->registrosVista[] = EdicionCuotasGeneradasCatalog::filaVistaPrevia($registro);
            $this->idsRegistros[] = (int) $registro->id;
        }

        $this->idsRegistrosSeleccionados = $this->idsRegistros;

        $this->mostrandoResultados = true;
        $this->reiniciarEdicionMasiva();
        $this->resetValidation();
    }

    public function updatedIdsRegistrosSeleccionados(): void
    {
        $this->normalizarIdsRegistrosSeleccionados();
    }

    public function seleccionarTodosRegistros(): void
    {
        $idsVisibles = collect($this->registrosVisibles())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($this->tieneBusquedaEnListado()) {
            $this->idsRegistrosSeleccionados = collect($this->idsRegistrosSeleccionados)
                ->merge($idsVisibles)
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values()
                ->all();
        } else {
            $this->idsRegistrosSeleccionados = $this->idsRegistros;
        }

        $this->normalizarIdsRegistrosSeleccionados();
    }

    public function quitarTodosRegistros(): void
    {
        $this->idsRegistrosSeleccionados = [];
    }

    public function limpiarFiltros(): void
    {
        $this->idNivel = 0;
        $this->idCurso = 0;
        $this->idCuota = 0;
        $this->pagadoOp = '';
        $this->pagadoValor = '';
        $this->saldoOp = '';
        $this->saldoValor = '';
        $this->search = '';
        $this->ocultarResultados();
        $this->resetValidation();
    }

    public function aplicarMasivo(): void
    {
        abort_unless(PermisosCuotas::puedeEdicionCuotasGeneradas(), 403);

        if (! $this->puedeAplicarMasivo) {
            if ($this->idsRegistrosSeleccionados === []) {
                $this->dispatch('se-swal-error', mensaje: 'Seleccione al menos un registro para actualizar.');

                return;
            }

            $this->dispatch('se-swal-error', mensaje: 'Indique al menos un dato nuevo para aplicar.');

            return;
        }

        $rateKey = 'cuotas:editar-generadas-masivo:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 120);

        $cambios = $this->parametrosCambios();

        try {
            $reglas = EdicionCuotasGeneradasCatalog::reglasCambios($cambios);
            if ($reglas !== []) {
                $this->validate($reglas, [
                    'nuevoImporte.required' => 'Indique el importe base.',
                    'nuevoVenc1.required' => 'Indique el vencimiento 1.',
                    'nuevoVenc1.date' => 'La fecha del vencimiento 1 no es válida.',
                    'nuevoVenc2.required' => 'Indique el vencimiento 2.',
                    'nuevoVenc2.date' => 'La fecha del vencimiento 2 no es válida.',
                    'nuevoNueVenc.required' => 'Indique el vencimiento actualizado o marque quitar.',
                    'nuevoNueVenc.date' => 'La fecha del vencimiento actualizado no es válida.',
                ]);
            }
            EdicionCuotasGeneradasCatalog::validarCambios($cambios);
        } catch (ValidationException $e) {
            throw $e;
        }

        $resultado = EdicionCuotasGeneradasCatalog::aplicarMasivo($this->idsRegistrosSeleccionados, $cambios);

        $actualizados = (int) ($resultado['actualizados'] ?? 0);
        $importeActualizados = (int) ($resultado['importeActualizados'] ?? 0);
        $importeOmitidos = (int) ($resultado['importeOmitidos'] ?? 0);
        $fallos = (int) ($resultado['fallos'] ?? 0);

        if ($actualizados < 1) {
            $mensaje = 'No se actualizó ningún registro.';
            if (EdicionCuotasGeneradasCatalog::cambioImporteActivo($cambios) && $importeOmitidos > 0) {
                $mensaje .= ' El importe solo aplica a cuotas sin pago (pagado ≤ 0) y con saldo (faltapa > 0).';
            }
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return;
        }

        $partes = [];
        $partes[] = $actualizados === 1
            ? 'Se actualizó 1 cuota.'
            : "Se actualizaron {$actualizados} cuotas.";
        if ($importeActualizados > 0) {
            $partes[] = "Importe recalculado con beca: {$importeActualizados}.";
        }
        if ($importeOmitidos > 0) {
            $partes[] = "Importe omitido (con pago o sin saldo / matrícula): {$importeOmitidos}.";
        }
        if ($fallos > 0) {
            $partes[] = "No disponibles: {$fallos}.";
        }

        session()->flash('success', implode(' ', $partes));

        $this->buscar();
    }

    public function getPuedeAplicarMasivoProperty(): bool
    {
        if (! $this->mostrandoResultados || $this->idsRegistrosSeleccionados === []) {
            return false;
        }

        return EdicionCuotasGeneradasCatalog::tieneCambiosParaAplicar($this->parametrosCambios());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function registrosVisibles(): array
    {
        $q = mb_strtolower(trim($this->search));
        if ($q === '') {
            return $this->registrosVista;
        }

        return array_values(array_filter(
            $this->registrosVista,
            fn (array $row): bool => str_contains(mb_strtolower((string) ($row['estudiante'] ?? '')), $q)
                || str_contains(mb_strtolower((string) ($row['cursoLabel'] ?? '')), $q)
                || str_contains(mb_strtolower((string) ($row['cuotaNombre'] ?? '')), $q),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function parametrosFiltro(): array
    {
        return [
            'idNivel' => $this->idNivel,
            'idCurso' => $this->idCurso,
            'idCuota' => $this->idCuota,
            'pagadoOp' => $this->pagadoOp,
            'pagadoValor' => $this->pagadoValor,
            'saldoOp' => $this->saldoOp,
            'saldoValor' => $this->saldoValor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parametrosCambios(): array
    {
        return [
            'importe' => $this->nuevoImporte,
            'venc1' => $this->nuevoVenc1,
            'venc2' => $this->nuevoVenc2,
            'nueVenc' => $this->nuevoNueVenc,
            'limpiarNueVenc' => $this->limpiarNueVenc,
        ];
    }

    private function reiniciarEdicionMasiva(): void
    {
        $this->nuevoImporte = '';
        $this->nuevoVenc1 = '';
        $this->nuevoVenc2 = '';
        $this->nuevoNueVenc = '';
        $this->limpiarNueVenc = false;
    }

    private function ocultarResultados(): void
    {
        $this->mostrandoResultados = false;
        $this->registrosVista = [];
        $this->idsRegistros = [];
        $this->idsRegistrosSeleccionados = [];
        $this->filtrosActivos = [];
        $this->reiniciarEdicionMasiva();
    }

    private function normalizarIdsRegistrosSeleccionados(): void
    {
        $allowed = array_flip($this->idsRegistros);

        $this->idsRegistrosSeleccionados = collect($this->idsRegistrosSeleccionados)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0 && isset($allowed[$id]))
            ->unique()
            ->values()
            ->all();
    }

    private function tieneBusquedaEnListado(): bool
    {
        return mb_strtolower(trim($this->search)) !== '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function registrosVistaSeleccionados(): array
    {
        $marcados = array_flip($this->idsRegistrosSeleccionados);

        return array_values(array_filter(
            $this->registrosVista,
            fn (array $row): bool => isset($marcados[(int) ($row['id'] ?? 0)]),
        ));
    }

    public function render()
    {
        $ano = (int) schoolCtx()->terlecAno();
        $idNivelFiltro = $this->idNivel > 0 ? $this->idNivel : null;
        $cambios = $this->parametrosCambios();
        $registrosSeleccionados = $this->registrosVistaSeleccionados();
        $elegiblesImporte = EdicionCuotasGeneradasCatalog::contarElegiblesImporte($registrosSeleccionados);
        $totalSeleccionados = count($this->idsRegistrosSeleccionados);

        return view('livewire.cuotas.edicion-cuotas-generadas', [
            'ano' => $ano,
            'niveles' => EdicionCuotasGeneradasConsulta::nivelesParaSelector(),
            'cursos' => EdicionCuotasGeneradasConsulta::cursosParaSelector($idNivelFiltro),
            'cuotas' => EdicionCuotasGeneradasConsulta::cuotasParaSelector(),
            'opcionesComparador' => FiltroComparacionNumerica::opcionesEtiquetas(),
            'registros' => $this->registrosVisibles(),
            'totalRegistros' => count($this->idsRegistros),
            'totalSeleccionados' => $totalSeleccionados,
            'elegiblesImporte' => $elegiblesImporte,
            'puedeAplicarMasivo' => $this->puedeAplicarMasivo,
            'textoConfirmacion' => $this->mostrandoResultados && $this->puedeAplicarMasivo
                ? EdicionCuotasGeneradasCatalog::textoConfirmacion($cambios, $totalSeleccionados, $elegiblesImporte)
                : '',
            'etiquetaCurso' => fn ($curso) => GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($curso),
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Editar cuotas generadas — {$ano}"]);
    }
}
