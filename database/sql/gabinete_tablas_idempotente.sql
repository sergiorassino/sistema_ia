-- =============================================================================
-- Seguimiento de gabinete de orientación — tablas para colegios que no las tienen.
--
-- Crea (si faltan):
--   gabinetetipo  — catálogo de tipos
--   gabinete      — registros de seguimiento (idTipoSancion apunta a gabinetetipo)
--   gabinetemarca — semáforo por legajo (0 sin marca, 1 verde, 2 amarillo, 3 rojo)
--
-- Si la tabla ya existe, no la recrea. Solo agrega columnas/índices faltantes.
-- No borra datos. No otorga el permiso 103 (ver permiso_ia_orden_103_seguimiento_gabinete.sql).
--
-- Equivalente Artisan: php artisan migrate
--   (2026_09_07_122000_create_gabinete_tables_if_missing + gabinetemarca ya existente)
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- gabinetetipo
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gabinetetipo` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tipo` VARCHAR(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db := DATABASE();
SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinetetipo' AND COLUMN_NAME = 'tipo'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `gabinetetipo` ADD COLUMN `tipo` VARCHAR(100) NOT NULL DEFAULT ''''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- gabinete
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gabinete` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `idMatricula` INT NOT NULL,
  `idTipoSancion` INT NOT NULL,
  `fecha` DATE DEFAULT NULL,
  `cantidad` INT DEFAULT NULL,
  `solipor` VARCHAR(500) DEFAULT NULL,
  `motivo` TEXT,
  `asistentes` TEXT,
  `conclusion` TEXT,
  PRIMARY KEY (`id`),
  KEY `idx_gabinete_matricula` (`idMatricula`),
  KEY `idx_gabinete_tipo` (`idTipoSancion`),
  KEY `idx_gabinete_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Columnas si la tabla ya existía incompleta
SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinete' AND COLUMN_NAME = 'idMatricula'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `gabinete` ADD COLUMN `idMatricula` INT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinete' AND COLUMN_NAME = 'idTipoSancion'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `gabinete` ADD COLUMN `idTipoSancion` INT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinete' AND COLUMN_NAME = 'fecha'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `gabinete` ADD COLUMN `fecha` DATE DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinete' AND COLUMN_NAME = 'cantidad'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `gabinete` ADD COLUMN `cantidad` INT DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinete' AND COLUMN_NAME = 'solipor'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `gabinete` ADD COLUMN `solipor` VARCHAR(500) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinete' AND COLUMN_NAME = 'motivo'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `gabinete` ADD COLUMN `motivo` TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinete' AND COLUMN_NAME = 'asistentes'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `gabinete` ADD COLUMN `asistentes` TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinete' AND COLUMN_NAME = 'conclusion'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `gabinete` ADD COLUMN `conclusion` TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índices (no fallan si la tabla es nueva: el CREATE ya los tiene)
SET @tiene := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinete' AND INDEX_NAME = 'idx_gabinete_matricula'
);
SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinete' AND COLUMN_NAME = 'idMatricula'
);
SET @sql := IF(@tiene = 0 AND @col > 0, 'ALTER TABLE `gabinete` ADD KEY `idx_gabinete_matricula` (`idMatricula`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinete' AND INDEX_NAME = 'idx_gabinete_tipo'
);
SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinete' AND COLUMN_NAME = 'idTipoSancion'
);
SET @sql := IF(@tiene = 0 AND @col > 0, 'ALTER TABLE `gabinete` ADD KEY `idx_gabinete_tipo` (`idTipoSancion`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinete' AND INDEX_NAME = 'idx_gabinete_fecha'
);
SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinete' AND COLUMN_NAME = 'fecha'
);
SET @sql := IF(@tiene = 0 AND @col > 0, 'ALTER TABLE `gabinete` ADD KEY `idx_gabinete_fecha` (`fecha`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- gabinetemarca (también en database/sql/gabinetemarca.sql)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gabinetemarca` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idLegajos` INT UNSIGNED NOT NULL,
  `color` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gabinetemarca_legajo` (`idLegajos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinetemarca' AND COLUMN_NAME = 'idLegajos'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `gabinetemarca` ADD COLUMN `idLegajos` INT UNSIGNED NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinetemarca' AND COLUMN_NAME = 'color'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `gabinetemarca` ADD COLUMN `color` TINYINT UNSIGNED NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinetemarca' AND INDEX_NAME = 'uk_gabinetemarca_legajo'
);
SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'gabinetemarca' AND COLUMN_NAME = 'idLegajos'
);
SET @sql := IF(@tiene = 0 AND @col > 0, 'ALTER TABLE `gabinetemarca` ADD UNIQUE KEY `uk_gabinetemarca_legajo` (`idLegajos`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Catálogo: cada colegio carga sus tipos en gabinetetipo (formulario Alta).
-- Sin filas en gabinetetipo el módulo abre pero no permite guardar un registro.

-- Verificación:
-- SHOW CREATE TABLE gabinetetipo;
-- SHOW CREATE TABLE gabinete;
-- SHOW CREATE TABLE gabinetemarca;
-- SELECT id, tipo FROM gabinetetipo ORDER BY tipo;
