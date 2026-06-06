<?php

namespace App\Http\Controllers\Navegacion;

use App\Http\Controllers\Controller;
use App\Models\Profesor;
use App\Support\ProfesorMenuPortal;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Activa la "Autogestión Docente" para usuarios del Menú de Secretaría
 * que también figuran como docentes con cursos asignados en `ppc`.
 *
 * Si existe un legajo paralelo (mismo DNI, mismo nivel) con rol Profesor/a,
 * se realiza un cambio de identidad de Auth a ese registro para que el Menú
 * de Docentes encuentre sus materias y permisos.
 *
 * @see docs/08-menus-de-navegacion.md
 */
class AutogestionDocenteController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $usuario = Auth::user();

        if (! $usuario instanceof Profesor
            || ! ProfesorMenuPortal::tieneAccesoAutogestion($usuario)) {
            return ProfesorMenuPortal::redirectInicio($usuario instanceof Profesor ? $usuario : null);
        }

        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        $target = ProfesorMenuPortal::perfilProfesorParaAutogestion($usuario);
        if (! $target instanceof Profesor) {
            return ProfesorMenuPortal::redirectInicio($usuario);
        }

        if ((int) $target->id !== (int) $usuario->id) {
            Auth::loginUsingId((int) $target->id);
            session()->regenerate();
            SchoolContext::set(
                idProfesor: (int) $target->id,
                idNivel: $idNivel,
                idTerlec: $idTerlec,
            );
            // Mantener la instancia singleton coherente para el resto del request actual.
            $ctx->idProfesor = (int) $target->id;
            $ctx->refreshProfesor();
        }

        ProfesorMenuPortal::activarAutogestionDocente();

        return redirect()->route('portalDocente.home');
    }
}
