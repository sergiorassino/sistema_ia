-- =============================================================================
-- Tabla `ento` — observación libre para el impreso de factura AFIP
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_07_29_100000_add_obs_factura_to_ento_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- Columna: obsFactura TEXT — HTML/texto con párrafos para el comprobante
--
-- ADVERTENCIA: solo agrega la columna si falta. No altera tipos ni datos existentes.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'obsFactura'
);
SET @ddl := IF(
  @add = 0,
  'ALTER TABLE `ento` ADD COLUMN `obsFactura` TEXT NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
