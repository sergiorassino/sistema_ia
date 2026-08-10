-- =============================================================================
-- Tabla `sanciones` — columna `acta` (MEDIUMTEXT NULL, HTML del acta disciplinaria)
--
-- Uso preferido: php artisan migrate
--   (migración 2026_08_10_120000_add_acta_to_sanciones.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- ADVERTENCIA: solo agrega la columna si falta. No modifica filas existentes
-- (quedan NULL = sin acta). Irreversible solo si luego se hace DROP COLUMN.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sanciones' AND COLUMN_NAME = 'acta'
);
SET @has_motivo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sanciones' AND COLUMN_NAME = 'motivo'
);
SET @has_solipor := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sanciones' AND COLUMN_NAME = 'solipor'
);
SET @ddl := IF(
  @add > 0,
  'SELECT 1',
  IF(
    @has_motivo > 0,
    'ALTER TABLE `sanciones` ADD COLUMN `acta` MEDIUMTEXT NULL AFTER `motivo`',
    IF(
      @has_solipor > 0,
      'ALTER TABLE `sanciones` ADD COLUMN `acta` MEDIUMTEXT NULL AFTER `solipor`',
      'ALTER TABLE `sanciones` ADD COLUMN `acta` MEDIUMTEXT NULL'
    )
  )
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
