-- =============================================================================
-- Tabla `ento` — condición IVA del emisor y aporte estatal (facturación AFIP)
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_07_04_130000_add_cond_iva_inst_aporte_estatal_to_ento_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- Columnas: condIvaInst varchar(40), aporteEstatal varchar(10) — debajo de cuit
--
-- ADVERTENCIA: solo agrega columnas que falten. No altera tipos ni datos existentes.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'condIvaInst'
);
SET @ddl := IF(
  @add = 0,
  'ALTER TABLE `ento` ADD COLUMN `condIvaInst` varchar(40) NULL DEFAULT NULL AFTER `cuit`',
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'aporteEstatal'
);
SET @after := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'condIvaInst'
);
SET @ddl := IF(
  @add = 0,
  IF(
    @after > 0,
    'ALTER TABLE `ento` ADD COLUMN `aporteEstatal` varchar(10) NULL DEFAULT NULL AFTER `condIvaInst`',
    'ALTER TABLE `ento` ADD COLUMN `aporteEstatal` varchar(10) NULL DEFAULT NULL AFTER `cuit`'
  ),
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
