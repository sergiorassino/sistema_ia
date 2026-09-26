-- =============================================================================
-- Tabla `ext_actividades` — presupuesto de la actividad
--
-- Uso preferido: php artisan migrate
--   (migración 2026_09_26_140000_add_presupuesto_to_ext_actividades.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- Columna: presupuesto TEXT NULL
--
-- ADVERTENCIA: solo agrega la columna si falta. No altera ni borra datos existentes.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ext_actividades' AND COLUMN_NAME = 'presupuesto'
);
SET @ddl := IF(
  @add = 0,
  'ALTER TABLE `ext_actividades` ADD COLUMN `presupuesto` TEXT NULL AFTER `evaluacion`',
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
