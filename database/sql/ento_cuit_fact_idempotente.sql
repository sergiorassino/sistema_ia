-- =============================================================================
-- Tabla `ento` — CUIT de facturación AFIP (puede diferir del CUIT institucional)
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_07_08_120000_add_cuit_fact_to_ento_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- Columna: cuitFact varchar(13) — debajo de cuit
--
-- ADVERTENCIA: solo agrega la columna si falta. No altera tipos ni datos existentes.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'cuitFact'
);
SET @ddl := IF(
  @add = 0,
  'ALTER TABLE `ento` ADD COLUMN `cuitFact` varchar(13) NULL DEFAULT NULL AFTER `cuit`',
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
