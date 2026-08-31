-- =============================================================================
-- Tabla `legajos` — destinatario de facturación AFIP
--
-- Uso preferido: php artisan migrate
--   (migración 2026_08_31_140000_add_resp_admi_to_legajos_if_missing.php)
--   o php artisan se:migrate-legacy --force
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- Columnas:
--   respAdmiNom VARCHAR(100) NULL — nombre y apellido
--   respAdmiDni VARCHAR(20) NULL  — DNI (7 a 11 dígitos)
--
-- ADVERTENCIA: solo agrega las columnas si faltan. No altera tipos ni datos
-- existentes. Alcance: tabla `legajos` del tenant conectado.
-- =============================================================================

SET NAMES utf8mb4;

SET @has_nom := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'legajos'
    AND COLUMN_NAME = 'respAdmiNom'
);
SET @after_emailtut := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'legajos'
    AND COLUMN_NAME = 'emailtut'
);
SET @after_dnitut := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'legajos'
    AND COLUMN_NAME = 'dnitut'
);
SET @ddl_nom := IF(
  @has_nom = 0,
  IF(
    @after_emailtut > 0,
    'ALTER TABLE `legajos` ADD COLUMN `respAdmiNom` VARCHAR(100) NULL DEFAULT NULL AFTER `emailtut`',
    IF(
      @after_dnitut > 0,
      'ALTER TABLE `legajos` ADD COLUMN `respAdmiNom` VARCHAR(100) NULL DEFAULT NULL AFTER `dnitut`',
      'ALTER TABLE `legajos` ADD COLUMN `respAdmiNom` VARCHAR(100) NULL DEFAULT NULL'
    )
  ),
  'SELECT 1'
);
PREPARE s_nom FROM @ddl_nom; EXECUTE s_nom; DEALLOCATE PREPARE s_nom;

SET @has_dni := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'legajos'
    AND COLUMN_NAME = 'respAdmiDni'
);
SET @after_nom := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'legajos'
    AND COLUMN_NAME = 'respAdmiNom'
);
SET @ddl_dni := IF(
  @has_dni = 0,
  IF(
    @after_nom > 0,
    'ALTER TABLE `legajos` ADD COLUMN `respAdmiDni` VARCHAR(20) NULL DEFAULT NULL AFTER `respAdmiNom`',
    'ALTER TABLE `legajos` ADD COLUMN `respAdmiDni` VARCHAR(20) NULL DEFAULT NULL'
  ),
  'SELECT 1'
);
PREPARE s_dni FROM @ddl_dni; EXECUTE s_dni; DEALLOCATE PREPARE s_dni;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
