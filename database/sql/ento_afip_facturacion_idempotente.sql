-- =============================================================================
-- Tabla `ento` — campos de facturación AFIP (idempotente, re-ejecutable)
-- Referencia: esquema legacy con columnas para emisión/consulta WSFE
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migraciones 2026_06_24_120000_add_afip_cert_fields_to_ento_table.php
--    y 2026_07_02_142000_add_ento_afip_facturacion_fields_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- Columnas:
--   condicionIva, ptoVta, afipCertCarpeta, afipCertKey, afipCertCrt,
--   ingresosBrutos, fechaInicioAct
--
-- ADVERTENCIA: solo agrega columnas que falten. No altera tipos ni datos existentes.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'condicionIva'
);
SET @ddl := IF(@add = 0, 'ALTER TABLE `ento` ADD COLUMN `condicionIva` varchar(50) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'ptoVta'
);
SET @ddl := IF(@add = 0, 'ALTER TABLE `ento` ADD COLUMN `ptoVta` int(5) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'afipCertCarpeta'
);
SET @ddl := IF(@add = 0, 'ALTER TABLE `ento` ADD COLUMN `afipCertCarpeta` varchar(40) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'afipCertKey'
);
SET @ddl := IF(@add = 0, 'ALTER TABLE `ento` ADD COLUMN `afipCertKey` varchar(120) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'afipCertCrt'
);
SET @ddl := IF(@add = 0, 'ALTER TABLE `ento` ADD COLUMN `afipCertCrt` varchar(120) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'ingresosBrutos'
);
SET @ddl := IF(@add = 0, 'ALTER TABLE `ento` ADD COLUMN `ingresosBrutos` varchar(10) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'fechaInicioAct'
);
SET @ddl := IF(@add = 0, 'ALTER TABLE `ento` ADD COLUMN `fechaInicioAct` varchar(15) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
