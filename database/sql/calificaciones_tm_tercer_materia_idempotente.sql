-- =============================================================================
-- Tabla `calificaciones` — columnas de tercer materia (condición TM)
--
-- El módulo Gestión de tercer materia lee y escribe:
--   tm1, tm2, tm3, tm4, tm5, tm6, tmNota
-- VARCHAR(20) NULL: el sistema admite notas 1–10, «a», «Aprob» y «Reprob»,
-- y rechaza valores de más de 20 caracteres.
--
-- También exige que ya existan `apro` y `condAdeuda` (no se crean acá).
--
-- Ejecutar en la base de 25 de Mayo (ia_25demayo).
-- Revisar backup antes de aplicar. ALTER TABLE puede bloquear `calificaciones`.
--
-- ADVERTENCIA: solo agrega cada columna si falta. No modifica datos existentes.
-- Irreversible solo si luego se hace DROP COLUMN.
-- =============================================================================

SET NAMES utf8mb4;

-- Diagnóstico (no altera nada):
-- SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
-- FROM INFORMATION_SCHEMA.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME = 'calificaciones'
--   AND COLUMN_NAME IN ('apro', 'condAdeuda', 'tm1', 'tm2', 'tm3', 'tm4', 'tm5', 'tm6', 'tmNota')
-- ORDER BY FIELD(COLUMN_NAME, 'apro', 'condAdeuda', 'tm1', 'tm2', 'tm3', 'tm4', 'tm5', 'tm6', 'tmNota');

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calificaciones' AND COLUMN_NAME = 'tm1'
);
SET @ddl := IF(
  @add = 0,
  'ALTER TABLE `calificaciones` ADD COLUMN `tm1` VARCHAR(20) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calificaciones' AND COLUMN_NAME = 'tm2'
);
SET @ddl := IF(
  @add = 0,
  'ALTER TABLE `calificaciones` ADD COLUMN `tm2` VARCHAR(20) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calificaciones' AND COLUMN_NAME = 'tm3'
);
SET @ddl := IF(
  @add = 0,
  'ALTER TABLE `calificaciones` ADD COLUMN `tm3` VARCHAR(20) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calificaciones' AND COLUMN_NAME = 'tm4'
);
SET @ddl := IF(
  @add = 0,
  'ALTER TABLE `calificaciones` ADD COLUMN `tm4` VARCHAR(20) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calificaciones' AND COLUMN_NAME = 'tm5'
);
SET @ddl := IF(
  @add = 0,
  'ALTER TABLE `calificaciones` ADD COLUMN `tm5` VARCHAR(20) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calificaciones' AND COLUMN_NAME = 'tm6'
);
SET @ddl := IF(
  @add = 0,
  'ALTER TABLE `calificaciones` ADD COLUMN `tm6` VARCHAR(20) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calificaciones' AND COLUMN_NAME = 'tmNota'
);
SET @ddl := IF(
  @add = 0,
  'ALTER TABLE `calificaciones` ADD COLUMN `tmNota` VARCHAR(20) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
