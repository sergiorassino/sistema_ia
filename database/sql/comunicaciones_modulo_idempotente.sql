-- =============================================================================
-- Módulo Comunicación institucional — solo tablas com_*
-- Idempotente (re-ejecutable). MySQL 5.7+ / MariaDB 10.x
-- Revisar backup antes de aplicar en producción.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP PROCEDURE IF EXISTS sp_add_column_if_missing;
DELIMITER $$
CREATE PROCEDURE sp_add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
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
  `docentes_permite_respuestas` tinyint(1) DEFAULT NULL,
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

CALL sp_add_column_if_missing('com_hilos', 'cursos_envio', 'json DEFAULT NULL AFTER `id_curso`');
CALL sp_add_column_if_missing('com_hilos', 'familia_puede_responder', 'tinyint(1) NOT NULL DEFAULT 1 AFTER `estado`');
CALL sp_add_column_if_missing('com_hilos', 'docentes_permite_respuestas', 'tinyint(1) DEFAULT NULL AFTER `familia_puede_responder`');

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

CALL sp_add_column_if_missing('com_preferencias', 'vinculos_contacto', 'json DEFAULT NULL AFTER `vinculo_contacto`');

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

UPDATE `com_canales` c
SET c.`id_nivel` = (SELECT MIN(n.`id`) FROM `niveles` n)
WHERE c.`id_nivel` IS NULL;

CALL sp_drop_index_if_exists('com_canales', 'uq_canal_par');

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

-- -----------------------------------------------------------------------------
-- Canales por nivel: INSERT IGNORE evita el conflicto de parser
-- "CROSS JOIN ... ) tpl ON DUPLICATE" (el ON se lee como JOIN).
-- Si ya existen filas, no se actualizan; ejecutar de nuevo es seguro.
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `com_canales` (
  `id_nivel`, `rol_emisor`, `rol_receptor`,
  `puede_iniciar`, `puede_responder`, `medios_permitidos`, `activo`,
  `created_at`, `updated_at`
)
SELECT
  n.`id`,
  seed.`rol_emisor`,
  seed.`rol_receptor`,
  seed.`puede_iniciar`,
  seed.`puede_responder`,
  seed.`medios_permitidos`,
  1,
  CURRENT_TIMESTAMP,
  CURRENT_TIMESTAMP
FROM `niveles` n
CROSS JOIN (
  SELECT 'familia' AS rol_emisor, 'preceptor' AS rol_receptor, 1 AS puede_iniciar, 1 AS puede_responder, '["push","email","whatsapp"]' AS medios_permitidos
  UNION ALL SELECT 'familia','profesor',0,1,'["push","email"]'
  UNION ALL SELECT 'familia','directivo',1,1,'["push","email"]'
  UNION ALL SELECT 'profesor','familia',1,0,'["push","email"]'
  UNION ALL SELECT 'preceptor','familia',1,1,'["push","email","whatsapp"]'
  UNION ALL SELECT 'directivo','familia',1,1,'["push","email","whatsapp"]'
  UNION ALL SELECT 'preceptor','profesor',1,1,'["push","email"]'
  UNION ALL SELECT 'profesor','profesor',1,1,'["push","email"]'
  UNION ALL SELECT 'profesor','preceptor',1,1,'["push","email"]'
  UNION ALL SELECT 'profesor','directivo',1,1,'["push","email"]'
  UNION ALL SELECT 'preceptor','preceptor',1,1,'["push","email","whatsapp"]'
  UNION ALL SELECT 'preceptor','directivo',1,1,'["push","email","whatsapp"]'
  UNION ALL SELECT 'directivo','profesor',1,1,'["push","email"]'
  UNION ALL SELECT 'directivo','preceptor',1,1,'["push","email","whatsapp"]'
  UNION ALL SELECT 'directivo','directivo',1,1,'["push","email","whatsapp"]'
) seed;

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(4,  3,  'COMUNICACIONES',         'Ver la bandeja de comunicados y los hilos de conversación.'),
(5,  4,  'COMUNICACIONES',         'Iniciar nuevos comunicados hacia familias.'),
(6,  5,  'COMUNICACIONES - CONFIG','Administrar la configuración de canales (quién puede comunicarse con quién y por qué medios).'),
(7,  6,  'COMUNICACIONES',         'Borrar mensajes propios en un hilo.'),
(8,  7,  'COMUNICACIONES',         'Borrar mensajes de otros participantes en un hilo.'),
(9,  8,  'COMUNICACIONES',         'Acceder a la bandeja de revisión de comunicados.'),
(43, 43, 'COMUNICACIONES',         'Auditoría de comunicación institucional: consultar borrados y marcas de lectura en bandejas.')
ON DUPLICATE KEY UPDATE
  `orden` = VALUES(`orden`),
  `tema` = VALUES(`tema`),
  `descripcion` = VALUES(`descripcion`);

SET FOREIGN_KEY_CHECKS = 1;

DROP PROCEDURE IF EXISTS sp_add_column_if_missing;
DROP PROCEDURE IF EXISTS sp_drop_index_if_exists;
