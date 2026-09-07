<?php

namespace App\Livewire\Seguimiento\Gabinete;

use App\Livewire\Seguimiento\Gabinete\Concerns\RequiresPermisoSeguimientoGabinete;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\Seguimiento\GabineteOrientacion;
use App\Support\Seguimiento\GabineteSemaforo;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

class GabineteIndex extends Component
{
    use RequiresPermisoSeguimientoGabinete;
    use WithPagination;

    public const POR_PAGINA = 50;

    /** 0 = todos los cursos. */
    public int|string $idCurso = 0;

    /** curso = agrupar por curso; alfa = orden alfabético del colegio. */
    public string $vista = 'curso';

    /** '' | verde | amarillo | rojo | sin */
    public string $filtroColor = '';

    public string $busqueda = '';

    public function mount(): void
    {
        $ctx = ContextoEstudianteSesion::leer(ContextoEstudianteSesion::SEGUIMIENTO_GABINETE);
        if ((int) ($ctx['curso'] ?? 0) > 0) {
            $this->idCurso = (int) $ctx['curso'];
        }
    }

    public function updatedIdCurso(): void
    {
        $this->idCurso = (int) $this->idCurso;
        $this->resetPage();
    }

    public function updatedVista(mixed $value): void
    {
        $this->vista = is_scalar($value) && (string) $value === 'alfa' ? 'alfa' : 'curso';
        $this->resetPage();
    }

    public function updatedFiltroColor(mixed $value): void
    {
        $this->filtroColor = GabineteSemaforo::normalizarFiltro($value);
        $this->resetPage();
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function marcarColor(int $idMatricula, int $color): void
    {
        $key = 'gabinete:color:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados cambios. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        try {
            $m = GabineteOrientacion::matriculaEnContexto($idMatricula);
        } catch (\Throwable) {
            $this->dispatch('se-swal-error', mensaje: 'Alumno no encontrado en el contexto actual.');

            return;
        }

        $actual = GabineteOrientacion::colorDelLegajo((int) $m->idLegajos);
        $nuevo = GabineteSemaforo::normalizar($color);
        if ($nuevo === $actual) {
            $nuevo = GabineteSemaforo::NINGUNO;
        }

        $resultado = GabineteOrientacion::guardarColor((int) $m->idLegajos, $nuevo);
        if (! $resultado['ok']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['mensaje']);

            return;
        }
    }

    public function render()
    {
        $tablasDisponibles = GabineteOrientacion::tablasDisponibles();
        $marcaDisponible = GabineteOrientacion::tablaMarcaDisponible();
        $cursos = GabineteOrientacion::cursosDelContexto();
        $this->vista = $this->vista === 'alfa' ? 'alfa' : 'curso';
        $this->filtroColor = GabineteSemaforo::normalizarFiltro($this->filtroColor);
        $this->idCurso = (int) $this->idCurso;

        $alumnos = GabineteOrientacion::paginarAlumnos(
            $this->idCurso,
            $this->vista,
            $this->filtroColor,
            $this->busqueda,
            self::POR_PAGINA
        );

        return view('livewire.seguimiento.gabinete.index', compact(
            'tablasDisponibles',
            'marcaDisponible',
            'cursos',
            'alumnos',
        ))->layout(layoutMenuStaff(), ['pageTitle' => 'Seguimiento de gabinete de orientación']);
    }
}
