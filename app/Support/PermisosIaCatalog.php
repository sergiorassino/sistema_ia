<?php

namespace App\Support;

/**
 * Órdenes del catálogo {@see permisos_ia} (posición en profesores.permisos_ia).
 *
 * Cada módulo debe comprobar acceso con tienePermiso(self::ORDEN_*)
 * o middleware permiso:N / permiso-config:N.
 */
final class PermisosIaCatalog
{
    public const ADMIN_PERMISOS = 0;

    public const TOMA_ASISTENCIA_CLASE = 1;

    public const LEGAJOS_ESTUDIANTES = 2;

    public const COM_BANDEJA = 3;

    public const COM_NUEVO = 4;

    public const COM_CANALES = 5;

    public const COM_BORRAR_PROPIO = 6;

    public const COM_BORRAR_OTROS = 7;

    public const COM_REVISION = 8;

    public const CALIF_SINCRO_CARGA = 9;

    public const CALIF_COLOQUIOS = 10;

    public const LEGAJOS_DOCENTES = 11;

    /** Asignación ppc y consulta cursos por profesor (separado del ABM de legajo docente). */
    public const ASIGNACION_PROFESORES_POR_CURSO = 48;

    public const EXAMENES = 12;

    public const HORARIOS = 13;

    public const PERMISOS_POR_USUARIO = 14;

    public const CALIF_CIERRE_ANUAL = 15;

    public const MATRIZ_ANALITICO = 16;

    public const CERT_ALUMNO_REGULAR = 17;

    public const CERT_ESTUDIOS_TRAMITE = 18;

    public const CERT_CONSTANCIA_DOCS = 19;

    public const CERT_ASISTENCIA_PROF = 20;

    public const CERT_PASE_PARCIAL = 21;

    public const CERT_SOLICITUD_PASE = 22;

    public const INASISTENCIAS_DOCENTES = 23;

    public const INASISTENCIAS_SINCRO_CIDI = 24;

    public const SEGUIMIENTO_DISCIPLINARIO = 37;

    public const INASISTENCIAS_ESTUDIANTES_GESTION = 38;

    public const ASPIRANTES_GESTION = 39;

    public const ASPIRANTES_CAMPOS = 40;

    public const COM_AUDITORIA = 43;

    public const MATRICULA_WEB_DOCUMENTOS = 44;

    public const SOLICITUDES_EVALUACION_GESTION = 45;

    public const LEGAJOS_FAMILIAS_GESTION = 46;

    /**
     * Nivel Administración: crear/editar/eliminar legajos y matrículas en cualquier nivel pedagógico (1–4).
     * Sin este permiso: solo consulta de legajos en Administración (sin alta/edición cross-nivel).
     */
    public const LEGAJOS_MODIFICAR_ADMIN = 47;

    /** Menú Administración — aranceles por estudiante (búsqueda, cuotas, pagos, comprobantes). */
    public const ADMIN_ARANCELES_ESTUDIANTE = 49;

    /** Menú Administración — crear / editar plantillas de cuotas del ciclo. */
    public const ADMIN_CUOTAS_PLANTILLAS = 50;

    /** Menú Administración — importes y bonificaciones por curso. */
    public const ADMIN_CUOTAS_IMPORTES_CURSO = 51;

    /** Menú Administración — generación masiva de cuotas. */
    public const ADMIN_CUOTAS_GENERACION_MASIVA = 52;

    /** Menú Administración — eliminación masiva de cuotas generadas sin pago. */
    public const ADMIN_CUOTAS_ELIMINACION_MASIVA = 53;

    /** Menú Administración — edición masiva de cuotas generadas. */
    public const ADMIN_CUOTAS_EDICION_GENERADAS = 54;

    /** Menú Administración — cancelar todas las reservas del ciclo. */
    public const ADMIN_CUOTAS_CANCELAR_RESERVAS = 55;

    /** Menú Administración — libro de aranceles (PDF). */
    public const ADMIN_LIBRO_ARANCELES = 56;

    /** Menú Administración — listado de pagos por fecha (PDF). */
    public const ADMIN_LISTADO_PAGOS_FECHA = 57;

    /** Menú Administración — listado de estudiantes por cuota (PDF). */
    public const ADMIN_LISTADO_ESTUDIANTES_CUOTA = 58;

    /** Menú Administración — tipos de beca. */
    public const ADMIN_BECAS_TIPOS = 59;

    /** Menú Administración — asignación de becas. */
    public const ADMIN_BECAS_ASIGNACION = 60;

    /** Menú Administración — resumen de becas por nivel. */
    public const ADMIN_BECAS_RESUMEN_NIVEL = 61;

    /** Menú Administración — solicitud de ayuda familiar (PDF). */
    public const ADMIN_BECAS_SOLICITUD_AYUDA = 62;

    /** Menú Administración — estado de deuda familiar. */
    public const ADMIN_MORA_ESTADO_DEUDA = 63;

    /** Menú Administración — gestión de morosos (listados y notificaciones). */
    public const ADMIN_MORA_GESTION_MOROSOS = 64;

    /** Menú de Secretaría — estadística de rendimiento escolar (nivel medio). */
    public const ESTADISTICA_RENDIMIENTO_ESCOLAR = 65;

    /** @return list<array{id: int, orden: int, tema: string, descripcion: string}> */
    public static function definicionCatalogo(): array
    {
        return [
            ['id' => 1, 'orden' => self::ADMIN_PERMISOS, 'tema' => 'ADMINISTRACIÓN', 'descripcion' => 'Administrar permisos del portal de gestión (sistema nuevo).'],
            ['id' => 2, 'orden' => self::TOMA_ASISTENCIA_CLASE, 'tema' => 'ASISTENCIA ESTUDIANTES', 'descripcion' => 'Toma de asistencia a clase por curso, fecha y tipo (clase / educación física).'],
            ['id' => 3, 'orden' => self::LEGAJOS_ESTUDIANTES, 'tema' => 'LEGAJOS ESTUDIANTES', 'descripcion' => 'Crear, editar y eliminar legajos de estudiantes; gestionar matrículas.'],
            ['id' => 4, 'orden' => self::COM_BANDEJA, 'tema' => 'COMUNICACIONES', 'descripcion' => 'Ver la bandeja de comunicados y los hilos de conversación.'],
            ['id' => 5, 'orden' => self::COM_NUEVO, 'tema' => 'COMUNICACIONES', 'descripcion' => 'Iniciar nuevos comunicados hacia familias.'],
            ['id' => 6, 'orden' => self::COM_CANALES, 'tema' => 'COMUNICACIONES - CONFIG', 'descripcion' => 'Administrar la configuración de canales (quién puede comunicarse con quién y por qué medios).'],
            ['id' => 7, 'orden' => self::COM_BORRAR_PROPIO, 'tema' => 'COMUNICACIONES', 'descripcion' => 'Borrar mensajes propios en un hilo.'],
            ['id' => 8, 'orden' => self::COM_BORRAR_OTROS, 'tema' => 'COMUNICACIONES', 'descripcion' => 'Borrar mensajes de otros participantes en un hilo.'],
            ['id' => 9, 'orden' => self::COM_REVISION, 'tema' => 'COMUNICACIONES', 'descripcion' => 'Acceder a la bandeja de revisión de comunicados.'],
            ['id' => 10, 'orden' => self::CALIF_SINCRO_CARGA, 'tema' => 'CALIFICACIONES SECUNDARIO', 'descripcion' => 'Importar calificaciones desde CIDI/GE y carga manual de calificaciones (secundario).'],
            ['id' => 11, 'orden' => self::CALIF_COLOQUIOS, 'tema' => 'CALIFICACIONES SECUNDARIO', 'descripcion' => 'Carga de coloquios Dic / Feb (secundario).'],
            ['id' => 12, 'orden' => self::LEGAJOS_DOCENTES, 'tema' => 'LEGAJOS DOCENTES', 'descripcion' => 'Crear, editar y eliminar legajos de docentes (ABM legajo docente).'],
            ['id' => 48, 'orden' => self::ASIGNACION_PROFESORES_POR_CURSO, 'tema' => 'LEGAJOS DOCENTES', 'descripcion' => 'Asignar y quitar docentes en materias por curso (ppc); consultar cursos por profesor.'],
            ['id' => 13, 'orden' => self::EXAMENES, 'tema' => 'EXÁMENES', 'descripcion' => 'Módulo de exámenes: materias adeudadas, gestión, listados y borrado de inscripciones.'],
            ['id' => 14, 'orden' => self::HORARIOS, 'tema' => 'HORARIOS', 'descripcion' => 'Configuración de horarios (turnos, días, reloj) y carga de horas cátedra por docente.'],
            ['id' => 15, 'orden' => self::PERMISOS_POR_USUARIO, 'tema' => 'ADMINISTRACIÓN', 'descripcion' => 'Consultar permisos concedidos por usuario (módulo Permisos por Usuario).'],
            ['id' => 17, 'orden' => self::CALIF_CIERRE_ANUAL, 'tema' => 'CALIFICACIONES SECUNDARIO', 'descripcion' => 'Cierre anual: historial de calificaciones y pasaje al matriz (Dic / Feb).'],
            ['id' => 18, 'orden' => self::MATRIZ_ANALITICO, 'tema' => 'MATRÍZ Y ANALÍTICOS', 'descripcion' => 'Libro matriz, pase y certificado analítico: consulta y edición de calificaciones en matriz.'],
            ['id' => 19, 'orden' => self::CERT_ALUMNO_REGULAR, 'tema' => 'CERTIFICADOS', 'descripcion' => 'Certificado escolar de alumno/a regular: listado de matriculados del año en curso y emisión de PDF.'],
            ['id' => 20, 'orden' => self::CERT_ESTUDIOS_TRAMITE, 'tema' => 'CERTIFICADOS', 'descripcion' => 'Constancia de certificado de estudios en trámite: listado de matriculados y emisión de PDF.'],
            ['id' => 21, 'orden' => self::CERT_CONSTANCIA_DOCS, 'tema' => 'CERTIFICADOS', 'descripcion' => 'Constancia de documentos: listado de matriculados y emisión de PDF.'],
            ['id' => 22, 'orden' => self::CERT_ASISTENCIA_PROF, 'tema' => 'CERTIFICADOS', 'descripcion' => 'Certificado de asistencia del profesor: listado de personal del legajo y emisión de PDF.'],
            ['id' => 23, 'orden' => self::CERT_PASE_PARCIAL, 'tema' => 'CERTIFICADOS', 'descripcion' => 'Pase parcial: listado de legajos de nivel medio, solicitud y emisión de PDF.'],
            ['id' => 24, 'orden' => self::CERT_SOLICITUD_PASE, 'tema' => 'CERTIFICADOS', 'descripcion' => 'Solicitud de pase: listado de legajos de nivel medio, datos en paseprovisorio y emisión de PDF.'],
            ['id' => 25, 'orden' => self::INASISTENCIAS_DOCENTES, 'tema' => 'INASISTENCIAS DOCENTES', 'descripcion' => 'Gestión de inasistencias docentes: cargos, registros, informes por bimestre y PDF.'],
            ['id' => 26, 'orden' => self::INASISTENCIAS_SINCRO_CIDI, 'tema' => 'ASISTENCIA ESTUDIANTES', 'descripcion' => 'Importar inasistencias de estudiantes desde CSV CIDI/GE (InasistenciasDetalle).'],
            ['id' => 27, 'orden' => PermisosConfiguracion::TERLEC, 'tema' => 'CONFIGURACIÓN', 'descripcion' => 'Términos lectivos.'],
            ['id' => 28, 'orden' => PermisosConfiguracion::NIVELES, 'tema' => 'CONFIGURACIÓN', 'descripcion' => 'Niveles educativos.'],
            ['id' => 29, 'orden' => PermisosConfiguracion::CAMPOS_LEGAJO_ESTUDIANTE, 'tema' => 'CONFIGURACIÓN', 'descripcion' => 'Campos activos del legajo del estudiante.'],
            ['id' => 30, 'orden' => PermisosConfiguracion::SOLAPAS_LEGAJO_ESTUDIANTE, 'tema' => 'CONFIGURACIÓN', 'descripcion' => 'Solapas del legajo del estudiante.'],
            ['id' => 31, 'orden' => PermisosConfiguracion::CAMPOS_LEGAJO_DOCENTE, 'tema' => 'CONFIGURACIÓN', 'descripcion' => 'Campos activos del legajo del docente.'],
            ['id' => 32, 'orden' => PermisosConfiguracion::SOLAPAS_LEGAJO_DOCENTE, 'tema' => 'CONFIGURACIÓN', 'descripcion' => 'Solapas del legajo del docente.'],
            ['id' => 33, 'orden' => PermisosConfiguracion::PARAMETROS_SISTEMA, 'tema' => 'CONFIGURACIÓN', 'descripcion' => 'Parámetros del sistema.'],
            ['id' => 34, 'orden' => PermisosConfiguracion::NOTIFICACIONES_PUSH, 'tema' => 'CONFIGURACIÓN', 'descripcion' => 'Notificaciones push (suscripción en este dispositivo).'],
            ['id' => 35, 'orden' => PermisosConfiguracion::PLANES_ESTUDIO, 'tema' => 'CONFIGURACIÓN', 'descripcion' => 'Gestión de planes de estudio.'],
            ['id' => 36, 'orden' => PermisosConfiguracion::CURSOS_MATERIAS_PLAN, 'tema' => 'CONFIGURACIÓN', 'descripcion' => 'Gestión de cursos y materias del plan.'],
            ['id' => 37, 'orden' => PermisosConfiguracion::CURSOS_ANIO, 'tema' => 'CONFIGURACIÓN', 'descripcion' => 'Gestión de cursos / grados / salas del año.'],
            ['id' => 38, 'orden' => PermisosConfiguracion::MATERIAS_ANIO, 'tema' => 'CONFIGURACIÓN', 'descripcion' => 'Gestión de asignaturas del año.'],
            ['id' => 39, 'orden' => self::SEGUIMIENTO_DISCIPLINARIO, 'tema' => 'SEGUIMIENTO DISCIPLINARIO', 'descripcion' => 'Registro de sanciones, antecedentes disciplinarios e impresión de comunicados.'],
            ['id' => 40, 'orden' => self::INASISTENCIAS_ESTUDIANTES_GESTION, 'tema' => 'ASISTENCIA ESTUDIANTES', 'descripcion' => 'Gestión de inasistencias del estudiante: alta, edición, baja e informe individual en PDF.'],
            ['id' => 41, 'orden' => self::ASPIRANTES_GESTION, 'tema' => 'ASPIRANTES', 'descripcion' => 'Gestión de aspirantes: parametrización de la instancia de registro, cursos disponibles y listado de inscriptos.'],
            ['id' => 42, 'orden' => self::ASPIRANTES_CAMPOS, 'tema' => 'CONFIGURACIÓN', 'descripcion' => 'Campos activos del formulario público de aspirantes.'],
            ['id' => 43, 'orden' => self::COM_AUDITORIA, 'tema' => 'COMUNICACIONES', 'descripcion' => 'Auditoría de comunicación institucional: consultar borrados y marcas de lectura en bandejas.'],
            ['id' => 44, 'orden' => self::MATRICULA_WEB_DOCUMENTOS, 'tema' => 'MATRÍCULA WEB', 'descripcion' => 'Documentos de aceptación (PDF por nivel): compromiso educativo, AEC, normativas y traslado para el portal de estudiantes.'],
            ['id' => 45, 'orden' => self::SOLICITUDES_EVALUACION_GESTION, 'tema' => 'CALIFICACIONES SECUNDARIO', 'descripcion' => 'Gestión de solicitudes de evaluación: listado por fecha, alta, edición y baja de evaluaciones programadas (tabla evaluac).'],
            ['id' => 46, 'orden' => self::LEGAJOS_FAMILIAS_GESTION, 'tema' => 'LEGAJOS ESTUDIANTES', 'descripcion' => 'Gestionar familias de estudiantes: crear, editar, eliminar y asignar o quitar vínculos con legajos (la consulta permanece disponible para todos).'],
            ['id' => 47, 'orden' => self::LEGAJOS_MODIFICAR_ADMIN, 'tema' => 'LEGAJOS ESTUDIANTES', 'descripcion' => 'Nivel Administración: crear, editar, eliminar legajos y matrículas en Inicial, Primario y Secundario (cualquier nivel pedagógico del ciclo activo).'],
            ['id' => 49, 'orden' => self::ADMIN_ARANCELES_ESTUDIANTE, 'tema' => 'GESTIÓN DE ARANCELES', 'descripcion' => 'Aranceles por estudiante: búsqueda, cuotas generadas, pagos, comprobantes y resumen de pagos.'],
            ['id' => 50, 'orden' => self::ADMIN_CUOTAS_PLANTILLAS, 'tema' => 'GESTIÓN MASIVA DE CUOTAS', 'descripcion' => 'Crear y editar plantillas de cuotas del año lectivo activo.'],
            ['id' => 51, 'orden' => self::ADMIN_CUOTAS_IMPORTES_CURSO, 'tema' => 'GESTIÓN MASIVA DE CUOTAS', 'descripcion' => 'Importes y bonificaciones o intereses por curso.'],
            ['id' => 52, 'orden' => self::ADMIN_CUOTAS_GENERACION_MASIVA, 'tema' => 'GESTIÓN MASIVA DE CUOTAS', 'descripcion' => 'Generación masiva de cuotas para estudiantes regulares por curso.'],
            ['id' => 53, 'orden' => self::ADMIN_CUOTAS_ELIMINACION_MASIVA, 'tema' => 'GESTIÓN MASIVA DE CUOTAS', 'descripcion' => 'Eliminar masivamente cuotas generadas sin pagos por curso.'],
            ['id' => 54, 'orden' => self::ADMIN_CUOTAS_EDICION_GENERADAS, 'tema' => 'GESTIÓN MASIVA DE CUOTAS', 'descripcion' => 'Edición masiva de importes y vencimientos de cuotas generadas.'],
            ['id' => 55, 'orden' => self::ADMIN_CUOTAS_CANCELAR_RESERVAS, 'tema' => 'GESTIÓN MASIVA DE CUOTAS', 'descripcion' => 'Cancelar todas las reservas del ciclo (importe y saldo en cero).'],
            ['id' => 56, 'orden' => self::ADMIN_LIBRO_ARANCELES, 'tema' => 'RESÚMENES DE ARANCELES', 'descripcion' => 'Libro de aranceles por curso (PDF apaisado).'],
            ['id' => 57, 'orden' => self::ADMIN_LISTADO_PAGOS_FECHA, 'tema' => 'RESÚMENES DE ARANCELES', 'descripcion' => 'Listado de pagos recibidos entre dos fechas (PDF).'],
            ['id' => 58, 'orden' => self::ADMIN_LISTADO_ESTUDIANTES_CUOTA, 'tema' => 'RESÚMENES DE ARANCELES', 'descripcion' => 'Listado de estudiantes con cuotas generadas (PDF apaisado).'],
            ['id' => 59, 'orden' => self::ADMIN_BECAS_TIPOS, 'tema' => 'BECAS', 'descripcion' => 'Tipos de beca y porcentaje de descuento.'],
            ['id' => 60, 'orden' => self::ADMIN_BECAS_ASIGNACION, 'tema' => 'BECAS', 'descripcion' => 'Asignar beca a alumnos por curso o búsqueda individual.'],
            ['id' => 61, 'orden' => self::ADMIN_BECAS_RESUMEN_NIVEL, 'tema' => 'BECAS', 'descripcion' => 'Resumen de becas otorgadas por tipo y nivel pedagógico.'],
            ['id' => 62, 'orden' => self::ADMIN_BECAS_SOLICITUD_AYUDA, 'tema' => 'BECAS', 'descripcion' => 'Buscar estudiante e imprimir solicitud de ayuda familiar.'],
            ['id' => 63, 'orden' => self::ADMIN_MORA_ESTADO_DEUDA, 'tema' => 'GESTIÓN DE MORA', 'descripcion' => 'Estado de deuda familiar: listado de familias y deuda.'],
            ['id' => 64, 'orden' => self::ADMIN_MORA_GESTION_MOROSOS, 'tema' => 'GESTIÓN DE MORA', 'descripcion' => 'Gestión de morosos: filtros, listado de deuda (PDF) y notificaciones.'],
            ['id' => 65, 'orden' => self::ESTADISTICA_RENDIMIENTO_ESCOLAR, 'tema' => 'ESTADÍSTICAS', 'descripcion' => 'Estadística de rendimiento escolar: aprobación por materias, docentes y estudiantes (nivel medio).'],
        ];
    }

    public static function maxOrden(): int
    {
        $max = 0;
        foreach (self::definicionCatalogo() as $row) {
            $max = max($max, (int) $row['orden']);
        }

        return $max;
    }
}
