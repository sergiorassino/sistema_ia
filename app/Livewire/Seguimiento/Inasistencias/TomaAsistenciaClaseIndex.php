<?php

namespace App\Livewire\Seguimiento\Inasistencias;

use App\Support\PermisosIaCatalog;
use App\Support\Seguimiento\TomaAsistenciaClase;
use App\Support\Tea\TeaInstanciasPendientes;
use Carbon\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class TomaAsistenciaClaseIndex extends Component
{
    public string $fecha = '';

    public int|string $idCurso = '';

    /**
     * idMatricula => ['clase' => idTipo|string, 'edfis' => idTipo|string, 'just_clase' => J|I, 'just_edfis' => J|I]
     *
     * @var array<int, array{clase: string, edfis: string, just_clase: string, just_edfis: string}>
     */
    public array $asistencia = [];

    public bool $grillaCargada = false;

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::TOMA_ASISTENCIA_CLASE), 403, 'Sin permiso para toma de asistencia a clase.');

        $this->fecha = now()->format('Y-m-d');
    }

    public function updatedIdCurso(mixed $value): void
    {
        $this->idCurso = is_scalar($value) ? (string) $value : '';
        $this->reiniciarGrilla();
    }

    public function updatedFecha(mixed $value): void
    {
        $this->fecha = is_scalar($value) ? trim((string) $value) : '';
        $this->reiniciarGrilla();
    }

    public function cargarGrilla(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::TOMA_ASISTENCIA_CLASE), 403);

        $this->validate([
            'fecha' => ['required', 'date'],
            'idCurso' => ['required', 'integer', 'min:1'],
        ], [
            'fecha.required' => 'Indique la fecha.',
            'fecha.date' => 'Fecha inválida.',
            'idCurso.required' => 'Seleccione el curso.',
        ]);

        $idCurso = (int) $this->idCurso;

        abort_if(TomaAsistenciaClase::cursoDelContexto($idCurso) === null, 404);

        $alumnos = TomaAsistenciaClase::alumnosDelCurso($idCurso);
        $this->asistencia = TomaAsistenciaClase::estadoAsistenciaDesdeBd($alumnos, $this->fecha);
        $this->grillaCargada = true;
    }

    public function updatedAsistencia(mixed $value, string $key): void
    {
        if (! $this->grillaCargada || ! str_contains($key, '.')) {
            return;
        }

        [$idMatriculaRaw, $clave] = explode('.', $key, 2);
        $idMatricula = (int) $idMatriculaRaw;
        $campo = TomaAsistenciaClase::campoDesdeClaveAsistencia($clave);
        if ($idMatricula < 1 || $campo === null) {
            return;
        }

        $justKey = TomaAsistenciaClase::claveJustDelCampo($campo);
        if ($justKey !== null && isset($this->asistencia[$idMatricula])) {
            $tipo = trim((string) ($this->asistencia[$idMatricula][$campo] ?? ''));
            if ($tipo === '') {
                $this->asistencia[$idMatricula][$justKey] = 'I';
            } else {
                $this->asistencia[$idMatricula][$justKey] = TomaAsistenciaClase::normalizarJust(
                    $this->asistencia[$idMatricula][$justKey] ?? 'I'
                );
            }
        }

        // Solo justif. sin tipo de inasistencia: no hay registro que actualizar.
        if (in_array($clave, [TomaAsistenciaClase::JUST_CLASE, TomaAsistenciaClase::JUST_ED_FIS], true)
            && trim((string) ($this->asistencia[$idMatricula][$campo] ?? '')) === '') {
            return;
        }

        $this->guardarCelda($idMatricula, $campo);
    }

    public function guardarCelda(int $idMatricula, string $campo): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::TOMA_ASISTENCIA_CLASE), 403);

        if (! $this->grillaCargada || ! $this->filtrosCompletos()) {
            return;
        }

        if (! in_array($campo, [TomaAsistenciaClase::CAMPO_CLASE, TomaAsistenciaClase::CAMPO_ED_FIS], true)) {
            return;
        }

        $key = 'toma-asistencia:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 120)) {
            $this->addError('fecha', 'Demasiados cambios seguidos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $idCurso = (int) $this->idCurso;
        $valor = trim((string) ($this->asistencia[$idMatricula][$campo] ?? ''));
        $justKey = TomaAsistenciaClase::claveJustDelCampo($campo);
        $just = $justKey !== null
            ? TomaAsistenciaClase::normalizarJust($this->asistencia[$idMatricula][$justKey] ?? 'I')
            : 'I';

        if ($justKey !== null && isset($this->asistencia[$idMatricula])) {
            $this->asistencia[$idMatricula][$justKey] = $valor === '' ? 'I' : $just;
        }

        try {
            TomaAsistenciaClase::sincronizarCelda(
                $idMatricula,
                $idCurso,
                $this->fecha,
                $campo,
                $valor,
                $just,
            );
        } catch (\Throwable $e) {
            $this->recargarFilaDesdeBd($idMatricula);
            $this->addError('asistencia.'.$idMatricula.'.'.$campo, $e->getMessage() ?: 'No se pudo guardar.');

            return;
        }

        $this->resetErrorBag('asistencia.'.$idMatricula.'.'.$campo);
        if ($justKey !== null) {
            $this->resetErrorBag('asistencia.'.$idMatricula.'.'.$justKey);
        }
    }

    /** @return array{presentes_clase: int, presentes_ed_fis: int, presentes_contraturno: int, ausentes: int, llegadas_tarde: int, retiros: int, contraturno: int, educacion_fisica: int} */
    public function getResumenTotalesProperty(): array
    {
        return TomaAsistenciaClase::contarResumen($this->asistencia);
    }

    public function getTotalAlumnosProperty(): int
    {
        return count($this->asistencia);
    }

    private function filtrosCompletos(): bool
    {
        return (int) $this->idCurso > 0 && $this->fechaValida();
    }

    private function fechaValida(): bool
    {
        if ($this->fecha === '') {
            return false;
        }

        try {
            Carbon::createFromFormat('Y-m-d', $this->fecha)->startOfDay();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function reiniciarGrilla(): void
    {
        $this->asistencia = [];
        $this->grillaCargada = false;

        if ($this->filtrosCompletos()) {
            $this->cargarGrilla();
        }
    }

    private function recargarFilaDesdeBd(int $idMatricula): void
    {
        if (! isset($this->asistencia[$idMatricula])) {
            return;
        }

        $parcial = TomaAsistenciaClase::estadoAsistenciaDesdeBd(
            collect([(object) ['id' => $idMatricula]]),
            $this->fecha,
        );

        if (isset($parcial[$idMatricula])) {
            $this->asistencia[$idMatricula] = $parcial[$idMatricula];
        }
    }

    public function render()
    {
        $idCurso = (int) $this->idCurso;
        $cursos = TomaAsistenciaClase::cursosDelContexto();
        $curso = $idCurso > 0 ? TomaAsistenciaClase::cursoDelContexto($idCurso) : null;

        $alumnos = $this->grillaCargada && $idCurso > 0
            ? TomaAsistenciaClase::alumnosDelCurso($idCurso)
            : collect();

        $teaPendientesPorMatricula = [];
        if ($alumnos->isNotEmpty()) {
            $teaPendientesPorMatricula = TeaInstanciasPendientes::porMatriculas(
                $alumnos->pluck('id')->map(static fn ($id) => (int) $id)->all(),
            );
        }

        return view('livewire.seguimiento.inasistencias.toma-asistencia-clase', [
            'cursos' => $cursos,
            'curso' => $curso,
            'alumnos' => $alumnos,
            'tiposClase' => TomaAsistenciaClase::tiposClase(),
            'tiposEdFis' => TomaAsistenciaClase::tiposEducacionFisica(),
            'puedeCargarGrilla' => $this->filtrosCompletos(),
            'resumen' => $this->resumenTotales,
            'teaPendientesPorMatricula' => $teaPendientesPorMatricula,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Toma de asistencia a clase']);
    }
}
