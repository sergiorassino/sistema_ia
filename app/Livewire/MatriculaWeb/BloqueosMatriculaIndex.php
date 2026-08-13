<?php

namespace App\Livewire\MatriculaWeb;

use App\Livewire\Concerns\RequiresPermisoMatriculaWeb;
use App\Support\MatriculaWeb\BloqueosMatriculaConsulta;
use App\Support\MatriculaWeb\BloqueosMatriculaService;
use App\Support\PermisosMatriculaWeb;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

class BloqueosMatriculaIndex extends Component
{
    use RequiresPermisoMatriculaWeb;
    use WithPagination;

    /** 0 = todos los cursos (alfabético); >0 = filtrar por curso. */
    public int $idCurso = 0;

    /** @var array<string, array{except?: mixed, as?: string}> */
    protected $queryString = [
        'idCurso' => ['except' => 0, 'as' => 'curso'],
    ];

    protected function permisoMatriculaWebOrden(): int
    {
        return PermisosMatriculaWeb::BLOQUEOS_MATRICULA;
    }

    public function mount(): void
    {
        $ctx = schoolCtx();
        if ($ctx->idNivel < 1 || $ctx->idTerlec < 1) {
            abort(403, 'Seleccione nivel y ciclo lectivo en el contexto activo.');
        }

        $this->idCurso = BloqueosMatriculaConsulta::opcionesCurso()
            ->pluck('id')
            ->contains($this->idCurso)
            ? $this->idCurso
            : 0;
    }

    public function updatedIdCurso(): void
    {
        $this->resetPage();
    }

    public function alternarBloqueo(int $idMatricula, string $campo): void
    {
        abort_unless(PermisosMatriculaWeb::tiene(PermisosMatriculaWeb::BLOQUEOS_MATRICULA), 403);

        $rateKey = 'matricula-web:bloqueos:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 60)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados cambios seguidos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 60);

        $resultado = BloqueosMatriculaService::alternar($idMatricula, $campo);

        if (! $resultado['exito']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['mensaje']);

            return;
        }
    }

    public function aplicarBloqueoMasivo(string $campo, bool $bloquear): void
    {
        abort_unless(PermisosMatriculaWeb::tiene(PermisosMatriculaWeb::BLOQUEOS_MATRICULA), 403);

        $rateKey = 'matricula-web:bloqueos-masivo:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados cambios masivos seguidos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 60);

        $resultado = BloqueosMatriculaService::aplicarMasivo($this->idCurso, $campo, $bloquear);

        if (! $resultado['exito']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['mensaje']);

            return;
        }

        $this->dispatch('se-swal-exito', mensaje: $resultado['mensaje']);
    }

    public function render()
    {
        $ctx = schoolCtx();
        $opcionesCurso = BloqueosMatriculaConsulta::opcionesCurso();
        $alumnos = BloqueosMatriculaConsulta::paginar($this->idCurso);

        return view('livewire.matricula-web.bloqueos-matricula-index', [
            'opcionesCurso' => $opcionesCurso,
            'alumnos' => $alumnos,
            'totalAlumnos' => $alumnos->total(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Bloqueos de matrícula']);
    }
}
