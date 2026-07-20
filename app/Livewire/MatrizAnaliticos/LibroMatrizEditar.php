<?php

namespace App\Livewire\MatrizAnaliticos;

use App\Support\MatrizAnaliticos\LibroMatrizAnalitico;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Edición en grilla de calificaciones del matriz (secundario) por alumno.
 */
class LibroMatrizEditar extends Component
{
    public int $idLegajos = 0;

    /** @var array<string, string> */
    public array $alumno = [];

    /** @var list<array<string, mixed>> */
    public array $lineas = [];

    /** @var array<int, array{calif: string, mes: string, ano: string, cond: string, escuapro: string}> */
    public array $lineasSnapshot = [];

    public bool $modalSalirAbierto = false;

    public bool $modalDatosAdicionalesAbierto = false;

    public ?int $idAnaliticoDato = null;

    public string $analCohorte = '';

    public string $analObservaciones = '';

    public string $analParaCompletar = '';

    public string $analValidez = '';

    public string $serie = '';

    public string $numero = '';

    public string $analLibroFolio = '';

    public string $analFechaEmision = '';

    public string $analParaPre = '';

    public string $buscarRetorno = '';

    public function mount(): void
    {
        $idLegajos = \App\Support\Navegacion\ContextoEstudianteSesion::legajo(
            \App\Support\Navegacion\ContextoEstudianteSesion::MATRIZ_ANALITICOS,
        );
        abort_if($idLegajos === null, 404);

        abort_unless(tienePermiso(16), 403, 'Sin permiso para Libro Matriz / Analítico.');

        abort_unless(schoolEsNivelSecundario(), 403, 'Este módulo requiere contexto de Secundario.');

        $alumno = LibroMatrizAnalitico::alumno($idLegajos);
        if ($alumno === null) {
            abort(404, 'Legajo no encontrado.');
        }

        $this->idLegajos = $idLegajos;
        $this->alumno = $alumno;
        $this->buscarRetorno = LibroMatrizAnalitico::buscarRetornoListado();
        $ctx = schoolCtx();
        $this->cargarLineasDesdeServidor($idLegajos, (int) $ctx->idNivel);
    }

    public function solicitarVolver(): void
    {
        if ($this->tieneCambiosSinGuardar()) {
            $this->modalSalirAbierto = true;

            return;
        }

        $this->irAlListado();
    }

    public function cerrarModalSalir(): void
    {
        $this->modalSalirAbierto = false;
    }

    public function salirSinGuardar(): void
    {
        $this->modalSalirAbierto = false;
        $this->irAlListado();
    }

    public function guardarYSalir(): void
    {
        if ($this->persistirLineas()) {
            $this->modalSalirAbierto = false;
            $this->irAlListado();
        } else {
            $this->modalSalirAbierto = false;
        }
    }

    public function guardar(): void
    {
        if ($this->persistirLineas()) {
            session()->flash('success', $this->mensajeExitoGuardado);
        }
    }

    public function abrirModalDatosAdicionales(): void
    {
        $this->cargarDatosAdicionalesDesdeServidor();
        $this->resetErrorBag('guardarDatosAdicionales');
        $this->modalDatosAdicionalesAbierto = true;
    }

    public function cerrarModalDatosAdicionales(): void
    {
        $this->modalDatosAdicionalesAbierto = false;
    }

    public function guardarDatosAdicionales(): void
    {
        abort_unless(tienePermiso(16), 403);

        $key = 'matrizAnaliticos:datos-adicionales:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('guardarDatosAdicionales', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $validated = $this->validate(LibroMatrizAnalitico::reglasDatosAdicionales());

        if (! LibroMatrizAnalitico::guardarDatosAdicionales($this->idLegajos, $validated)) {
            $this->addError('guardarDatosAdicionales', 'No se pudo guardar. Verifique los datos.');

            return;
        }

        $this->cargarDatosAdicionalesDesdeServidor();
        $this->modalDatosAdicionalesAbierto = false;
        $this->dispatch('se-swal-exito', mensaje: 'Datos adicionales guardados.');
    }

    private function cargarDatosAdicionalesDesdeServidor(): void
    {
        $datos = LibroMatrizAnalitico::datosAdicionales($this->idLegajos);
        $this->idAnaliticoDato = $datos['id'];
        $this->analCohorte = $datos['analCohorte'];
        $this->analObservaciones = $datos['analObservaciones'];
        $this->analParaCompletar = $datos['analParaCompletar'];
        $this->analValidez = $datos['analValidez'];
        $this->serie = $datos['serie'];
        $this->numero = $datos['numero'];
        $this->analLibroFolio = $datos['analLibroFolio'];
        $this->analFechaEmision = $datos['analFechaEmision'];
        $this->analParaPre = $datos['analParaPre'];
    }

    private string $mensajeExitoGuardado = '';

    private function tieneCambiosSinGuardar(): bool
    {
        return $this->snapshotLineas($this->lineas) !== $this->lineasSnapshot;
    }

    private function persistirLineas(): bool
    {
        abort_unless(tienePermiso(16), 403);

        $key = 'matrizAnaliticos:guardar:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('guardar', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return false;
        }
        RateLimiter::hit($key, 60);

        $validated = $this->validate($this->reglasLineas());

        $ctx = schoolCtx();
        $res = LibroMatrizAnalitico::guardarLineas(
            $this->idLegajos,
            (int) $ctx->idNivel,
            $validated['lineas'],
        );

        if ($res['ok'] < 1) {
            $this->addError('guardar', 'No se pudo guardar ningún registro. Verifique los datos.');

            return false;
        }

        $msg = $res['ok'] === 1
            ? 'Se guardó 1 registro.'
            : 'Se guardaron '.$res['ok'].' registros.';

        if ($res['omitidos'] > 0) {
            $msg .= ' '.$res['omitidos'].' fila(s) no se actualizaron.';
        }

        $this->mensajeExitoGuardado = $msg;
        $this->cargarLineasDesdeServidor($this->idLegajos, (int) $ctx->idNivel);

        return true;
    }

    private function cargarLineasDesdeServidor(int $idLegajos, int $idNivel): void
    {
        $this->lineas = LibroMatrizAnalitico::lineasEdicion(
            $idLegajos,
            $idNivel,
            (string) ($this->alumno['apellido'] ?? ''),
            (string) ($this->alumno['nombre'] ?? ''),
        );
        $this->lineasSnapshot = $this->snapshotLineas($this->lineas);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array<int, array{calif: string, mes: string, ano: string, cond: string, escuapro: string}>
     */
    private function snapshotLineas(array $lineas): array
    {
        $out = [];

        foreach ($lineas as $linea) {
            $id = (int) ($linea['id'] ?? 0);
            if ($id < 1) {
                continue;
            }

            $out[$id] = [
                'calif' => trim((string) ($linea['calif'] ?? '')),
                'mes' => trim((string) ($linea['mes'] ?? '')),
                'ano' => trim((string) ($linea['ano'] ?? '')),
                'cond' => trim((string) ($linea['cond'] ?? '')),
                'escuapro' => trim((string) ($linea['escuapro'] ?? '')),
            ];
        }

        ksort($out);

        return $out;
    }

    private function irAlListado(): void
    {
        $this->redirect(
            LibroMatrizAnalitico::urlListado($this->buscarRetorno),
            navigate: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasLineas(): array
    {
        return [
            'lineas' => ['required', 'array'],
            'lineas.*.id' => ['required', 'integer', 'min:1'],
            'lineas.*.calif' => ['nullable', 'string', 'max:10'],
            'lineas.*.mes' => ['nullable', 'string', 'max:2'],
            'lineas.*.ano' => ['nullable', 'string', 'max:4'],
            'lineas.*.cond' => ['nullable', 'string', 'max:20'],
            'lineas.*.escuapro' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function render()
    {
        return view('livewire.matriz-analiticos.libro-matriz-editar')
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Editar matriz · '.$this->alumno['apellido']]);
    }
}
