-- =============================================================================
-- Tabla `sanciones` — columna `idProfesores` (INT UNSIGNED default 0)
-- Quién registró la sanción (`profesores.id`). 0 = desconocido / filas legacy.
--
-- Uso preferido: php artisan migrate
--   (migración 2026_09_02_180000_add_id_profesores_to_sanciones_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- ADVERTENCIA: solo agrega la columna si falta. Filas existentes quedan en 0.
-- No modifica datos. Irreversible solo si luego se hace DROP COLUMN.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sanciones' AND COLUMN_NAME = 'idProfesores'
);
SET @has_tipo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sanciones' AND COLUMN_NAME = 'idTipoSancion'
);
SET @has_mat := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sanciones' AND COLUMN_NAME = 'idMatricula'
);
SET @ddl := IF(
  @add > 0,
  'SELECT 1',
  IF(
    @has_tipo > 0,
    'ALTER TABLE `sanciones` ADD COLUMN `idProfesores` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `idTipoSancion`',
    IF(
      @has_mat > 0,
      'ALTER TABLE `sanciones` ADD COLUMN `idProfesores` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `idMatricula`',
      'ALTER TABLE `sanciones` ADD COLUMN `idProfesores` INT UNSIGNED NOT NULL DEFAULT 0'
    )
  )
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
