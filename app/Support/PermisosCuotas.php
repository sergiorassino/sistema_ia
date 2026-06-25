<?php

namespace App\Support;

/**
 * Permisos por ítem del Menú de Administración — aranceles, gestión masiva, resúmenes y becas.
 *
 * Requiere sesión nivel Administración ({@see schoolEsAdministracion}) y el orden correspondiente en
 * {@see profesores.permisos_ia}.
 */
final class PermisosCuotas
{
    private static function enAdministracion(): bool
    {
        return schoolEsAdministracion();
    }

    private static function tiene(int $orden): bool
    {
        return self::enAdministracion() && tienePermiso($orden);
    }

    public static function puedeArancelesPorEstudiante(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_ARANCELES_ESTUDIANTE);
    }

    /** Subida de base de deuda a SIRO (solo tenants con SIRO habilitado). */
    public static function puedeSiroSubidaBaseDeuda(): bool
    {
        return self::puedeArancelesPorEstudiante() && tenantCuotasSiroHabilitado();
    }

    /** Descarga de planillas de rendición SIRO (solo tenants con SIRO habilitado). */
    public static function puedeSiroDescargaRendicion(): bool
    {
        return self::puedeArancelesPorEstudiante() && tenantCuotasSiroHabilitado();
    }

    /** Consulta de comprobantes AFIP (solo tenants con facturación AFIP habilitada). */
    public static function puedeConsultaAfipComprobante(): bool
    {
        return self::puedeArancelesPorEstudiante() && tenantCuotasFacturacionAfipHabilitada();
    }

    public static function puedePlantillas(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_CUOTAS_PLANTILLAS);
    }

    public static function puedeImportesPorCurso(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_CUOTAS_IMPORTES_CURSO);
    }

    public static function puedeGeneracionMasiva(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_CUOTAS_GENERACION_MASIVA);
    }

    public static function puedeEliminacionMasiva(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_CUOTAS_ELIMINACION_MASIVA);
    }

    public static function puedeEdicionCuotasGeneradas(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_CUOTAS_EDICION_GENERADAS);
    }

    public static function puedeCancelarTodasReservas(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_CUOTAS_CANCELAR_RESERVAS);
    }

    public static function puedeLibroAranceles(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_LIBRO_ARANCELES);
    }

    public static function puedeListadoPagosPorFecha(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_LISTADO_PAGOS_FECHA);
    }

    public static function puedeListadoEstudiantesPorCuota(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_LISTADO_ESTUDIANTES_CUOTA);
    }

    public static function puedeTiposBeca(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_BECAS_TIPOS);
    }

    public static function puedeAsignacionBecas(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_BECAS_ASIGNACION);
    }

    public static function puedeResumenBecasPorNivel(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_BECAS_RESUMEN_NIVEL);
    }

    public static function puedeSolicitudAyudaFamiliar(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_BECAS_SOLICITUD_AYUDA);
    }

    /** Grupo sidebar «Gestión de aranceles». */
    public static function muestraGrupoGestionAranceles(): bool
    {
        return self::puedeArancelesPorEstudiante() || self::puedeConsultaAfipComprobante();
    }

    /** Grupo sidebar «Gestión masiva». */
    public static function muestraGrupoGestionMasiva(): bool
    {
        return self::puedePlantillas()
            || self::puedeImportesPorCurso()
            || self::puedeGeneracionMasiva()
            || self::puedeEliminacionMasiva()
            || self::puedeEdicionCuotasGeneradas()
            || self::puedeCancelarTodasReservas();
    }

    /** Grupo sidebar «Resúmenes». */
    public static function muestraGrupoResumenes(): bool
    {
        return self::puedeLibroAranceles()
            || self::puedeListadoPagosPorFecha()
            || self::puedeListadoEstudiantesPorCuota();
    }

    /** Grupo sidebar «Becas». */
    public static function muestraGrupoBecas(): bool
    {
        return self::puedeTiposBeca()
            || self::puedeAsignacionBecas()
            || self::puedeResumenBecasPorNivel()
            || self::puedeSolicitudAyudaFamiliar();
    }
}
