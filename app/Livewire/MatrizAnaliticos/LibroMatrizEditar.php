<?php

namespace App\Livewire\MatrizAnaliticos;

use App\Support\MatrizAnaliticos\LibroMatrizAnalitico;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Edición en grilla de calificaciones del matriz (secundario) por alumno.
 * Cada celda se guarda al salir del foco (`guardarCelda`).
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

    public bool $modalDatosAdicionalesAbierto = false;

    public bool $modalNombreMateriaAbierto = false;

    public int $nombreOverrideIdMaterias = 0;

    public string $nombreOverrideMateriaBase = '';

    public string $nombreOverrideValor = '';

    public bool $nombreOverrideTiene = false;

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

    public function volver(): void
    {
        $this->irAlListado();
    }

    /**
     * Persistencia al salir de una celda editable.
     */
    public function guardarCelda(int $indice, string $campo, mixed $valor = null): void
    {
        abort_unless(tienePermiso(16), 403);

        $campo = trim($campo);
        if (! in_array($campo, LibroMatrizAnalitico::CAMPOS_LINEA_EDITABLES, true)) {
            return;
        }

        $linea = $this->lineas[$indice] ?? null;
        if (! is_array($linea)) {
            return;
        }

        $id = (int) ($linea['id'] ?? 0);
        if ($id < 1) {
            return;
        }

        $strValor = trim((string) ($valor ?? $linea[$campo] ?? ''));
        $this->lineas[$indice][$campo] = $strValor;

        $anterior = trim((string) ($this->lineasSnapshot[$id][$campo] ?? ''));
        if ($strValor === $anterior) {
            return;
        }

        $key = 'matrizAnaliticos:celda:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 240)) {
            $this->addError('guardar', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->resetErrorBag('guardar');
        $this->resetErrorBag('lineas.'.$indice.'.'.$campo);

        $max = match ($campo) {
            'calif' => 10,
            'mes' => 2,
            'ano' => 4,
            'cond' => 20,
            'escuapro' => 100,
            default => 100,
        };

        $this->validate([
            'lineas.'.$indice.'.'.$campo => ['nullable', 'string', 'max:'.$max],
        ]);

        $ctx = schoolCtx();
        $res = LibroMatrizAnalitico::guardarCampoLinea(
            $this->idLegajos,
            (int) $ctx->idNivel,
            $id,
            $campo,
            $strValor,
        );

        if (! $res['ok']) {
            $this->lineas[$indice][$campo] = $anterior;
            $this->addError('guardar', $res['error'] ?? 'No se pudo guardar la celda.');
            $this->addError('lineas.'.$indice.'.'.$campo, $res['error'] ?? 'Error al guardar.');

            return;
        }

        $normalizado = (string) ($res['valor_normalizado'] ?? $strValor);
        $this->lineas[$indice][$campo] = $normalizado;
        if (! isset($this->lineasSnapshot[$id])) {
            $this->lineasSnapshot[$id] = [
                'calif' => '',
                'mes' => '',
                'ano' => '',
                'cond' => '',
                'escuapro' => '',
            ];
        }
        $this->lineasSnapshot[$id][$campo] = $normalizado;
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

    public function abrirModalNombreMateria(int $indice): void
    {
        abort_unless(tienePermiso(16), 403);

        $linea = $this->lineas[$indice] ?? null;
        if (! is_array($linea)) {
            return;
        }

        $idMaterias = (int) ($linea['idMaterias'] ?? 0);
        if ($idMaterias < 1) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo identificar la asignatura.');

            return;
        }

        $this->nombreOverrideIdMaterias = $idMaterias;
        $this->nombreOverrideMateriaBase = trim((string) ($linea['materia_base'] ?? $linea['materia'] ?? ''));
        $this->nombreOverrideTiene = (bool) ($linea['tiene_override'] ?? false);
        $this->nombreOverrideValor = $this->nombreOverrideTiene
            ? trim((string) ($linea['materia'] ?? ''))
            : $this->nombreOverrideMateriaBase;
        $this->resetErrorBag('nombreOverrideValor');
        $this->resetErrorBag('guardarNombreOverride');
        $this->modalNombreMateriaAbierto = true;
    }

    public function cerrarModalNombreMateria(): void
    {
        $this->modalNombreMateriaAbierto = false;
        $this->nombreOverrideIdMaterias = 0;
        $this->nombreOverrideMateriaBase = '';
        $this->nombreOverrideValor = '';
        $this->nombreOverrideTiene = false;
    }

    public function guardarNombreMateriaOverride(): void
    {
        abort_unless(tienePermiso(16), 403);

        $key = 'matrizAnaliticos:nombre-override:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('guardarNombreOverride', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $validated = $this->validate([
            'nombreOverrideValor' => ['required', 'string', 'max:300'],
        ], [], [
            'nombreOverrideValor' => 'nombre para analítico',
        ]);

        $ctx = schoolCtx();
        $res = LibroMatrizAnalitico::guardarOverrideNombreMateria(
            $this->idLegajos,
            (int) $ctx->idNivel,
            $this->nombreOverrideIdMaterias,
            (string) $validated['nombreOverrideValor'],
        );

        if (! $res['ok']) {
            $this->addError('guardarNombreOverride', $res['error'] ?? 'No se pudo guardar.');

            return;
        }

        $this->aplicarOverridesEnLineas();
        $this->cerrarModalNombreMateria();
        $this->dispatch('se-swal-exito', mensaje: 'Nombre de asignatura para analítico guardado.');
    }

    public function eliminarNombreMateriaOverride(): void
    {
        abort_unless(tienePermiso(16), 403);

        $key = 'matrizAnaliticos:nombre-override-del:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('guardarNombreOverride', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();
        $res = LibroMatrizAnalitico::eliminarOverrideNombreMateria(
            $this->idLegajos,
            (int) $ctx->idNivel,
            $this->nombreOverrideIdMaterias,
        );

        if (! $res['ok']) {
            $this->addError('guardarNombreOverride', $res['error'] ?? 'No se pudo quitar el override.');

            return;
        }

        $this->aplicarOverridesEnLineas();
        $this->cerrarModalNombreMateria();
        $this->dispatch('se-swal-exito', mensaje: 'Se quitó el nombre especial del analítico.');
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

    /** Actualiza solo nombres/override sin pisar calificaciones editadas en la grilla. */
    private function aplicarOverridesEnLineas(): void
    {
        $overrides = LibroMatrizAnalitico::overridesNombreMateriaPorLegajo($this->idLegajos);

        foreach ($this->lineas as $i => $lin) {
            $idMaterias = (int) ($lin['idMaterias'] ?? 0);
            $base = trim((string) ($lin['materia_base'] ?? ''));
            $override = $idMaterias > 0 ? trim((string) ($overrides[$idMaterias] ?? '')) : '';
            $tiene = $override !== '';

            $this->lineas[$i]['tiene_override'] = $tiene;
            $this->lineas[$i]['materia'] = $tiene ? $override : $base;
        }
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

    public function render()
    {
        return view('livewire.matriz-analiticos.libro-matriz-editar')
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Editar matriz · '.$this->alumno['apellido']]);
    }
}
