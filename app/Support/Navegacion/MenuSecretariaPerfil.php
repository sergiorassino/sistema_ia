<?php

namespace App\Support\Navegacion;

use App\Models\Profesor;
use App\Support\Mora\PermisosMora;
use App\Support\NivelSistema;
use App\Support\PermisosCuotas;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Facades\Auth;

/**
 * Perfil de menú del portal de Secretaría según `school.idNivel` (identidad de login).
 *
 * @see docs/08-menus-de-navegacion.md
 */
final class MenuSecretariaPerfil
{
    public static function esAdministracion(): bool
    {
        return NivelSistema::esAdministracion((int) (schoolCtx()->idNivel ?? 0));
    }

    /** Oculta bloques pedagógicos (calificaciones, exámenes, etc.) en sesión Administración. */
    public static function ocultarGruposPedagogicos(): bool
    {
        return self::esAdministracion();
    }

    /** Grupo sidebar «CALIFICACIONES (Inicial)» — flujo estándar (Montecristo). */
    public static function muestraCalificacionesInicial(): bool
    {
        return ! self::ocultarGruposPedagogicos()
            && NivelSistema::esInicial((int) (schoolCtx()->idNivel ?? 0))
            && (bool) config('tenant.secretaria.calificaciones_inicial.habilitado', true)
            && ! self::muestraCalificacionesInicialSfq();
    }

    /** Grupo sidebar «CALIFICACIONES (Inicial)» — variante SFQ (carga ic01–ic06). */
    public static function muestraCalificacionesInicialSfq(): bool
    {
        return ! self::ocultarGruposPedagogicos()
            && NivelSistema::esInicial((int) (schoolCtx()->idNivel ?? 0))
            && (bool) config('tenant.secretaria.calificaciones_inicial_sfq.habilitado', false)
            && \App\Support\CalificacionesInicial\CalificacionesInicialModulos::moduloActivo(
                \App\Support\CalificacionesInicial\CalificacionesInicialModulos::CARGA_NOTAS,
            );
    }

    /** Grupo sidebar «CALIFICACIONES (Primario)» — solo sesión en `niveles.id` = 2. */
    public static function muestraCalificacionesPrimario(): bool
    {
        return ! self::ocultarGruposPedagogicos()
            && NivelSistema::esPrimario((int) (schoolCtx()->idNivel ?? 0));
    }

    /**
     * Grupo sidebar «CALIFICACIONES (Secundario)» — módulos actuales (`calificacionesSecundario.*`).
     * Solo sesión en `niveles.id` = 3.
     */
    public static function muestraCalificacionesSecundario(): bool
    {
        return ! self::ocultarGruposPedagogicos()
            && NivelSistema::esSecundario((int) (schoolCtx()->idNivel ?? 0));
    }

    /** Gestión de planes y cursos modelo: solo Menú de Secretaría. */
    public static function muestraPlanesCursosModelo(): bool
    {
        return ! self::esAdministracion();
    }

    /** Grupo sidebar «ESTADÍSTICAS» — solo sesión en `niveles.id` = 3 (secundario). */
    public static function muestraEstadisticas(): bool
    {
        return self::muestraCalificacionesSecundario();
    }

    /** Gestión de cursos y materias del año: solo Menú de Secretaría. */
    public static function muestraCursosMateriasAnio(): bool
    {
        return ! self::esAdministracion();
    }

    public static function muestraGrupoEstudiantes(): bool
    {
        return true;
    }

    /** Bloque «Gestión de aranceles» (Administración): al menos un ítem del grupo. */
    public static function muestraGestionCuotas(): bool
    {
        return self::esAdministracion() && PermisosCuotas::muestraGrupoGestionAranceles();
    }

    /** Bloque «Gestión masiva» (Administración). */
    public static function muestraGestionMasivaCuotas(): bool
    {
        return self::esAdministracion() && PermisosCuotas::muestraGrupoGestionMasiva();
    }

    /** Bloque «Resúmenes» (Administración). */
    public static function muestraResumenes(): bool
    {
        return self::esAdministracion() && PermisosCuotas::muestraGrupoResumenes();
    }

    /** Bloque «Becas» (Administración). */
    public static function muestraBecas(): bool
    {
        return self::esAdministracion() && PermisosCuotas::muestraGrupoBecas();
    }

    /** Bloque «Gestión de mora» (Administración). */
    public static function muestraGestionMora(): bool
    {
        return self::esAdministracion() && PermisosMora::muestraGrupoGestionMora();
    }

    /**
     * Excel de viajes / salidas educativas: solo Menú de Secretaría en niveles pedagógicos (1–4).
     * No Administración, no Menú de Docentes ni de Alumnos (rutas bajo menu.portal:secretaria).
     */
    public static function muestraViajesSalidasEducativas(): bool
    {
        if (self::esAdministracion()) {
            return false;
        }

        $profesor = Auth::user();

        return ProfesorMenuPortal::usaMenuSecretariaPedagogica($profesor instanceof Profesor ? $profesor : null);
    }

    /** Calendario escolar y proyectos extracurriculares: Menú de Secretaría, niveles 1–4. */
    public static function muestraProyectosExtracurriculares(): bool
    {
        return self::muestraViajesSalidasEducativas();
    }

    public static function abortSiNoViajesSalidasEducativas(): void
    {
        abort_unless(
            self::muestraViajesSalidasEducativas(),
            403,
            'Viajes y salidas educativas solo están disponibles en el Menú de Secretaría (niveles pedagógicos).',
        );
    }
}
