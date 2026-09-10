<?php

namespace App\Livewire\Administracion\Configuracion;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Models\Nivel;
use App\Models\Terlec;
use App\Support\Configuracion\PromoverAlumnosAnio;
use App\Support\NivelSistema;
use App\Support\PermisosConfiguracion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;
use RuntimeException;

class PromoverAlumnosIndex extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::PROMOVER_ALUMNOS_ANIO;
    }

    public $idTerlecOrigen = null;

    public $idTerlecDestino = null;

    /** @var list<int|string> */
    public array $idNiveles = [];

    /** @var list<int|string> */
    public array $idCursos = [];

    /**
     * @var array{
     *     matriculas: array{origen: int, a_crear: int, existentes: int, sin_curso: int, no_promovibles: int},
     *     calificaciones: array{a_crear: int},
     *     por_nivel: list<array{id: int, nombre: string, matriculas: int, calificaciones: int}>
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
        $this->sincronizarCursosSeleccionados();
    }

    public function quitarTodosNiveles(): void
    {
        $this->idNiveles = [];
        $this->idCursos = [];
        $this->informe = null;
    }

    public function seleccionarTodosCursos(): void
    {
        $this->idCursos = $this->idsCursosListados();
        $this->informe = null;
    }

    public function quitarTodosCursos(): void
    {
        $this->idCursos = [];
        $this->informe = null;
    }

    public function updatedIdTerlecOrigen(): void
    {
        $this->informe = null;
        $this->sincronizarCursosSeleccionados();
    }

    public function updatedIdTerlecDestino(): void
    {
        $this->informe = null;
        if ($this->idCursos === []) {
            $this->sincronizarCursosSeleccionados();
        }
    }

    public function updatedIdNiveles(): void
    {
        $this->informe = null;
        $this->sincronizarCursosSeleccionados();
    }

    public function updatedIdCursos(): void
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

        $key = 'config:promoverAlumnos:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('copia', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 120);

        $idsTerlec = Terlec::paraSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $idsNivel = $this->idsNivelesDisponibles();
        $idsCursosValidos = $this->idsCursosListados();

        $this->idNiveles = array_values(array_unique(array_map('intval', $this->idNiveles)));
        $this->idCursos = array_values(array_unique(array_map('intval', $this->idCursos)));

        $this->validate([
            'idTerlecOrigen' => ['required', 'integer', Rule::in($idsTerlec)],
            'idTerlecDestino' => ['required', 'integer', 'different:idTerlecOrigen', Rule::in($idsTerlec)],
            'idNiveles' => ['required', 'array', 'min:1'],
            'idNiveles.*' => ['integer', Rule::in($idsNivel)],
            'idCursos' => ['required', 'array', 'min:1'],
            'idCursos.*' => ['integer', Rule::in($idsCursosValidos)],
        ], [
            'idTerlecOrigen.required' => 'Seleccione el año de origen.',
            'idTerlecDestino.required' => 'Seleccione el año de destino.',
            'idTerlecDestino.different' => 'El año de destino debe ser distinto al de origen.',
            'idNiveles.required' => 'Seleccione al menos un nivel.',
            'idNiveles.min' => 'Seleccione al menos un nivel.',
            'idCursos.required' => 'Marque al menos un curso para promover.',
            'idCursos.min' => 'Marque al menos un curso para promover.',
        ]);

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        try {
            $this->informe = PromoverAlumnosAnio::ejecutar(
                (int) $this->idTerlecOrigen,
                (int) $this->idTerlecDestino,
                array_map('intval', $this->idNiveles),
                array_map('intval', $this->idCursos),
            );
        } catch (QueryException|RuntimeException $e) {
            report($e);
            $msg = $e instanceof RuntimeException && trim($e->getMessage()) !== ''
                ? $e->getMessage()
                : 'No se pudo completar la promoción. No se aplicaron cambios.';
            $this->addError('copia', $msg);
            $this->dispatch('se-swal-error', mensaje: $msg);

            return;
        }

        $creados = (int) ($this->informe['matriculas']['a_crear'] ?? 0);

        if ($creados === 0) {
            $this->dispatch(
                'se-swal-aviso',
                mensaje: 'No había alumnos nuevos para promover: ya tenían matrícula en el año de destino, o faltaba el curso/sección siguiente.',
            );

            return;
        }

        $calif = (int) ($this->informe['calificaciones']['a_crear'] ?? 0);
        $this->dispatch(
            'se-swal-exito',
            mensaje: 'Promoción finalizada. Se crearon '.$creados.' matrícula(s) y '.$calif.' registro(s) de calificaciones.',
        );
    }

    public function render()
    {
        $niveles = Nivel::query()
            ->where('id', '!=', NivelSistema::ADMINISTRACION)
            ->orderBy('id')
            ->get(['id', 'nivel', 'abrev']);

        $cursos = [];
        $nivelesSel = PromoverAlumnosAnio::nivelesValidos($this->idNiveles);
        $origenOk = (int) $this->idTerlecOrigen > 0;
        $destinoOk = (int) $this->idTerlecDestino > 0
            && (int) $this->idTerlecOrigen !== (int) $this->idTerlecDestino;

        if ($origenOk && $destinoOk && $nivelesSel !== []) {
            $cursos = PromoverAlumnosAnio::listarCursos(
                (int) $this->idTerlecOrigen,
                (int) $this->idTerlecDestino,
                $nivelesSel,
            );
        }

        $idsMarcados = array_map('intval', $this->idCursos);
        $preview = [
            'cursos' => count($cursos),
            'marcados' => 0,
            'regulares' => 0,
            'a_crear' => 0,
            'existentes' => 0,
            'sin_curso' => 0,
        ];
        foreach ($cursos as $fila) {
            if (! in_array((int) $fila['id'], $idsMarcados, true)) {
                continue;
            }
            $preview['marcados']++;
            $preview['regulares'] += (int) $fila['regulares'];
            $preview['a_crear'] += (int) $fila['a_crear'];
            $preview['existentes'] += (int) $fila['existentes'];
            if ($fila['sin_destino']) {
                $preview['sin_curso'] += (int) $fila['regulares'];
            }
        }

        return view('livewire.administracion.configuracion.promover-alumnos-index', [
            'terlecs' => Terlec::paraSelector(),
            'niveles' => $niveles,
            'cursos' => $cursos,
            'preview' => $preview,
            'origenAno' => $this->anoTerlec((int) ($this->idTerlecOrigen ?? 0)),
            'destinoAno' => $this->anoTerlec((int) ($this->idTerlecDestino ?? 0)),
            'ultimoCursoSecundario' => PromoverAlumnosAnio::ultimoCursoSecundario(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Promover a todos los alumnos']);
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

    /** @return list<int> */
    private function idsCursosListados(): array
    {
        $nivelesSel = PromoverAlumnosAnio::nivelesValidos($this->idNiveles);
        if ((int) $this->idTerlecOrigen <= 0 || $nivelesSel === []) {
            return [];
        }

        $cursos = PromoverAlumnosAnio::listarCursos(
            (int) $this->idTerlecOrigen,
            (int) ($this->idTerlecDestino ?? 0),
            $nivelesSel,
        );

        return array_values(array_map(fn (array $fila): int => (int) $fila['id'], $cursos));
    }

    private function sincronizarCursosSeleccionados(): void
    {
        $this->idCursos = $this->idsCursosListados();
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
