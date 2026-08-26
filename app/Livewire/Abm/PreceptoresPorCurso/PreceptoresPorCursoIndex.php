<?php

namespace App\Livewire\Abm\PreceptoresPorCurso;

use App\Models\Curso;
use App\Support\PermisosIaCatalog;
use App\Support\PreceptoresPorCurso;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Asignación de preceptor(es) a cada curso del ciclo y nivel de sesión.
 * Persistencia: tabla legacy `preceptoresporcurso`; el preceptor es un registro de `profesores`.
 */
class PreceptoresPorCursoIndex extends Component
{
    public string $search = '';

    /** @var array<string, int|string|null> idCurso => idProfesor a asignar */
    public array $nuevoPreceptorId = [];

    public function mount(): void
    {
        abort_unless(
            tienePermiso(PermisosIaCatalog::PRECEPTORES_POR_CURSO),
            403,
            'Sin permiso para asignar preceptores por curso.'
        );
    }

    public function asignar(int $idCurso): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::PRECEPTORES_POR_CURSO), 403);

        $key = 'preceptores-curso-assign:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $idProfesor = (int) ($this->nuevoPreceptorId[(string) $idCurso] ?? $this->nuevoPreceptorId[$idCurso] ?? 0);
        if ($idProfesor < 1) {
            $this->addError('nuevoPreceptorId.'.$idCurso, 'Seleccione un preceptor.');

            return;
        }

        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        if (! $this->cursoEnContexto($idCurso, $idNivel, $idTerlec)) {
            abort(404);
        }

        if (! PreceptoresPorCurso::profesorEsPreceptorElegible($idProfesor, $idNivel)) {
            $this->dispatch('se-swal-error', mensaje: 'El personal seleccionado no es un preceptor del nivel.');

            return;
        }

        $resultado = PreceptoresPorCurso::asignar($idCurso, $idProfesor, $idNivel, $idTerlec);
        if (! $resultado['ok']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['mensaje']);

            return;
        }

        $this->nuevoPreceptorId[(string) $idCurso] = '';
        $this->resetValidation();
        $this->dispatch('se-swal-exito', mensaje: $resultado['mensaje']);
    }

    public function quitar(int $idCurso, int $idProfesor): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::PRECEPTORES_POR_CURSO), 403);

        $key = 'preceptores-curso-quitar:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        if (! $this->cursoEnContexto($idCurso, $idNivel, $idTerlec)) {
            abort(404);
        }

        $resultado = PreceptoresPorCurso::quitar($idCurso, $idProfesor, $idNivel, $idTerlec);
        if (! $resultado['ok']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['mensaje']);

            return;
        }

        $this->dispatch('se-swal-exito', mensaje: $resultado['mensaje']);
    }

    public function render()
    {
        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        $mensajeTabla = PreceptoresPorCurso::mensajeSiTablaNoDisponible();
        $tablaOk = $mensajeTabla === null;

        $cursosTodos = Curso::query()
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->with(['turnoClase', 'curplan'])
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get();

        $idsTodos = $cursosTodos->pluck('Id')->map(fn ($id) => (int) $id)->all();
        $asignacionesTodas = $tablaOk
            ? PreceptoresPorCurso::asignacionesPorCurso($idsTodos, $idNivel, $idTerlec)
            : [];

        $cursosConAsignacion = 0;
        foreach ($asignacionesTodas as $lista) {
            if ($lista !== []) {
                $cursosConAsignacion++;
            }
        }

        $cursos = $cursosTodos;
        $search = trim($this->search);
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $cursos = $cursos->filter(function (Curso $c) use ($needle) {
                $hay = mb_strtolower($c->nombreParaListado());
                $extra = mb_strtolower(trim((string) ($c->c ?? '').' '.(string) ($c->s ?? '').' '.(string) ($c->cursec ?? '')));

                return str_contains($hay, $needle) || str_contains($extra, $needle);
            })->values();
        }

        $idsCursos = $cursos->pluck('Id')->map(fn ($id) => (int) $id)->all();
        $asignaciones = [];
        foreach ($idsCursos as $idCurso) {
            if (isset($asignacionesTodas[$idCurso])) {
                $asignaciones[$idCurso] = $asignacionesTodas[$idCurso];
            }
        }

        $preceptores = PreceptoresPorCurso::preceptoresElegibles($idNivel);

        return view('livewire.abm.preceptores-por-curso.index', [
            'cursos' => $cursos,
            'asignaciones' => $asignaciones,
            'preceptores' => $preceptores,
            'tablaOk' => $tablaOk,
            'mensajeTabla' => $mensajeTabla,
            'cursosConAsignacion' => $cursosConAsignacion,
            'totalCursos' => $cursosTodos->count(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Preceptores por curso']);
    }

    private function cursoEnContexto(int $idCurso, int $idNivel, int $idTerlec): bool
    {
        if ($idCurso < 1 || $idNivel < 1 || $idTerlec < 1) {
            return false;
        }

        return Curso::query()
            ->where('Id', $idCurso)
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->exists();
    }
}
