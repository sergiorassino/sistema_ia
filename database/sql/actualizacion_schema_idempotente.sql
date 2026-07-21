-- =============================================================================
-- Actualización de esquema — idempotente (re-ejecutable)
-- Compatible con MySQL 5.7+ / MariaDB 10.x
--
-- - Tablas: CREATE TABLE IF NOT EXISTS
-- - Columnas: procedimiento sp_add_column_if_missing
-- - Datos semilla: INSERT ... ON DUPLICATE KEY UPDATE
--
-- Ejecutar en la base del colegio (phpMyAdmin, HeidiSQL, mysql CLI).
-- Revisar backup antes de aplicar en producción.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Utilidad: agregar columna solo si no existe
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_add_column_if_missing;
DELIMITER $$
CREATE PROCEDURE sp_add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @ddl = CONCAT(
            'ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition
        );
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- -----------------------------------------------------------------------------
-- Utilidad: quitar columna solo si existe
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_drop_column_if_exists;
DELIMITER $$
CREATE PROCEDURE sp_drop_column_if_exists(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @ddl = CONCAT(
            'ALTER TABLE `', p_table, '` DROP COLUMN `', p_column, '`'
        );
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- =============================================================================
-- 1. Legajo alumnos — solapas y campos
-- =============================================================================
CREATE TABLE IF NOT EXISTS `solapas_legajo` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `slug` varchar(30) NOT NULL,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `solapas_legajo_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `solapas_legajo` (`id`, `nombre`, `slug`, `orden`) VALUES
(1, 'Alumno',   'alumno',   1),
(2, 'Contacto', 'contacto', 2),
(3, 'Madre',    'madre',    3),
(4, 'Padre',    'padre',    4),
(5, 'Tutor',    'tutor',    5),
(6, 'Otros',    'otros',    6)
ON DUPLICATE KEY UPDATE
  `nombre` = VALUES(`nombre`),
  `orden`  = VALUES(`orden`);

CREATE TABLE IF NOT EXISTS `campos_legajo` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `columna` varchar(64) NOT NULL,
  `etiqueta` varchar(150) DEFAULT NULL,
  `visible_listado` tinyint(1) NOT NULL DEFAULT 1,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `solapa_legajo_id` bigint(20) unsigned DEFAULT NULL,
  `orden_en_solapa` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campos_listado_alumnos_columna_unique` (`columna`),
  KEY `campos_legajo_solapa_legajo_id_foreign` (`solapa_legajo_id`),
  CONSTRAINT `campos_legajo_solapa_legajo_id_foreign`
    FOREIGN KEY (`solapa_legajo_id`) REFERENCES `solapas_legajo` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. Entidad (ento) y cursos
-- =============================================================================
CALL sp_add_column_if_missing('ento', 'logo_path', 'varchar(255) DEFAULT NULL AFTER `replegal`');
CALL sp_add_column_if_missing('ento', 'logo_original_name', 'varchar(255) DEFAULT NULL AFTER `logo_path`');
CALL sp_add_column_if_missing('ento', 'cuit', 'VARCHAR(13) NULL DEFAULT NULL AFTER `insti`');
CALL sp_add_column_if_missing('ento', 'ee', 'VARCHAR(20) NULL DEFAULT NULL AFTER `cue`');
CALL sp_add_column_if_missing('cursos', 'turno', 'VARCHAR(20) NULL DEFAULT NULL AFTER `s`');
CALL sp_add_column_if_missing('cursos', 'idTurnoClase', 'TINYINT UNSIGNED NULL DEFAULT NULL');

-- =============================================================================
-- 3. Módulo comunicaciones (com_*)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `com_canales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rol_emisor` enum('directivo','preceptor','profesor','familia') NOT NULL,
  `rol_receptor` enum('directivo','preceptor','profesor','familia') NOT NULL,
  `puede_iniciar` tinyint(1) NOT NULL DEFAULT 0,
  `puede_responder` tinyint(1) NOT NULL DEFAULT 0,
  `medios_permitidos` json DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_canal_par` (`rol_emisor`,`rol_receptor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL sp_add_column_if_missing('com_canales', 'id_nivel', 'INT UNSIGNED NULL AFTER `id`');

CREATE TABLE IF NOT EXISTS `com_hilos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asunto` varchar(200) NOT NULL,
  `cuerpo_inicial_id` bigint(20) unsigned DEFAULT NULL,
  `scope` enum('alumno','varios_alumnos','curso','varios_cursos','colegio','docentes') NOT NULL,
  `id_legajo` int(10) unsigned DEFAULT NULL,
  `id_curso` int(10) unsigned DEFAULT NULL,
  `cursos_envio` json DEFAULT NULL,
  `id_nivel` int(10) unsigned DEFAULT NULL,
  `id_terlec` int(10) unsigned DEFAULT NULL,
  `creado_por_tipo` enum('profesor','familia') NOT NULL,
  `creado_por_id` int(10) unsigned NOT NULL,
  `creado_por_rol` varchar(30) DEFAULT NULL,
  `estado` enum('abierto','cerrado') NOT NULL DEFAULT 'abierto',
  `familia_puede_responder` tinyint(1) NOT NULL DEFAULT 1,
  `docentes_permite_respuestas` tinyint(1) DEFAULT NULL COMMENT 'Docentes: NULL=permitir respuestas (legado); 0=solo informativo; 1=permitir',
  `ultimo_mensaje_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `com_hilos_id_nivel_id_terlec_index` (`id_nivel`,`id_terlec`),
  KEY `com_hilos_id_legajo_index` (`id_legajo`),
  KEY `com_hilos_id_curso_index` (`id_curso`),
  KEY `com_hilos_creado_por_tipo_creado_por_id_index` (`creado_por_tipo`,`creado_por_id`),
  KEY `com_hilos_ultimo_mensaje_at_index` (`ultimo_mensaje_at`),
  KEY `com_hilos_cuerpo_inicial_id_index` (`cuerpo_inicial_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `com_mensajes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_hilo` bigint(20) unsigned NOT NULL,
  `id_mensaje_padre` bigint(20) unsigned DEFAULT NULL,
  `tipo_remitente` enum('profesor','familia') NOT NULL,
  `id_profesor` int(10) unsigned DEFAULT NULL,
  `id_legajo` int(10) unsigned DEFAULT NULL,
  `rol_remitente` varchar(30) DEFAULT NULL,
  `vinculo_familiar` enum('madre','padre','tutor','resp_admin','otro') DEFAULT NULL,
  `nombre_remitente_snapshot` varchar(150) DEFAULT NULL,
  `dni_remitente_snapshot` varchar(20) DEFAULT NULL,
  `contenido` text NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `com_mensajes_id_hilo_created_at_index` (`id_hilo`,`created_at`),
  KEY `com_mensajes_tipo_remitente_id_profesor_index` (`tipo_remitente`,`id_profesor`),
  KEY `com_mensajes_tipo_remitente_id_legajo_index` (`tipo_remitente`,`id_legajo`),
  CONSTRAINT `com_mensajes_id_hilo_foreign` FOREIGN KEY (`id_hilo`) REFERENCES `com_hilos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `com_mensajes_destinatarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_mensaje` bigint(20) unsigned NOT NULL,
  `id_hilo` bigint(20) unsigned NOT NULL,
  `tipo_destinatario` enum('profesor','familia') NOT NULL,
  `id_profesor` int(10) unsigned DEFAULT NULL,
  `id_legajo` int(10) unsigned DEFAULT NULL,
  `rol_destinatario` varchar(30) DEFAULT NULL,
  `nombre_snapshot` varchar(150) DEFAULT NULL,
  `dni_snapshot` varchar(20) DEFAULT NULL,
  `leido_at` timestamp NULL DEFAULT NULL,
  `respondido_at` timestamp NULL DEFAULT NULL,
  `id_mensaje_respuesta` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `com_mensajes_destinatarios_id_mensaje_foreign` (`id_mensaje`),
  KEY `idx_dest_legajo_leido` (`tipo_destinatario`,`id_legajo`,`leido_at`),
  KEY `idx_dest_prof_leido` (`tipo_destinatario`,`id_profesor`,`leido_at`),
  KEY `idx_cmd_hilo_tipo_legajo` (`id_hilo`,`tipo_destinatario`,`id_legajo`),
  KEY `idx_cmd_hilo_tipo_prof` (`id_hilo`,`tipo_destinatario`,`id_profesor`),
  CONSTRAINT `com_mensajes_destinatarios_id_hilo_foreign` FOREIGN KEY (`id_hilo`) REFERENCES `com_hilos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `com_mensajes_destinatarios_id_mensaje_foreign` FOREIGN KEY (`id_mensaje`) REFERENCES `com_mensajes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `com_mensajes_envios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_mensaje_destinatario` bigint(20) unsigned NOT NULL,
  `medio` enum('push','email','whatsapp') NOT NULL,
  `estado` enum('pendiente','enviado','fallido','no_aplicable') NOT NULL DEFAULT 'pendiente',
  `motivo` varchar(255) DEFAULT NULL,
  `proveedor_msgid` text DEFAULT NULL,
  `enviado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `com_mensajes_envios_id_mensaje_destinatario_medio_index` (`id_mensaje_destinatario`,`medio`),
  CONSTRAINT `com_mensajes_envios_id_mensaje_destinatario_foreign` FOREIGN KEY (`id_mensaje_destinatario`) REFERENCES `com_mensajes_destinatarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `com_preferencias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tipo_usuario` enum('familia','profesor') NOT NULL,
  `id_legajo` int(10) unsigned DEFAULT NULL,
  `id_profesor` int(10) unsigned DEFAULT NULL,
  `vinculo_contacto` enum('madre','padre','tutor','resp_admin','otro') DEFAULT NULL,
  `vinculos_contacto` json DEFAULT NULL,
  `push` tinyint(1) NOT NULL DEFAULT 1,
  `email` tinyint(1) NOT NULL DEFAULT 1,
  `whatsapp` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pref_legajo` (`id_legajo`),
  UNIQUE KEY `uq_pref_profesor` (`id_profesor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `com_canales` (`rol_emisor`,`rol_receptor`,`puede_iniciar`,`puede_responder`,`medios_permitidos`,`activo`,`created_at`,`updated_at`) VALUES
('familia','preceptor',1,1,'["push","email","whatsapp"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('familia','profesor',0,1,'["push","email"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('familia','directivo',1,1,'["push","email"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('profesor','familia',1,0,'["push","email"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('preceptor','familia',1,1,'["push","email","whatsapp"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('directivo','familia',1,1,'["push","email","whatsapp"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('preceptor','profesor',1,1,'["push","email"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('profesor','profesor',1,1,'["push","email"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('profesor','preceptor',1,1,'["push","email"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('profesor','directivo',1,1,'["push","email"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('preceptor','preceptor',1,1,'["push","email","whatsapp"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('preceptor','directivo',1,1,'["push","email","whatsapp"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('directivo','profesor',1,1,'["push","email"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('directivo','preceptor',1,1,'["push","email","whatsapp"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('directivo','directivo',1,1,'["push","email","whatsapp"]',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
  `puede_iniciar` = VALUES(`puede_iniciar`),
  `puede_responder` = VALUES(`puede_responder`),
  `medios_permitidos` = VALUES(`medios_permitidos`),
  `activo` = VALUES(`activo`),
  `updated_at` = CURRENT_TIMESTAMP;

-- 3.1 Canales por nivel — índice único (id_nivel + roles)
-- Si al guardar un canal aparece: Duplicate entry 'profesor-familia' for key 'uq_canal_par',
-- ejecutar database/sql/com_canales_indice_por_nivel.sql o la migración 2026_05_28_220000_fix_com_canales_unique_for_nivel.php

DROP PROCEDURE IF EXISTS sp_drop_index_if_exists;
DELIMITER $$
CREATE PROCEDURE sp_drop_index_if_exists(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = p_table
          AND index_name = p_index
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` DROP INDEX `', p_index, '`');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

UPDATE `com_canales` c
SET c.`id_nivel` = (SELECT MIN(n.`id`) FROM `niveles` n)
WHERE c.`id_nivel` IS NULL;

CALL sp_drop_index_if_exists('com_canales', 'uq_canal_par');

-- Crear uq_canal_nivel_par solo si no existe (MySQL 8+ / MariaDB 10.5.2+ no tienen ADD INDEX IF NOT EXISTS estándar)
SET @idx_nivel_par := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'com_canales'
      AND index_name = 'uq_canal_nivel_par'
);
SET @ddl_idx := IF(
    @idx_nivel_par = 0,
    'ALTER TABLE `com_canales` ADD UNIQUE KEY `uq_canal_nivel_par` (`id_nivel`,`rol_emisor`,`rol_receptor`)',
    'SELECT 1'
);
PREPARE stmt_idx FROM @ddl_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

UPDATE `com_canales` SET `id_nivel` = (SELECT MIN(n.`id`) FROM `niveles` n) WHERE `id_nivel` IS NULL;
ALTER TABLE `com_canales` MODIFY `id_nivel` INT UNSIGNED NOT NULL;

DROP PROCEDURE IF EXISTS sp_drop_index_if_exists;

-- =============================================================================
-- 4. Push, boletín, legajos (dnitut)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `endpoint_hash` varchar(64) NOT NULL,
  `endpoint` text NOT NULL,
  `auth_key` varchar(255) NOT NULL,
  `p256dh_key` varchar(255) NOT NULL,
  `user_key` varchar(50) NOT NULL DEFAULT '' COMMENT 'legajo id (string para compatibilidad)',
  `device_type` varchar(20) DEFAULT NULL COMMENT 'mobile|tablet|desktop',
  `user_agent` varchar(512) DEFAULT NULL,
  `device_label` varchar(100) DEFAULT NULL,
  `client_hints` varchar(512) DEFAULT NULL COMMENT 'JSON string',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`endpoint_hash`,`user_key`),
  KEY `push_subscriptions_user_key_index` (`user_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `itemsboletin` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `orden` smallint unsigned NOT NULL DEFAULT 0,
  `etiqueta` varchar(160) NOT NULL,
  `fuente` varchar(32) NOT NULL,
  `condicion_where` varchar(500) NOT NULL,
  `idTerlec` int unsigned NULL DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `itemsboletin_activo_orden_index` (`activo`, `orden`),
  KEY `itemsboletin_idterlec_activo_index` (`idTerlec`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `itemsboletin` (`id`, `orden`, `etiqueta`, `fuente`, `condicion_where`, `idTerlec`, `activo`) VALUES
(1, 1, 'Inasistencias Justificadas', 'inasistencias', 'tipo <> 5 and just = ''J''', NULL, 1),
(2, 2, 'Inasistencias Injustificadas', 'inasistencias', 'tipo <> 5 and just = ''I''', NULL, 1),
(3, 3, 'Total de Inasistencias', 'inasistencias', 'tipo <> 5 and (just = ''J'' or just = ''I'')', NULL, 1),
(4, 4, 'Inasistencias a Educación Física', 'inasistencias', 'tipo = 5', NULL, 1),
(5, 5, 'Apercibimientos Orales', 'sanciones', 'idTipoSancion = 2', NULL, 1),
(6, 6, 'Apercibimientos Escritos', 'sanciones', 'idTipoSancion = 3', NULL, 1),
(7, 7, 'Amonestaciones', 'sanciones', 'idTipoSancion = 1 and publicada = 1', NULL, 1),
(8, 8, 'Suspensiones', 'sanciones', 'idTipoSancion = 6', NULL, 1)
ON DUPLICATE KEY UPDATE
  `orden` = VALUES(`orden`),
  `etiqueta` = VALUES(`etiqueta`),
  `fuente` = VALUES(`fuente`),
  `condicion_where` = VALUES(`condicion_where`),
  `idTerlec` = VALUES(`idTerlec`),
  `activo` = VALUES(`activo`);

-- Solo si existe la columna dnitut (tabla legajos legacy)
SET @has_dnitut := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legajos' AND COLUMN_NAME = 'dnitut'
);
SET @ddl_legajos := IF(@has_dnitut > 0,
  'ALTER TABLE `legajos` CHANGE COLUMN `dnitut` `dnitut` VARCHAR(10) NULL DEFAULT '''' COLLATE utf8mb3_unicode_ci AFTER `nombretut`',
  'SELECT 1'
);
PREPARE stmt_leg FROM @ddl_legajos;
EXECUTE stmt_leg;
DEALLOCATE PREPARE stmt_leg;

-- =============================================================================
-- 5. Horarios
-- =============================================================================
CREATE TABLE IF NOT EXISTS `turnos_clase` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `orden` tinyint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `turnos_clase` (`id`, `codigo`, `nombre`, `orden`) VALUES
  (1, 'manana', 'Mañana', 1),
  (2, 'tarde', 'Tarde', 2),
  (3, 'noche', 'Noche', 3)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `orden` = VALUES(`orden`);

CREATE TABLE IF NOT EXISTS `horarios_config` (
  `idNivel` smallint unsigned NOT NULL,
  `turnos_activos` varchar(20) NOT NULL DEFAULT '1',
  `dias_activos` varchar(20) NOT NULL DEFAULT '1,2,3,4,5',
  PRIMARY KEY (`idNivel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL sp_add_column_if_missing('reloj', 'idTurnoClase', 'tinyint unsigned NULL DEFAULT 1 AFTER `idNivel`');

-- =============================================================================
-- 6. Legajo docentes
-- =============================================================================
CREATE TABLE IF NOT EXISTS `solapas_legajo_profesor` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `slug` varchar(30) NOT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `solapas_legajo_profesor_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `solapas_legajo_profesor` (`nombre`, `slug`, `orden`) VALUES ('DOCENTE', 'docente', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `orden` = VALUES(`orden`);

CREATE TABLE IF NOT EXISTS `campos_profesores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `columna` varchar(80) NOT NULL,
  `etiqueta` varchar(100) DEFAULT NULL,
  `visible_listado` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int unsigned NOT NULL DEFAULT 0,
  `solapa_legajo_profesor_id` bigint unsigned DEFAULT NULL,
  `orden_en_solapa` smallint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `campos_profesores_solapa_legajo_profesor_id_foreign` (`solapa_legajo_profesor_id`),
  CONSTRAINT `campos_profesores_solapa_legajo_profesor_id_foreign`
    FOREIGN KEY (`solapa_legajo_profesor_id`) REFERENCES `solapas_legajo_profesor` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 7. Permisos IA (catálogo alineado con migraciones Laravel)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `permisos_ia` (
  `id` int(4) NOT NULL AUTO_INCREMENT,
  `orden` int(4) NOT NULL,
  `tema` varchar(50) NOT NULL DEFAULT '',
  `descripcion` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permisos_ia_orden_unique` (`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CALL sp_add_column_if_missing('profesores', 'permisos_ia', 'varchar(128) NULL DEFAULT NULL AFTER `permisos`');

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(1, 0, 'ADMINISTRACIÓN', 'Administrar permisos del portal de gestión (sistema nuevo).'),
(2, 1, 'ASISTENCIA ESTUDIANTES', 'Toma de asistencia a clase por curso, fecha y tipo (clase / educación física).'),
(3, 2, 'LEGAJOS ESTUDIANTES', 'Crear, editar y eliminar legajos de estudiantes; gestionar matrículas.'),
(4, 3, 'COMUNICACIONES', 'Ver la bandeja de comunicados y los hilos de conversación.'),
(5, 4, 'COMUNICACIONES', 'Iniciar nuevos comunicados hacia familias.'),
(6, 5, 'COMUNICACIONES - CONFIG', 'Administrar la configuración de canales (quién puede comunicarse con quién y por qué medios).'),
(7, 6, 'COMUNICACIONES', 'Borrar mensajes propios en un hilo.'),
(8, 7, 'COMUNICACIONES', 'Borrar mensajes de otros participantes en un hilo.'),
(9, 8, 'COMUNICACIONES', 'Acceder a la bandeja de revisión de comunicados.'),
(10, 9, 'CALIFICACIONES SECUNDARIO', 'Importar calificaciones desde CIDI/GE y carga manual de calificaciones (secundario).'),
(11, 10, 'CALIFICACIONES SECUNDARIO', 'Carga de coloquios Dic / Feb (secundario).'),
(12, 11, 'LEGAJOS DOCENTES', 'Crear, editar y eliminar legajos de docentes (ABM legajo docente).'),
(48, 48, 'LEGAJOS DOCENTES', 'Asignar y quitar docentes en materias por curso (ppc); consultar cursos por profesor.'),
(13, 12, 'EXÁMENES', 'Módulo de exámenes: materias adeudadas, gestión, listados y borrado de inscripciones.'),
(14, 13, 'HORARIOS', 'Configuración de horarios (turnos, días, reloj) y carga de horas cátedra por docente.'),
(15, 14, 'ADMINISTRACIÓN', 'Consultar permisos concedidos por usuario (módulo Permisos por Usuario).'),
(17, 15, 'CALIFICACIONES SECUNDARIO', 'Cierre anual: historial de calificaciones y pasaje al matriz (Dic / Feb).'),
(18, 16, 'MATRÍZ Y ANALÍTICOS', 'Libro matriz, pase y certificado analítico: consulta y edición de calificaciones en matriz.'),
(27, 25, 'CONFIGURACIÓN', 'Términos lectivos.'),
(28, 26, 'CONFIGURACIÓN', 'Niveles educativos.'),
(29, 27, 'CONFIGURACIÓN', 'Campos activos del legajo del estudiante.'),
(30, 28, 'CONFIGURACIÓN', 'Solapas del legajo del estudiante.'),
(31, 29, 'CONFIGURACIÓN', 'Campos activos del legajo del docente.'),
(32, 30, 'CONFIGURACIÓN', 'Solapas del legajo del docente.'),
(33, 31, 'CONFIGURACIÓN', 'Parámetros del sistema.'),
(34, 32, 'CONFIGURACIÓN', 'Notificaciones push (suscripción en este dispositivo).'),
(35, 33, 'CONFIGURACIÓN', 'Gestión de planes de estudio.'),
(36, 34, 'CONFIGURACIÓN', 'Gestión de cursos y materias del plan.'),
(37, 35, 'CONFIGURACIÓN', 'Gestión de cursos / grados / salas del año.'),
(38, 36, 'CONFIGURACIÓN', 'Gestión de asignaturas del año.'),
(19, 17, 'CERTIFICADOS', 'Certificado escolar de alumno/a regular: listado de matriculados del año en curso y emisión de PDF.'),
(20, 18, 'CERTIFICADOS', 'Constancia de certificado de estudios en trámite: listado de matriculados y emisión de PDF.'),
(21, 19, 'CERTIFICADOS', 'Constancia de documentos: listado de matriculados y emisión de PDF.'),
(22, 20, 'CERTIFICADOS', 'Certificado de asistencia del profesor: listado de personal del legajo y emisión de PDF.'),
(23, 21, 'CERTIFICADOS', 'Pase parcial: listado de legajos de nivel medio, solicitud y emisión de PDF.'),
(24, 22, 'CERTIFICADOS', 'Solicitud de pase: listado de legajos de nivel medio, datos en paseprovisorio y emisión de PDF.'),
(25, 23, 'INASISTENCIAS DOCENTES', 'Gestión de inasistencias docentes: cargos, registros, informes por bimestre y PDF.'),
(26, 24, 'ASISTENCIA ESTUDIANTES', 'Descargar e importar inasistencias de estudiantes desde CSV CIDI/GE (InasistenciasDetalle).'),
(39, 37, 'SEGUIMIENTO DISCIPLINARIO', 'Registro de sanciones, antecedentes disciplinarios e impresión de comunicados.'),
(40, 38, 'ASISTENCIA ESTUDIANTES', 'Gestión de inasistencias del estudiante: alta, edición, baja e informe individual en PDF.'),
(81, 81, 'ASISTENCIA ESTUDIANTES', 'Parte diario del preceptor: selección de curso(s), fecha e impresión PDF por día.'),
(85, 85, 'ASISTENCIA ESTUDIANTES', 'Gestión de TEA por inasistencias: registros por estudiante, alta, edición, baja e impresión PDF por situación.'),
(44, 44, 'MATRÍCULA WEB', 'Documentos de aceptación (PDF por nivel): compromiso educativo, AEC, normativas y traslado para el portal de estudiantes.')
ON DUPLICATE KEY UPDATE
  `orden` = VALUES(`orden`),
  `tema` = VALUES(`tema`),
  `descripcion` = VALUES(`descripcion`);

-- =============================================================================
-- 8. Inasistencias estudiantes — texto CIDI en catálogo de tipos
-- =============================================================================
CALL sp_add_column_if_missing('inasistencias_valores', 'texto_cidi', 'varchar(120) NULL DEFAULT NULL AFTER `concepto`');

-- =============================================================================
-- 10. Aspirantes (gestión de aspirantes + form público)
-- =============================================================================
-- Tabla parametrización de campos del form público (nueva)
CREATE TABLE IF NOT EXISTS `campos_aspirantes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `columna` varchar(80) NOT NULL,
  `orden` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campos_aspirantes_columna_unique` (`columna`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limpieza legacy (columnas globales ya no se usan; viven en campos_aspirantes_nivel)
CALL sp_drop_column_if_exists('campos_aspirantes', 'visible');
CALL sp_drop_column_if_exists('campos_aspirantes', 'obligatorio');

-- Campos visibles/obligatorios/etiqueta/opciones por nivel
CREATE TABLE IF NOT EXISTS `campos_aspirantes_nivel` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campo_aspirante_id` bigint(20) unsigned NOT NULL,
  `idNivel` int(10) unsigned NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT 0,
  `obligatorio` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_campo_aspirante_nivel` (`campo_aspirante_id`,`idNivel`),
  KEY `idx_campos_asp_nivel_idNivel` (`idNivel`),
  CONSTRAINT `fk_campos_asp_nivel_campo`
    FOREIGN KEY (`campo_aspirante_id`) REFERENCES `campos_aspirantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL sp_add_column_if_missing('campos_aspirantes_nivel', 'etiqueta', 'varchar(100) NULL DEFAULT NULL AFTER `obligatorio`');
CALL sp_add_column_if_missing('campos_aspirantes_nivel', 'opciones', 'varchar(500) NULL DEFAULT NULL AFTER `etiqueta`');
CALL sp_add_column_if_missing('campos_aspirantes_nivel', 'ayuda', 'varchar(500) NULL DEFAULT NULL AFTER `opciones`');

-- Copiar etiqueta/opciones globales → por nivel (solo si aún existen en campos_aspirantes)
DROP PROCEDURE IF EXISTS sp_migrate_etiqueta_opciones_aspirantes_nivel;
DELIMITER $$
CREATE PROCEDURE sp_migrate_etiqueta_opciones_aspirantes_nivel()
BEGIN
    IF EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'campos_aspirantes_nivel'
          AND COLUMN_NAME = 'etiqueta'
    ) AND EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'campos_aspirantes'
          AND COLUMN_NAME = 'etiqueta'
    ) THEN
        UPDATE campos_aspirantes_nivel cn
        INNER JOIN campos_aspirantes ca ON ca.id = cn.campo_aspirante_id
        SET cn.etiqueta = COALESCE(cn.etiqueta, ca.etiqueta);
    END IF;

    IF EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'campos_aspirantes_nivel'
          AND COLUMN_NAME = 'opciones'
    ) AND EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'campos_aspirantes'
          AND COLUMN_NAME = 'opciones'
    ) THEN
        UPDATE campos_aspirantes_nivel cn
        INNER JOIN campos_aspirantes ca ON ca.id = cn.campo_aspirante_id
        SET cn.opciones = COALESCE(cn.opciones, ca.opciones);
    END IF;
END$$
DELIMITER ;
CALL sp_migrate_etiqueta_opciones_aspirantes_nivel();
DROP PROCEDURE IF EXISTS sp_migrate_etiqueta_opciones_aspirantes_nivel;

CALL sp_drop_column_if_exists('campos_aspirantes', 'etiqueta');
CALL sp_drop_column_if_exists('campos_aspirantes', 'opciones');

-- Columnas a agregar en tablas legacy (solo si faltan)
CALL sp_add_column_if_missing('aspiento', 'titulo', 'varchar(150) NULL DEFAULT NULL');
CALL sp_add_column_if_missing('aspiento', 'token', 'varchar(64) NULL DEFAULT NULL');
CALL sp_add_column_if_missing('aspiento', 'activo', 'tinyint(1) NOT NULL DEFAULT 0');
CALL sp_add_column_if_missing('aspiento', 'idTerlec', 'int(10) unsigned NULL DEFAULT NULL');
CALL sp_add_column_if_missing('aspiento', 'mensaje_publico', 'text NULL DEFAULT NULL');
CALL sp_add_column_if_missing('aspiento', 'created_at', 'timestamp NULL DEFAULT NULL');
CALL sp_add_column_if_missing('aspiento', 'updated_at', 'timestamp NULL DEFAULT NULL');

-- Índice único en aspiento.token (solo si no existe)
SET @idx_aspiento_token := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'aspiento' AND index_name = 'aspiento_token_unique'
);
SET @ddl := IF(@idx_aspiento_token = 0,
  'ALTER TABLE `aspiento` ADD UNIQUE KEY `aspiento_token_unique` (`token`)',
  'SELECT 1'
);
PREPARE st FROM @ddl; EXECUTE st; DEALLOCATE PREPARE st;

CALL sp_add_column_if_missing('aspicursos', 'idAspiento', 'int(10) unsigned NULL DEFAULT NULL');
CALL sp_add_column_if_missing('aspicursos', 'idCursos', 'int(10) unsigned NULL DEFAULT NULL');
CALL sp_add_column_if_missing('aspicursos', 'idCursoModelo', 'bigint(20) unsigned NULL DEFAULT NULL');
CALL sp_add_column_if_missing('aspicursos', 'activo', 'tinyint(1) NOT NULL DEFAULT 1');

SET @idx_aspicursos := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'aspicursos' AND index_name = 'aspicursos_aspiento_curso_unique'
);
SET @ddl := IF(@idx_aspicursos = 0,
  'ALTER TABLE `aspicursos` ADD UNIQUE KEY `aspicursos_aspiento_curso_unique` (`idAspiento`, `idCursos`)',
  'SELECT 1'
);
PREPARE st FROM @ddl; EXECUTE st; DEALLOCATE PREPARE st;

SET @idx_aspicursos_modelo := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'aspicursos' AND index_name = 'aspicursos_aspiento_modelo_unique'
);
SET @ddl := IF(@idx_aspicursos_modelo = 0,
  'ALTER TABLE `aspicursos` ADD UNIQUE KEY `aspicursos_aspiento_modelo_unique` (`idAspiento`, `idCursoModelo`)',
  'SELECT 1'
);
PREPARE st FROM @ddl; EXECUTE st; DEALLOCATE PREPARE st;

CALL sp_add_column_if_missing('aspirantes', 'idAspiento', 'int(10) unsigned NULL DEFAULT NULL');
CALL sp_add_column_if_missing('aspirantes', 'idCursos', 'int(10) unsigned NULL DEFAULT NULL');
CALL sp_add_column_if_missing('aspirantes', 'idCursoModelo', 'bigint(20) unsigned NULL DEFAULT NULL');
CALL sp_add_column_if_missing('aspirantes', 'idNivel', 'int(10) unsigned NULL DEFAULT NULL');
CALL sp_add_column_if_missing('aspirantes', 'ip_origen', 'varchar(45) NULL DEFAULT NULL');
CALL sp_add_column_if_missing('aspirantes', 'user_agent', 'varchar(255) NULL DEFAULT NULL');
CALL sp_add_column_if_missing('aspirantes', 'created_at', 'timestamp NULL DEFAULT NULL');

-- Catálogo de cursos modelo (sin sección) por nivel
CREATE TABLE IF NOT EXISTS `aspicursosmodelo` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `idNivel` int(10) unsigned NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `aspicursosmodelo_idnivel_index` (`idNivel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permisos nuevos para el módulo (orden 39 / 40)
INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(41, 39, 'ASPIRANTES', 'Gestión de aspirantes: parametrización de la instancia de registro, cursos disponibles y listado de inscriptos.'),
(42, 40, 'CONFIGURACIÓN', 'Campos activos del formulario público de aspirantes.')
ON DUPLICATE KEY UPDATE
  `orden` = VALUES(`orden`),
  `tema` = VALUES(`tema`),
  `descripcion` = VALUES(`descripcion`);

-- =============================================================================
-- 8b. Auditoría comunicación institucional
-- =============================================================================
CREATE TABLE IF NOT EXISTS `com_auditoria` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `accion` enum('marcar_leido','marcar_no_leido','borrar_mensaje','borrar_hilo') NOT NULL,
  `portal` enum('secretaria','docente','familia') NOT NULL,
  `tipo_actor` enum('profesor','familia') NOT NULL,
  `actor_categoria` enum('estudiante','profesor','personal') NOT NULL,
  `id_profesor_actor` int unsigned DEFAULT NULL,
  `id_legajo_actor` int unsigned DEFAULT NULL,
  `nombre_actor_snapshot` varchar(150) NOT NULL,
  `dni_actor_snapshot` varchar(20) DEFAULT NULL,
  `id_hilo` bigint unsigned NOT NULL,
  `hilo_asunto_snapshot` varchar(200) NOT NULL,
  `id_mensaje` bigint unsigned DEFAULT NULL,
  `mensaje_contenido_snapshot` text DEFAULT NULL,
  `mensaje_fecha_snapshot` date DEFAULT NULL,
  `mensaje_remitente_snapshot` varchar(200) DEFAULT NULL,
  `mensaje_destinatario_snapshot` text DEFAULT NULL,
  `id_nivel` int unsigned NOT NULL,
  `id_terlec` int unsigned NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_com_aud_nivel_terlec` (`id_nivel`,`id_terlec`,`created_at`),
  KEY `idx_com_aud_prof` (`tipo_actor`,`id_profesor_actor`,`created_at`),
  KEY `idx_com_aud_legajo` (`tipo_actor`,`id_legajo_actor`,`created_at`),
  KEY `idx_com_aud_categoria` (`actor_categoria`,`created_at`),
  KEY `idx_com_aud_accion` (`accion`,`created_at`),
  KEY `com_auditoria_id_hilo_index` (`id_hilo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL sp_add_column_if_missing(
    'com_auditoria',
    'mensaje_remitente_snapshot',
    'varchar(200) DEFAULT NULL AFTER `mensaje_fecha_snapshot`'
);
CALL sp_add_column_if_missing(
    'com_auditoria',
    'mensaje_destinatario_snapshot',
    'text DEFAULT NULL AFTER `mensaje_remitente_snapshot`'
);

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(43, 43, 'COMUNICACIONES', 'Auditoría de comunicación institucional: consultar borrados y marcas de lectura en bandejas.')
ON DUPLICATE KEY UPDATE
  `orden` = VALUES(`orden`),
  `tema` = VALUES(`tema`),
  `descripcion` = VALUES(`descripcion`);

-- =============================================================================
-- 9. Inasistencias docentes
-- =============================================================================
CREATE TABLE IF NOT EXISTS `cargos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cargo` varchar(50) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cargosxprofesor` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idCargos` int unsigned NOT NULL,
  `idProfesores` int unsigned NOT NULL,
  `dniProfesor` int unsigned NOT NULL DEFAULT 0,
  `idNiveles` int unsigned NOT NULL DEFAULT 0,
  `cant` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL sp_add_column_if_missing('cargosxprofesor', 'dniProfesor', 'INT UNSIGNED NOT NULL DEFAULT 0');
CALL sp_add_column_if_missing('cargosxprofesor', 'idNiveles', 'INT UNSIGNED NOT NULL DEFAULT 0');
CALL sp_add_column_if_missing('cargosxprofesor', 'cant', 'INT UNSIGNED NOT NULL DEFAULT 0');

-- Sin AFTER: evita error si la columna ancla no existe aún (orden físico irrelevante)
CALL sp_add_column_if_missing('inasdocentes', 'dniProfesor', 'INT UNSIGNED NOT NULL DEFAULT 0');
CALL sp_add_column_if_missing('inasdocentes', 'idNivel', 'INT UNSIGNED NOT NULL DEFAULT 0');
CALL sp_add_column_if_missing('inasdocentes', 'idCargosXProfesor', 'INT UNSIGNED NOT NULL DEFAULT 0');

CREATE TABLE IF NOT EXISTS `inasdocentes_detalle` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idInasDocentes` int unsigned NOT NULL,
  `idMaterias` int unsigned NOT NULL,
  `idCursos` int unsigned NOT NULL,
  `cantidad` decimal(5,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `inasdocentes_detalle_idinasdocentes_index` (`idInasDocentes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

DROP PROCEDURE IF EXISTS sp_add_column_if_missing;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error por tablas/columnas existentes.
-- =============================================================================
