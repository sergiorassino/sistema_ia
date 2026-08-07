-- =============================================================================
-- Tabla `ento` — código de establecimiento educativo (EE)
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_08_07_143000_add_ee_to_ento_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- Columna: ee varchar(30) — debajo de cue (si existe)
--
-- ADVERTENCIA: solo agrega la columna si falta. No altera tipos ni datos
-- existentes. Aplicable a todos los tenants (p. ej. NSSC).
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'ee'
);
SET @after := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'cue'
);
SET @ddl := IF(
  @add = 0,
  IF(
    @after > 0,
    'ALTER TABLE `ento` ADD COLUMN `ee` varchar(30) NULL DEFAULT NULL AFTER `cue`',
    'ALTER TABLE `ento` ADD COLUMN `ee` varchar(30) NULL DEFAULT NULL'
  ),
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
