-- =============================================================================
-- Columnas `profesores.art28` y `profesores.fichaIncompat` — creación idempotente
-- Referencia art28: ia_iess — varchar(50) NULL (después de incapac).
-- fichaIncompat: varchar(50) NULL (después de art28).
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_08_13_160000_add_art28_and_ficha_incompat_to_profesores_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
-- Compatible con MySQL 5.7+ / MariaDB 10.x
-- Sin DELIMITER ni procedimientos almacenados (ejecutable en un solo lote).
--
-- Verificar antes:
--   SHOW COLUMNS FROM profesores LIKE 'art28';
--   SHOW COLUMNS FROM profesores LIKE 'fichaIncompat';
--
-- ADVERTENCIA: solo agrega las columnas si faltan. No modifica datos existentes.
-- =============================================================================

SET NAMES utf8mb4;

-- --- art28 ------------------------------------------------------------------
SET @has_art28 := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'profesores'
    AND COLUMN_NAME = 'art28'
);

SET @has_incapac := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'profesores'
    AND COLUMN_NAME = 'incapac'
);

SET @ddl_art28 := IF(
  @has_art28 > 0,
  'SELECT 1',
  IF(
    @has_incapac > 0,
    'ALTER TABLE `profesores` ADD COLUMN `art28` varchar(50) DEFAULT NULL AFTER `incapac`',
    'ALTER TABLE `profesores` ADD COLUMN `art28` varchar(50) DEFAULT NULL'
  )
);

PREPARE stmt_art28 FROM @ddl_art28;
EXECUTE stmt_art28;
DEALLOCATE PREPARE stmt_art28;

-- --- fichaIncompat ----------------------------------------------------------
SET @has_ficha_incompat := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'profesores'
    AND COLUMN_NAME = 'fichaIncompat'
);

SET @has_art28_after := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'profesores'
    AND COLUMN_NAME = 'art28'
);

SET @has_incapac_after := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'profesores'
    AND COLUMN_NAME = 'incapac'
);

SET @ddl_ficha_incompat := IF(
  @has_ficha_incompat > 0,
  'SELECT 1',
  IF(
    @has_art28_after > 0,
    'ALTER TABLE `profesores` ADD COLUMN `fichaIncompat` varchar(50) DEFAULT NULL AFTER `art28`',
    IF(
      @has_incapac_after > 0,
      'ALTER TABLE `profesores` ADD COLUMN `fichaIncompat` varchar(50) DEFAULT NULL AFTER `incapac`',
      'ALTER TABLE `profesores` ADD COLUMN `fichaIncompat` varchar(50) DEFAULT NULL'
    )
  )
);

PREPARE stmt_ficha_incompat FROM @ddl_ficha_incompat;
EXECUTE stmt_ficha_incompat;
DEALLOCATE PREPARE stmt_ficha_incompat;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
