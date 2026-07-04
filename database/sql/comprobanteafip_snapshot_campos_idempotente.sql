-- =============================================================================
-- Tabla `comprobanteafip` — snapshot de datos impresos al emitir (idempotente)
--
-- Uso preferido: php artisan migrate
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- Columnas nuevas:
--   telefonoInstitucion, aporteEstatal, cursoAlumno, docTipoAfip
-- Columna eliminada (sin uso en PDF ni AFIP):
--   domicilioAlumno
--
-- ADVERTENCIA: agrega columnas que falten y quita domicilioAlumno si existe.
-- Comprobantes ya emitidos conservan NULL en los campos nuevos; el PDF usa fallback legacy.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comprobanteafip' AND COLUMN_NAME = 'telefonoInstitucion'
);
SET @ddl := IF(@add = 0, 'ALTER TABLE `comprobanteafip` ADD COLUMN `telefonoInstitucion` varchar(40) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comprobanteafip' AND COLUMN_NAME = 'aporteEstatal'
);
SET @ddl := IF(@add = 0, 'ALTER TABLE `comprobanteafip` ADD COLUMN `aporteEstatal` varchar(80) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comprobanteafip' AND COLUMN_NAME = 'cursoAlumno'
);
SET @ddl := IF(@add = 0, 'ALTER TABLE `comprobanteafip` ADD COLUMN `cursoAlumno` varchar(120) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comprobanteafip' AND COLUMN_NAME = 'docTipoAfip'
);
SET @ddl := IF(@add = 0, 'ALTER TABLE `comprobanteafip` ADD COLUMN `docTipoAfip` smallint(5) unsigned NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @drop := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comprobanteafip' AND COLUMN_NAME = 'domicilioAlumno'
);
SET @ddl := IF(@drop > 0, 'ALTER TABLE `comprobanteafip` DROP COLUMN `domicilioAlumno`', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
