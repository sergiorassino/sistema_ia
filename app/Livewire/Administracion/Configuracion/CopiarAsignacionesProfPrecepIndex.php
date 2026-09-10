<?php

namespace App\Livewire\Administracion\Configuracion;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Models\Nivel;
use App\Models\Terlec;
use App\Support\Configuracion\CopiarAsignacionesProfPrecepAnio;
use App\Support\NivelSistema;
use App\Support\PermisosConfiguracion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;
use RuntimeException;

class CopiarAsignacionesProfPrecepIndex extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::COPIAR_ASIGNACIONES_PROF_PRECEP;
    }

    public $idTerlecOrigen = null;

    public $idTerlecDestino = null;

    /** @var list<int|string> */
    public array $idNiveles = [];

    /**
     * @var array{
     *     ppc: array{origen: int, existentes: int, a_crear: int, sin_materia: int},
     *     preceptores: array{origen: int, existentes: int, a_crear: int, sin_curso: int},
     *     por_nivel: list<array{id: int, nombre: string, ppc: int, preceptores: int}>
     * }|null
     */
    public ?array $informe = null;

    public function mount(): void
    {
        $this->idTerlecOrigen = (int) (schoolCtx()->idTerlec ?? 0) ?: null;
        $this->idNiveles = $this->idsNivelesDisponibles();
    }

    public function seleccionarTodosNiveles(): void
    {
        $this->idNiveles = $this->idsNivelesDisponibles();
        $this->informe = null;
    }

    public function quitarTodosNiveles(): void
    {
        $this->idNiveles = [];
        $this->informe = null;
    }

    public function updatedIdTerlecOrigen(): void
    {
        $this->informe = null;
    }

    public function updatedIdTerlecDestino(): void
    {
        $this->informe = null;
    }

    public function updatedIdNiveles(): void
    {
        $this->informe = null;
    }

    public function cerrarInforme(): void
    {
        $this->informe = null;
    }

    public function ejecutar(): void
    {
        $this->informe = null;

        $key = 'config:copiarAsignacionesProfPrecep:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('copia', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 120);

        $idsTerlec = Terlec::paraSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $idsNivel = $this->idsNivelesDisponibles();

        $this->idNiveles = array_values(array_unique(array_map('intval', $this->idNiveles)));

        $this->validate([
            'idTerlecOrigen' => ['required', 'integer', Rule::in($idsTerlec)],
            'idTerlecDestino' => ['required', 'integer', 'different:idTerlecOrigen', Rule::in($idsTerlec)],
            'idNiveles' => ['required', 'array', 'min:1'],
            'idNiveles.*' => ['integer', Rule::in($idsNivel)],
        ], [
            'idTerlecOrigen.required' => 'Seleccione el año de origen.',
            'idTerlecDestino.required' => 'Seleccione el año de destino.',
            'idTerlecDestino.different' => 'El año de destino debe ser distinto al de origen.',
            'idNiveles.required' => 'Seleccione al menos un nivel.',
            'idNiveles.min' => 'Seleccione al menos un nivel.',
        ]);

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        try {
            $this->informe = CopiarAsignacionesProfPrecepAnio::ejecutar(
                (int) $this->idTerlecOrigen,
                (int) $this->idTerlecDestino,
                array_map('intval', $this->idNiveles),
            );
        } catch (QueryException|RuntimeException $e) {
            report($e);
            $msg = $e instanceof RuntimeException && trim($e->getMessage()) !== ''
                ? $e->getMessage()
                : 'No se pudo completar la copia. No se aplicaron cambios.';
            $this->addError('copia', $msg);
            $this->dispatch('se-swal-error', mensaje: $msg);

            return;
        }

        $creados = (int) $this->informe['ppc']['a_crear']
            + (int) $this->informe['preceptores']['a_crear'];

        if ($creados === 0) {
            $this->dispatch(
                'se-swal-aviso',
                mensaje: 'No había asignaciones nuevas para copiar: profesores y preceptores ya existían en el año de destino, o faltaban materias/cursos de destino.',
            );

            return;
        }

        $this->dispatch(
            'se-swal-exito',
            mensaje: 'Copia finalizada. Se crearon '.$creados.' asignación(es) en el año de destino.',
        );
    }

    public function render()
    {
        $niveles = Nivel::query()
            ->where('id', '!=', NivelSistema::ADMINISTRACION)
            ->orderBy('id')
            ->get(['id', 'nivel', 'abrev']);

        $preview = null;
        $nivelesSel = CopiarAsignacionesProfPrecepAnio::nivelesValidos($this->idNiveles);
        if ((int) $this->idTerlecOrigen > 0
            && (int) $this->idTerlecDestino > 0
            && (int) $this->idTerlecOrigen !== (int) $this->idTerlecDestino
            && $nivelesSel !== []) {
            $preview = CopiarAsignacionesProfPrecepAnio::previsualizar(
                (int) $this->idTerlecOrigen,
                (int) $this->idTerlecDestino,
                $nivelesSel,
            );
        }

        $origenAno = $this->anoTerlec((int) ($this->idTerlecOrigen ?? 0));
        $destinoAno = $this->anoTerlec((int) ($this->idTerlecDestino ?? 0));

        return view('livewire.administracion.configuracion.copiar-asignaciones-prof-precep-index', [
            'terlecs' => Terlec::paraSelector(),
            'niveles' => $niveles,
            'preview' => $preview,
            'origenAno' => $origenAno,
            'destinoAno' => $destinoAno,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Copiar asignación de profesores y preceptores']);
    }

    /** @return list<int> */
    private function idsNivelesDisponibles(): array
    {
        return Nivel::query()
            ->where('id', '!=', NivelSistema::ADMINISTRACION)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function anoTerlec(int $id): ?int
    {
        if ($id <= 0) {
            return null;
        }

        $ano = Terlec::query()->whereKey($id)->value('ano');

        return $ano !== null ? (int) $ano : null;
    }
}
