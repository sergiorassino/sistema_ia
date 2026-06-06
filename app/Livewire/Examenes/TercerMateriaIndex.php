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

    public function mount(): void
    {
        abort_unless(tenantBoletinMuestraTercerMateria(), 404);

        $this->cargarEdiciones();
    }

    /**
     * Guardado al salir del campo (wire:blur). Livewire 4 no invoca updated() por cada clave anidada de ediciones.*.
     */
    public function guardarCampoTm(int $idCalificacion, string $campo, string $valor): void
    {
        $campo = trim($campo);
        if (! in_array($campo, TercerMateriaGestor::CAMPOS_TM, true)) {
            return;
        }

        $this->ediciones[$idCalificacion][$campo] = trim($valor);
        $this->persistirCampoTm($idCalificacion, $campo);
    }

    private function persistirCampoTm(int $idCalificacion, string $campo): void
    {
        if (! in_array($campo, TercerMateriaGestor::CAMPOS_TM, true)) {
            return;
        }

        $valor = trim((string) ($this->ediciones[$idCalificacion][$campo] ?? ''));
        $anterior = trim((string) ($this->persistidos[$idCalificacion][$campo] ?? ''));
        if ($valor === $anterior) {
            return;
        }

        $key = 'tercer-materia-guardar:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 240)) {
            $this->addError('guardar', 'Demasiados intentos. Espere un momento.');

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
            $this->addError('guardar', $resultado['error'] ?? 'No se pudo guardar.');
            $this->ediciones[$idCalificacion][$campo] = $anterior;

            return;
        }

        $this->resetErrorBag('guardar');
        $fila = $resultado['fila'] ?? [];
        $normalizado = (string) ($fila[$campo] ?? '');
        $this->ediciones[$idCalificacion][$campo] = $normalizado;
        $this->persistidos[$idCalificacion][$campo] = $normalizado;
    }

    public function render()
    {
        $ctx = schoolCtx();
        $filas = [];
        $pdfUrl = route('examenes.tercer-materia.pdf');

        if ($ctx->isValid()) {
            $filas = TercerMateriaGestor::filas((int) $ctx->idNivel, (int) $ctx->idTerlec);
        }

        return view('livewire.examenes.tercer-materia', [
            'filas' => $filas,
            'pdfUrl' => $pdfUrl,
            'totalFilas' => count($filas),
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
