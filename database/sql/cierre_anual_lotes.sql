-- Journal de cierre anual (secundario): lote por ejecución + snapshot por fila actualizada.
-- Equivalente: database/migrations/2026_08_31_170000_create_cierre_anual_lotes_tables.php
-- Revisar antes de ejecutar. No modifica tablas legacy.
-- Alcance: tablas nuevas. Irreversible solo si ya hay lotes cargados (DROP).

CREATE TABLE IF NOT EXISTS `cierre_anual_lotes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `operacion` VARCHAR(10) NOT NULL,
  `id_nivel` INT UNSIGNED NOT NULL,
  `id_terlec` INT UNSIGNED NOT NULL,
  `ano_lectivo` SMALLINT UNSIGNED NULL,
  `nivel_nombre` VARCHAR(80) NOT NULL DEFAULT '',
  `id_profesor` INT UNSIGNED NOT NULL DEFAULT 0,
  `nombre_profesor` VARCHAR(150) NOT NULL DEFAULT '',
  `procesados` INT UNSIGNED NOT NULL DEFAULT 0,
  `aprobados` INT UNSIGNED NOT NULL DEFAULT 0,
  `previas` INT UNSIGNED NOT NULL DEFAULT 0,
  `omitidos` INT UNSIGNED NOT NULL DEFAULT 0,
  `actualizados` INT UNSIGNED NOT NULL DEFAULT 0,
  `estado` VARCHAR(20) NOT NULL DEFAULT 'aplicado',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revertido_at` TIMESTAMP NULL,
  `id_profesor_reverso` INT UNSIGNED NULL,
  `nombre_profesor_reverso` VARCHAR(150) NULL,
  `revertidos_ok` INT UNSIGNED NOT NULL DEFAULT 0,
  `revertidos_omitidos` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_cierre_lotes_ctx` (`id_nivel`, `id_terlec`, `created_at`),
  KEY `idx_cierre_lotes_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cierre_anual_lote_filas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_lote` BIGINT UNSIGNED NOT NULL,
  `id_calificacion` INT UNSIGNED NOT NULL,
  `id_legajos` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_matricula` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_materias` INT UNSIGNED NOT NULL DEFAULT 0,
  `apellido` VARCHAR(100) NOT NULL DEFAULT '',
  `nombre` VARCHAR(100) NOT NULL DEFAULT '',
  `dni` VARCHAR(20) NOT NULL DEFAULT '',
  `materia` VARCHAR(150) NOT NULL DEFAULT '',
  `curso` VARCHAR(80) NOT NULL DEFAULT '',
  `tipo` VARCHAR(10) NOT NULL,
  `apro_antes` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `calif_antes` VARCHAR(20) NOT NULL DEFAULT '',
  `mes_antes` SMALLINT UNSIGNED NULL,
  `ano_antes` SMALLINT UNSIGNED NULL,
  `cond_antes` VARCHAR(20) NOT NULL DEFAULT '',
  `escuapro_antes` VARCHAR(100) NOT NULL DEFAULT '',
  `cond_adeuda_antes` VARCHAR(20) NULL,
  `inscri_antes` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `apro_despues` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `calif_despues` VARCHAR(20) NOT NULL DEFAULT '',
  `mes_despues` SMALLINT UNSIGNED NULL,
  `ano_despues` SMALLINT UNSIGNED NULL,
  `cond_despues` VARCHAR(20) NOT NULL DEFAULT '',
  `escuapro_despues` VARCHAR(100) NOT NULL DEFAULT '',
  `cond_adeuda_despues` VARCHAR(20) NULL,
  `inscri_despues` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `revertida_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cierre_filas_lote` (`id_lote`),
  KEY `idx_cierre_filas_calif` (`id_calificacion`),
  KEY `idx_cierre_filas_legajo` (`id_legajos`),
  KEY `idx_cierre_filas_lote_tipo` (`id_lote`, `tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verificación:
-- SHOW TABLES LIKE 'cierre_anual_%';
-- DESCRIBE cierre_anual_lotes;
-- DESCRIBE cierre_anual_lote_filas;
