-- Proyectos extracurriculares — tablas nuevas (aditivas).
-- Equivalente: database/migrations/2026_08_27_100000_create_ext_proyectos_extracurriculares_tables.php
-- Revisar antes de ejecutar. No modifica tablas legacy.

CREATE TABLE IF NOT EXISTS `ext_tipo_registro` (
  `id` INT UNSIGNED NOT NULL,
  `nombre` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `ext_tipo_registro` (`id`, `nombre`) VALUES
(1, 'Actividad Extraprogramática')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

CREATE TABLE IF NOT EXISTS `ext_actividades` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_tipo_registro` INT UNSIGNED NOT NULL DEFAULT 1,
  `id_nivel` INT UNSIGNED NOT NULL,
  `id_terlec` INT UNSIGNED NOT NULL,
  `id_profesor_proponente` INT UNSIGNED NOT NULL,
  `nombre` VARCHAR(255) NOT NULL,
  `lugar` VARCHAR(255) NULL,
  `horario` VARCHAR(255) NULL,
  `descripcion` TEXT NULL,
  `evaluacion` TEXT NULL,
  `tipo_grupo` VARCHAR(20) NOT NULL DEFAULT 'cursos',
  `estado` VARCHAR(20) NOT NULL DEFAULT 'pendiente',
  `aprobado_por` INT UNSIGNED NULL,
  `aprobado_at` DATETIME NULL,
  `comunicado_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ext_act_ctx_estado` (`id_nivel`, `id_terlec`, `estado`),
  KEY `idx_ext_act_prop` (`id_profesor_proponente`, `id_nivel`, `id_terlec`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ext_fechas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_actividad` BIGINT UNSIGNED NOT NULL,
  `fecha` DATE NOT NULL,
  `hora_inicio` TIME NULL,
  `hora_fin` TIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ext_fechas_act_fecha` (`id_actividad`, `fecha`),
  KEY `idx_ext_fechas_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ext_actividad_cursos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_actividad` BIGINT UNSIGNED NOT NULL,
  `id_curso` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ext_act_curso` (`id_actividad`, `id_curso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ext_actividad_alumnos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_actividad` BIGINT UNSIGNED NOT NULL,
  `id_legajo` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ext_act_alumno` (`id_actividad`, `id_legajo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ext_actividad_docentes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_actividad` BIGINT UNSIGNED NOT NULL,
  `id_profesor` INT UNSIGNED NOT NULL,
  `rol` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ext_act_doc_rol` (`id_actividad`, `id_profesor`, `rol`),
  KEY `idx_ext_act_doc_rol` (`id_actividad`, `rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verificación:
-- SHOW TABLES LIKE 'ext_%';
-- SELECT * FROM ext_tipo_registro;
