<?php

namespace App\Support;

/**
 * Permisos granulares del menú Configuración (secretaría).
 *
 * Cada ítem del menú Configuración usa su orden 25–36 (salvo orden 32,
 * «Notificaciones Push», que está en Comunicación institucional). Los órdenes
 * 14 y 99 son consulta (Permisos por Usuario / Permisos por Tarea).
 */
final class PermisosConfiguracion
{
    public const TERLEC = 25;

    public const NIVELES = 26;

    public const CAMPOS_LEGAJO_ESTUDIANTE = 27;

    public const SOLAPAS_LEGAJO_ESTUDIANTE = 28;

    public const CAMPOS_LEGAJO_DOCENTE = 29;

    public const SOLAPAS_LEGAJO_DOCENTE = 30;

    public const PARAMETROS_SISTEMA = 31;

    public const NOTIFICACIONES_PUSH = 32;

    public const PLANES_ESTUDIO = 33;

    public const CURSOS_MATERIAS_PLAN = 34;

    public const CURSOS_ANIO = 35;

    public const MATERIAS_ANIO = 36;

    public const ASPIRANTES_CAMPOS = PermisosIaCatalog::ASPIRANTES_CAMPOS;

    /** Administrar permisos (módulo Permisos de Usuarios). */
    public const ADMIN_PERMISOS = 0;

    /** Consultar permisos concedidos (módulo Permisos por Usuario). */
    public const PERMISOS_POR_USUARIO = 14;

    /** Consultar usuarios por módulo o función (módulo Permisos por Tarea). */
    public const PERMISOS_POR_TAREA = PermisosIaCatalog::PERMISOS_POR_TAREA;

    /** Configuración de canales escuela–familia (menú Comunicación institucional). */
    public const COM_CANALES = PermisosIaCatalog::COM_CANALES;

    /** ABM de tipos de sanción disciplinaria (notificación a padres). */
    public const SANCION_TIPOS_CONFIG = PermisosIaCatalog::SANCION_TIPOS_CONFIG;

    /**
     * @return list<int>
     */
    public static function ordenesGranulares(): array
    {
        return [
            self::TERLEC,
            self::NIVELES,
            self::CAMPOS_LEGAJO_ESTUDIANTE,
            self::SOLAPAS_LEGAJO_ESTUDIANTE,
            self::CAMPOS_LEGAJO_DOCENTE,
            self::SOLAPAS_LEGAJO_DOCENTE,
            self::PARAMETROS_SISTEMA,
            self::PLANES_ESTUDIO,
            self::CURSOS_MATERIAS_PLAN,
            self::CURSOS_ANIO,
            self::MATERIAS_ANIO,
            self::ASPIRANTES_CAMPOS,
            self::SANCION_TIPOS_CONFIG,
        ];
    }

    public static function tiene(int $orden): bool
    {
        return tienePermiso($orden);
    }

    public static function tieneAlgunAccesoMenu(): bool
    {
        if (tienePermiso(self::ADMIN_PERMISOS)
            || tienePermiso(self::PERMISOS_POR_USUARIO)
            || tienePermiso(self::PERMISOS_POR_TAREA)) {
            return true;
        }

        foreach (self::ordenesGranulares() as $orden) {
            if (tienePermiso($orden)) {
                return true;
            }
        }

        return false;
    }

    public static function tieneAlgunPermisoSistemaMenu(): bool
    {
        return tienePermiso(self::ADMIN_PERMISOS)
            || tienePermiso(self::PERMISOS_POR_USUARIO)
            || tienePermiso(self::PERMISOS_POR_TAREA);
    }

    public static function tieneAlgunPlanCursoModelo(): bool
    {
        return self::tiene(self::PLANES_ESTUDIO) || self::tiene(self::CURSOS_MATERIAS_PLAN);
    }

    public static function tieneAlgunCursoMateriaAnio(): bool
    {
        return self::tiene(self::CURSOS_ANIO) || self::tiene(self::MATERIAS_ANIO);
    }
}
