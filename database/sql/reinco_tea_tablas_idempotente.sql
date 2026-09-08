-- =============================================================================
-- Gestión de TEA por inasistencias — tablas para colegios que no las tienen.
--
-- Crea (si faltan):
--   reinco2025_tipo — catálogo de situaciones TEA (5 tipos, igual que Montecristo)
--   reinco2025      — registros TEA por matrícula (queda vacía al crearla)
--
-- Si la tabla ya existe, no la recrea. Solo agrega columnas faltantes.
-- No borra ni trunca reinco2025. No pisa filas ya presentes en reinco2025_tipo.
-- No otorga el permiso 85 (ver permiso_ia_orden_85_tea_estudiantes_gestion.sql).
--
-- Equivalente Artisan: php artisan migrate
--   (2026_09_08_100000_create_reinco_tea_tables_if_missing)
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- reinco2025_tipo
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reinco2025_tipo` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `orden` INT NOT NULL DEFAULT 0,
  `tipo` VARCHAR(80) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db := DATABASE();

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reinco2025_tipo' AND COLUMN_NAME = 'orden'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `reinco2025_tipo` ADD COLUMN `orden` INT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reinco2025_tipo' AND COLUMN_NAME = 'tipo'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `reinco2025_tipo` ADD COLUMN `tipo` VARCHAR(80) NOT NULL DEFAULT ''''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Catálogo Montecristo (ids 1–5). No actualiza filas que ya existan.
INSERT INTO `reinco2025_tipo` (`id`, `orden`, `tipo`) VALUES
(1, 1, 'Informe del Preceptor a la Familia (3 inas)'),
(2, 2, 'Citación Adultos Responsables (5 inas)'),
(3, 3, 'Acta Compromiso con Estudiante y Responsables (10 inas)'),
(4, 4, 'Informe de Situación de Riesgo y Definición de Acciones Pedag (20 inas)'),
(5, 5, 'Situación de TEA (más de 25 inas)')
ON DUPLICATE KEY UPDATE
    `id` = `id`;

-- -----------------------------------------------------------------------------
-- reinco2025 (vacía al crearla; no se trunca si ya hay datos)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reinco2025` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `idMatricula` INT NOT NULL,
  `idReinco_tipo` INT NOT NULL,
  `fecha` DATE NOT NULL,
  `obs` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reinco2025' AND COLUMN_NAME = 'idMatricula'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `reinco2025` ADD COLUMN `idMatricula` INT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reinco2025' AND COLUMN_NAME = 'idReinco_tipo'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `reinco2025` ADD COLUMN `idReinco_tipo` INT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reinco2025' AND COLUMN_NAME = 'fecha'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `reinco2025` ADD COLUMN `fecha` DATE DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reinco2025' AND COLUMN_NAME = 'obs'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `reinco2025` ADD COLUMN `obs` TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Verificación:
-- SHOW CREATE TABLE reinco2025_tipo;
-- SHOW CREATE TABLE reinco2025;
-- SELECT id, orden, tipo FROM reinco2025_tipo ORDER BY orden, id;
-- SELECT COUNT(*) FROM reinco2025;
