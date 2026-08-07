-- =============================================================================
-- Tabla `sanciones` — flag legacy `publicada` (TINYINT default 1)
--
-- Uso preferido: php artisan migrate
--   (migración 2026_08_04_110000_add_publicada_to_sanciones_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- Evita el error de migrate cuando `comunicadaPadres` se agrega AFTER `publicada`
-- y el tenant no tenía esa columna.
--
-- ADVERTENCIA: solo agrega la columna si falta. Filas existentes quedan en 1.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sanciones' AND COLUMN_NAME = 'publicada'
);
SET @has_solipor := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sanciones' AND COLUMN_NAME = 'solipor'
);
SET @has_motivo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sanciones' AND COLUMN_NAME = 'motivo'
);
SET @ddl := IF(
  @add > 0,
  'SELECT 1',
  IF(
    @has_solipor > 0,
    'ALTER TABLE `sanciones` ADD COLUMN `publicada` TINYINT NOT NULL DEFAULT 1 AFTER `solipor`',
    IF(
      @has_motivo > 0,
      'ALTER TABLE `sanciones` ADD COLUMN `publicada` TINYINT NOT NULL DEFAULT 1 AFTER `motivo`',
      'ALTER TABLE `sanciones` ADD COLUMN `publicada` TINYINT NOT NULL DEFAULT 1'
    )
  )
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
