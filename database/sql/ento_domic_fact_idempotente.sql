-- =============================================================================
-- Tabla `ento` — domicilio fiscal del emisor para facturación AFIP
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_07_08_100000_add_domic_fact_to_ento_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- Columna: domicFact varchar(100) — debajo de cuit
--
-- ADVERTENCIA: solo agrega la columna si falta. No altera tipos ni datos existentes.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'domicFact'
);
SET @ddl := IF(
  @add = 0,
  'ALTER TABLE `ento` ADD COLUMN `domicFact` varchar(100) NULL DEFAULT NULL AFTER `cuit`',
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
