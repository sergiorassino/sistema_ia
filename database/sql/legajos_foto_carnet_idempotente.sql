-- =============================================================================
-- Tabla `legajos` — ruta relativa de la foto carnet del estudiante
--
-- Uso preferido: php artisan migrate
--   (migración 2026_08_05_160000_add_foto_carnet_to_legajos_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- Columna: fotoCarnet VARCHAR(255) — path relativo en disco privado del tenant
--
-- ADVERTENCIA: solo agrega la columna si falta. No altera tipos ni datos existentes.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legajos' AND COLUMN_NAME = 'fotoCarnet'
);
SET @ddl := IF(
  @add = 0,
  'ALTER TABLE `legajos` ADD COLUMN `fotoCarnet` VARCHAR(255) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
