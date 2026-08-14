-- =============================================================================
-- Habilitar upload de foto carnet (mismo criterio que Caixal SF)
--
-- Requisitos para que el ABM de legajos y Actualización de datos personales
-- muestren el upload:
--   1. Columna legajos.fotoCarnet (VARCHAR 255)
--   2. Solapa `foto_carnet` en solapas_legajo
--   3. campos_legajo.fotoCarnet con solapa_legajo_id asignado
--
-- Uso: ejecutar en la BD del tenant (ej. ia_sanjose) en phpMyAdmin / HeidiSQL.
-- Equivalente de columna: database/sql/legajos_foto_carnet_idempotente.sql
--
-- ADVERTENCIA: inserta/actualiza parametrización de solapas y campos.
-- No borra fotos ni cambia datos de legajos. Si fotoCarnet ya está en otra
-- solapa, no la mueve.
-- =============================================================================

SET NAMES utf8mb4;

-- 1) Columna en legajos (no-op si ya existe)
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

-- 2) Solapa «Foto Carnet» (slug foto_carnet), al final del orden
SET @hay_solapa := (SELECT COUNT(*) FROM `solapas_legajo` WHERE `slug` = 'foto_carnet');
SET @max_orden_solapa := (SELECT IFNULL(MAX(`orden`), 0) FROM `solapas_legajo`);
SET @ins_solapa := IF(
  @hay_solapa = 0,
  CONCAT(
    'INSERT INTO `solapas_legajo` (`nombre`, `slug`, `orden`) VALUES (',
    '''Foto Carnet'', ''foto_carnet'', ',
    (@max_orden_solapa + 1),
    ')'
  ),
  'SELECT 1'
);
PREPARE s FROM @ins_solapa; EXECUTE s; DEALLOCATE PREPARE s;

-- 3) Campo en parametrización, asignado a esa solapa
SET @id_solapa := (SELECT `id` FROM `solapas_legajo` WHERE `slug` = 'foto_carnet' LIMIT 1);
SET @orden_col := (
  SELECT `ORDINAL_POSITION` FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legajos' AND COLUMN_NAME = 'fotoCarnet'
);
SET @hay_campo := (SELECT COUNT(*) FROM `campos_legajo` WHERE `columna` = 'fotoCarnet');
SET @ins_campo := IF(
  @id_solapa IS NOT NULL AND @hay_campo = 0,
  CONCAT(
    'INSERT INTO `campos_legajo` (`columna`, `etiqueta`, `visible_listado`, `orden`, `solapa_legajo_id`, `orden_en_solapa`) VALUES (',
    '''fotoCarnet'', ''Foto Carnet'', 1, ',
    IFNULL(@orden_col, 0), ', ',
    @id_solapa, ', 1)'
  ),
  'SELECT 1'
);
PREPARE s FROM @ins_campo; EXECUTE s; DEALLOCATE PREPARE s;

-- Si el campo ya existía sin solapa (p. ej. tras sincronizar esquema), asignarlo
UPDATE `campos_legajo`
SET
  `solapa_legajo_id` = @id_solapa,
  `orden_en_solapa` = IF(`orden_en_solapa` = 0, 1, `orden_en_solapa`),
  `etiqueta` = IF(`etiqueta` IS NULL OR `etiqueta` = '', 'Foto Carnet', `etiqueta`)
WHERE `columna` = 'fotoCarnet'
  AND `solapa_legajo_id` IS NULL
  AND @id_solapa IS NOT NULL;

-- Verificación:
-- SELECT s.id, s.nombre, s.slug, s.orden
-- FROM solapas_legajo s WHERE s.slug = 'foto_carnet';
-- SELECT c.columna, c.etiqueta, c.visible_listado, c.solapa_legajo_id, c.orden_en_solapa
-- FROM campos_legajo c WHERE c.columna = 'fotoCarnet';
-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
