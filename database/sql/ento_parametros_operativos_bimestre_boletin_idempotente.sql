-- =============================================================================
-- Tabla `ento` — bloqueo bimestre e impresión de boletines (parámetros operativos)
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_07_09_121500_add_ento_parametros_operativos_bimestre_boletin_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- Columnas: verBimesOff int(1), bimesOffMensaje varchar(300), imprBoleOff int(1)
--
-- ADVERTENCIA: solo agrega columnas que falten. No altera tipos ni datos existentes.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'verBimesOff'
);
SET @after := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'verOffMensaje'
);
SET @ddl := IF(
  @add = 0,
  IF(
    @after > 0,
    'ALTER TABLE `ento` ADD COLUMN `verBimesOff` tinyint(1) NOT NULL DEFAULT 0 AFTER `verOffMensaje`',
    'ALTER TABLE `ento` ADD COLUMN `verBimesOff` tinyint(1) NOT NULL DEFAULT 0'
  ),
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'bimesOffMensaje'
);
SET @after := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'verBimesOff'
);
SET @ddl := IF(
  @add = 0,
  IF(
    @after > 0,
    'ALTER TABLE `ento` ADD COLUMN `bimesOffMensaje` varchar(300) NULL DEFAULT NULL AFTER `verBimesOff`',
    'ALTER TABLE `ento` ADD COLUMN `bimesOffMensaje` varchar(300) NULL DEFAULT NULL'
  ),
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'imprBoleOff'
);
SET @after := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'bimesOffMensaje'
);
SET @ddl := IF(
  @add = 0,
  IF(
    @after > 0,
    'ALTER TABLE `ento` ADD COLUMN `imprBoleOff` tinyint(1) NOT NULL DEFAULT 0 AFTER `bimesOffMensaje`',
    'ALTER TABLE `ento` ADD COLUMN `imprBoleOff` tinyint(1) NOT NULL DEFAULT 0'
  ),
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
