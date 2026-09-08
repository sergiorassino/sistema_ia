<?php

namespace App\Livewire\Examenes;

use App\Livewire\Examenes\Concerns\RequiresPermisoExamenes;
use App\Support\Examenes\TercerMateriaGestor;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class TercerMateriaIndex extends Component
{
    use RequiresPermisoExamenes;

    /** @var array<int, array<string, string>> */
    public array $ediciones = [];

    /** Valores ya persistidos (evita guardar sin cambios al hacer blur). */
    /** @var array<int, array<string, string>> */
    public array $persistidos = [];

    public string $busqueda = '';

    /** Curso de la materia adeudada (etiqueta de listado). */
    public string $filtroCurso = '';

    public string $filtroCursoActual = '';

    public function mount(): void
    {
        abort_unless(tenantBoletinMuestraTercerMateria(), 404);

        $this->cargarEdiciones();
    }

    public function updatedBusqueda(mixed $value): void
    {
        $this->busqueda = mb_substr(trim((string) $value), 0, 80);
    }

    public function updatedFiltroCurso(mixed $value): void
    {
        $this->filtroCurso = mb_substr(trim((string) $value), 0, 120);
    }

    public function updatedFiltroCursoActual(mixed $value): void
    {
        $this->filtroCursoActual = mb_substr(trim((string) $value), 0, 120);
    }

    /**
     * Guardado al salir del campo (wire:blur). Livewire 4 no invoca updated() por cada clave anidada de ediciones.*.
     */
    public function guardarCampoTm(int $idCalificacion, string $campo, string $valor): void
    {
        $this->skipRender();

        $campo = trim($campo);
        if (! in_array($campo, TercerMateriaGestor::CAMPOS_TM, true)) {
            return;
        }

        $this->ediciones[$idCalificacion][$campo] = TercerMateriaGestor::normalizarValorTm($valor);
        $this->persistirCampoTm($idCalificacion, $campo);
    }

    private function persistirCampoTm(int $idCalificacion, string $campo): void
    {
        if (! in_array($campo, TercerMateriaGestor::CAMPOS_TM, true)) {
            return;
        }

        $valor = TercerMateriaGestor::normalizarValorTm((string) ($this->ediciones[$idCalificacion][$campo] ?? ''));
        $this->ediciones[$idCalificacion][$campo] = $valor;
        $anterior = TercerMateriaGestor::normalizarValorTm((string) ($this->persistidos[$idCalificacion][$campo] ?? ''));
        if ($valor === $anterior) {
            return;
        }

        $key = 'tercer-materia-guardar:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 240)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return;
        }

        $resultado = TercerMateriaGestor::actualizarCamposTm(
            $idCalificacion,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            [$campo => $valor],
        );

        if (! ($resultado['ok'] ?? false)) {
            $this->ediciones[$idCalificacion][$campo] = $anterior;
            $this->dispatch('se-swal-error', mensaje: $resultado['error'] ?? 'No se pudo guardar.');

            return;
        }

        $fila = $resultado['fila'] ?? [];
        $normalizado = (string) ($fila[$campo] ?? '');
        $this->ediciones[$idCalificacion][$campo] = $normalizado;
        $this->persistidos[$idCalificacion][$campo] = $normalizado;
    }

    public function render()
    {
        $ctx = schoolCtx();
        $todas = [];
        $filas = [];

        if ($ctx->isValid()) {
            $todas = TercerMateriaGestor::filas((int) $ctx->idNivel, (int) $ctx->idTerlec);
            $filas = TercerMateriaGestor::filtrarFilasListado(
                $todas,
                $this->busqueda,
                $this->filtroCurso,
                $this->filtroCursoActual,
            );
        }

        $queryPdf = array_filter([
            'q' => $this->busqueda,
            'curso' => $this->filtroCurso,
            'curso_actual' => $this->filtroCursoActual,
        ], static fn (string $v): bool => $v !== '');

        return view('livewire.examenes.tercer-materia', [
            'filas' => $filas,
            'pdfUrl' => route('examenes.tercer-materia.pdf', $queryPdf),
            'totalFilas' => count($filas),
            'totalSinFiltro' => count($todas),
            'opcionesCurso' => TercerMateriaGestor::opcionesFiltro($todas, 'curso'),
            'opcionesCursoActual' => TercerMateriaGestor::opcionesFiltro($todas, 'curso_actual'),
            'opcionesTm' => TercerMateriaGestor::opcionesSelector(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Gestión de tercer materia']);
    }

    private function cargarEdiciones(): void
    {
        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return;
        }

        $this->ediciones = [];
        $this->persistidos = [];

        foreach (TercerMateriaGestor::filas((int) $ctx->idNivel, (int) $ctx->idTerlec) as $fila) {
            $id = (int) $fila['id'];
            $this->ediciones[$id] = [];
            $this->persistidos[$id] = [];
            foreach (TercerMateriaGestor::CAMPOS_TM as $campo) {
                $valor = (string) ($fila[$campo] ?? '');
                $this->ediciones[$id][$campo] = $valor;
                $this->persistidos[$id][$campo] = $valor;
            }
        }
    }
}
