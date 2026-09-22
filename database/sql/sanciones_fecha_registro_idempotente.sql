-- =============================================================================
-- Tabla `sanciones` — columna `fechaRegistro` (DATETIME NULL)
-- Momento en que se cargó el registro. Distinta de `fecha` (día del hecho).
-- Filas legacy quedan en NULL.
--
-- Uso preferido: php artisan migrate
--   (migración 2026_09_22_120000_add_fecha_registro_to_sanciones_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- ADVERTENCIA: solo agrega la columna si falta. No modifica datos.
-- Irreversible solo si luego se hace DROP COLUMN.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sanciones' AND COLUMN_NAME = 'fechaRegistro'
);
SET @has_fecha := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sanciones' AND COLUMN_NAME = 'fecha'
);
SET @has_tipo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sanciones' AND COLUMN_NAME = 'idTipoSancion'
);
SET @ddl := IF(
  @add > 0,
  'SELECT 1',
  IF(
    @has_fecha > 0,
    'ALTER TABLE `sanciones` ADD COLUMN `fechaRegistro` DATETIME NULL DEFAULT NULL AFTER `fecha`',
    IF(
      @has_tipo > 0,
      'ALTER TABLE `sanciones` ADD COLUMN `fechaRegistro` DATETIME NULL DEFAULT NULL AFTER `idTipoSancion`',
      'ALTER TABLE `sanciones` ADD COLUMN `fechaRegistro` DATETIME NULL DEFAULT NULL'
    )
  )
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
