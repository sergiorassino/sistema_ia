<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\AsignacionBecasConsulta;
use App\Support\Cuotas\AsignacionBecasService;
use App\Support\Cuotas\GeneracionMasivaCuotasConsulta;
use App\Support\Cuotas\GestionAranceles;
use App\Support\PermisosCuotas;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Asignación de becas en matricula.idCuotasbecas — perfil Administración.
 */
class AsignacionBecasIndex extends Component
{
    public int $idCurso = 0;

    public string $searchAlumno = '';

    /** @var list<array<string, mixed>> */
    public array $filas = [];

    public bool $cargado = false;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeAsignacionBecas(), 403, 'Sin permiso para asignación de becas.');
    }

    public function updatedSearchAlumno(mixed $value): void
    {
        if (trim((string) $value) !== '') {
            $this->idCurso = 0;
        }
    }

    public function updatedIdCurso(mixed $value): void
    {
        if ((int) $value > 0) {
            $this->searchAlumno = '';
        }
    }

    public function actualizarBeca(int $idMatricula, mixed $idBecaRaw): void
    {
        abort_unless(PermisosCuotas::puedeAsignacionBecas(), 403);

        $idx = $this->indiceFilaPorMatricula($idMatricula);
        if ($idx === null) {
            return;
        }

        $idBeca = (int) $idBecaRaw;
        $idOriginal = (int) ($this->filas[$idx]['idCuotasbecasOriginal'] ?? 0);

        if ($idBeca === $idOriginal) {
            return;
        }

        $this->filas[$idx]['idCuotasbecas'] = $idBeca;

        $rateKey = 'cuotas:asignacion-becas:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 30)) {
            $this->filas[$idx]['idCuotasbecas'] = $idOriginal;
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 60);

        $resultado = AsignacionBecasService::guardar([$idMatricula => $idBeca]);

        if (($resultado['actualizados'] ?? 0) > 0) {
            $this->filas[$idx]['idCuotasbecasOriginal'] = $idBeca;
        } else {
            $this->filas[$idx]['idCuotasbecas'] = $idOriginal;
            $this->dispatch('se-swal-error', mensaje: 'No se pudo guardar la beca.');
        }
    }

    public function cargarAlumnos(): void
    {
        $cursoIds = $this->idsCursosValidados();
        $termino = trim($this->searchAlumno);

        if ($cursoIds === [] && $termino === '') {
            $this->addError('idCurso', 'Seleccione sala / grado / curso o busque por estudiante.');
            $this->addError('searchAlumno', 'Seleccione sala / grado / curso o busque por estudiante.');

            return;
        }

        $this->resetErrorBag();

        $matriculas = AsignacionBecasConsulta::matriculasParaAsignacion($cursoIds, $termino);

        $this->filas = $matriculas
            ->map(fn (array $fila) => array_merge($fila, [
                'idCuotasbecasOriginal' => $fila['idCuotasbecas'],
            ]))
            ->values()
            ->all();

        $this->cargado = true;

        if ($this->filas === []) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontraron matrículas con los criterios indicados.');
        }
    }

    private function indiceFilaPorMatricula(int $idMatricula): ?int
    {
        foreach ($this->filas as $idx => $fila) {
            if ((int) ($fila['idMatricula'] ?? 0) === $idMatricula) {
                return $idx;
            }
        }

        return null;
    }

    /** @return list<int> */
    private function idsCursosValidados(): array
    {
        $idCurso = (int) $this->idCurso;
        if ($idCurso < 1) {
            return [];
        }

        return AsignacionBecasConsulta::validarIdsCursos([$idCurso]);
    }

    /**
     * @return list<array{id: int, etiqueta: string}>
     */
    private function opcionesCurso(): array
    {
        $opciones = [];

        foreach (GeneracionMasivaCuotasConsulta::cursosEnContexto() as $curso) {
            $nivel = trim((string) ($curso->nivel?->nivel ?? ''));
            $nombre = $curso->nombreParaListado();
            $etiqueta = $nivel !== '' ? "{$nivel} — {$nombre}" : $nombre;

            $opciones[] = [
                'id' => (int) $curso->Id,
                'etiqueta' => $etiqueta,
            ];
        }

        return $opciones;
    }

    public function render()
    {
        $ano = (int) schoolCtx()->terlecAno();

        return view('livewire.cuotas.asignacion-becas', [
            'opcionesCurso' => $this->opcionesCurso(),
            'becas' => GestionAranceles::becasParaSelector(),
            'ano' => $ano,
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Asignación de Becas — {$ano}"]);
    }
}
