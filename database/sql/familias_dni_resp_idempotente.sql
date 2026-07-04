-- =============================================================================
-- Tabla `familias` — DNI del responsable económico (facturación AFIP)
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_07_04_140000_add_dni_resp_to_familias_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- Columna: dniResp varchar(20) NULL — debajo de responsable
--
-- ADVERTENCIA: solo agrega la columna si falta. No altera datos existentes.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'familias'
    AND COLUMN_NAME = 'dniResp'
);
SET @after := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'familias'
    AND COLUMN_NAME = 'responsable'
);
SET @ddl := IF(
  @add = 0,
  IF(
    @after > 0,
    'ALTER TABLE `familias` ADD COLUMN `dniResp` varchar(20) NULL DEFAULT NULL AFTER `responsable`',
    'ALTER TABLE `familias` ADD COLUMN `dniResp` varchar(20) NULL DEFAULT NULL'
  ),
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
