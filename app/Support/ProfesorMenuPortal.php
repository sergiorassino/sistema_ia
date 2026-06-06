<?php

namespace App\Support;

use App\Models\Profesor;
use App\Models\ProfesorTipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Define qué menú lateral corresponde según `profesores.IdTipoProf` / `profesortipo`.
 *
 * Soporta además una "Autogestión Docente": cuando un usuario que entra al
 * Menú de Secretaría (p. ej. Preceptor) también figura como docente con
 * cursos asignados en `ppc`, puede cambiar manualmente al Menú de Docentes
 * sin cerrar sesión. La activación guarda un override en la sesión y, si
 * existe un legajo aparte con `IdTipoProf = 6` y PPC para el mismo DNI,
 * cambia la identidad de Auth a ese registro para que el Menú de Docentes
 * encuentre sus materias.
 *
 * @see docs/08-menus-de-navegacion.md
 */
final class ProfesorMenuPortal
{
    /** Rol «Profesor/a» en `profesortipo` → Menú de Docentes. */
    public const ID_TIPO_PROFESOR_AULA = 6;

    /** Clave de sesión que fuerza el Menú de Docentes para un usuario de secretaría. */
    public const SESSION_OVERRIDE_KEY = 'school.menu_portal_override';

    /** Valor del override que activa la Autogestión Docente. */
    public const OVERRIDE_DOCENTE = 'docente';

    public static function usaMenuDocentes(?Profesor $profesor): bool
    {
        if (! $profesor) {
            return false;
        }

        if (self::autogestionDocenteActiva()) {
            return true;
        }

        return (int) ($profesor->IdTipoProf ?? 0) === self::ID_TIPO_PROFESOR_AULA;
    }

    public static function usaMenuAdministracion(): bool
    {
        return schoolEsAdministracion();
    }

    /** Secretaría pedagógica (Inicial / Primario / Secundario), no Administración ni Docentes. */
    public static function usaMenuSecretariaPedagogica(?Profesor $profesor): bool
    {
        if (self::usaMenuDocentes($profesor)) {
            return false;
        }

        return ! self::usaMenuAdministracion();
    }

    /** Staff institucional: Menú de Secretaría pedagógica o Menú de Administración. */
    public static function usaMenuStaff(?Profesor $profesor): bool
    {
        return self::usaMenuAdministracion() || self::usaMenuSecretariaPedagogica($profesor);
    }

    /**
     * @deprecated Usar {@see usaMenuStaff()} o {@see usaMenuSecretariaPedagogica()} según el caso.
     */
    public static function usaMenuSecretaria(?Profesor $profesor): bool
    {
        return self::usaMenuStaff($profesor);
    }

    public static function layoutStaff(): string
    {
        return self::usaMenuAdministracion()
            ? 'layouts.administracion'
            : 'layouts.app';
    }

    /**
     * Rol «Secretario/a» en `profesortipo` (por nombre; el id varía por colegio).
     */
    public static function esSecretario(?Profesor $profesor = null): bool
    {
        $profesor ??= Auth::user();
        if (! $profesor instanceof Profesor) {
            return false;
        }

        $idTipo = (int) ($profesor->IdTipoProf ?? 0);
        if ($idTipo <= 0) {
            return false;
        }

        $tipo = ProfesorTipo::query()->whereKey($idTipo)->value('tipo');
        if ($tipo === null || trim((string) $tipo) === '') {
            return false;
        }

        return str_contains(mb_strtolower(trim((string) $tipo)), 'secret');
    }

    public static function rutaInicio(?Profesor $profesor = null): string
    {
        $profesor ??= Auth::user();

        if (self::usaMenuDocentes($profesor instanceof Profesor ? $profesor : null)) {
            return 'portalDocente.home';
        }

        return 'dashboard';
    }

    /**
     * Redirección HTTP estándar (middleware, controladores). En Livewire usar
     * `$this->redirectRoute(self::rutaInicio($profesor), navigate: false)`.
     */
    public static function redirectInicio(?Profesor $profesor = null): RedirectResponse
    {
        return redirect()->route(self::rutaInicio($profesor));
    }

    /** ¿La sesión actual está en modo "Autogestión Docente" desde un usuario de secretaría? */
    public static function autogestionDocenteActiva(): bool
    {
        return session(self::SESSION_OVERRIDE_KEY) === self::OVERRIDE_DOCENTE;
    }

    /** Activa el override de sesión para llevar al Menú de Docentes al usuario actual. */
    public static function activarAutogestionDocente(): void
    {
        session([self::SESSION_OVERRIDE_KEY => self::OVERRIDE_DOCENTE]);
    }

    /** Limpia el override (uso interno; el logout invalida la sesión completa). */
    public static function limpiarAutogestionDocente(): void
    {
        session()->forget(self::SESSION_OVERRIDE_KEY);
    }

    /**
     * ¿Corresponde mostrar la opción "Autogestión Docente" en el Menú de Secretaría?
     *
     * Se muestra cuando:
     *  - el usuario actualmente está en el Menú de Secretaría (no es Profesor/a),
     *  - existe algún registro en `ppc` para algún legajo con el mismo DNI en el
     *    nivel/ciclo activo (p. ej. el legajo paralelo con `IdTipoProf = 6`).
     */
    public static function tieneAccesoAutogestion(?Profesor $profesor): bool
    {
        if (! $profesor instanceof Profesor) {
            return false;
        }

        if (self::usaMenuDocentes($profesor)) {
            return false;
        }

        return self::perfilProfesorParaAutogestion($profesor) !== null;
    }

    /**
     * Devuelve el legajo en `profesores` (mismo DNI y mismo nivel del contexto activo)
     * que tiene al menos una asignación en `ppc` para el ciclo lectivo en curso.
     *
     * Prioriza el legajo «Profesor/a» (IdTipoProf = 6); si no hay, devuelve el
     * propio usuario en caso de tener PPC.
     */
    public static function perfilProfesorParaAutogestion(?Profesor $profesor): ?Profesor
    {
        if (! $profesor instanceof Profesor) {
            return null;
        }

        $dni = trim((string) ($profesor->dni ?? ''));
        if ($dni === '') {
            return null;
        }

        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);
        if ($idNivel < 1 || $idTerlec < 1) {
            return null;
        }

        $idsConPpc = DB::table('profesores as p')
            ->join('ppc', 'ppc.idProfesor', '=', 'p.id')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->where('p.dni', $dni)
            ->where(function ($w) use ($idNivel) {
                $w->where('p.nivel', $idNivel)
                    ->orWhereNull('p.nivel')
                    ->orWhere('p.nivel', 0);
            })
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->pluck('p.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($idsConPpc === []) {
            return null;
        }

        $candidatos = Profesor::query()
            ->whereIn('id', $idsConPpc)
            ->get();

        $aula = $candidatos->first(
            fn (Profesor $p) => (int) ($p->IdTipoProf ?? 0) === self::ID_TIPO_PROFESOR_AULA,
        );
        if ($aula instanceof Profesor) {
            return $aula;
        }

        $propio = $candidatos->firstWhere('id', (int) $profesor->id);
        if ($propio instanceof Profesor) {
            return $propio;
        }

        return $candidatos->first();
    }
}
