-- =============================================================================
-- Tabla `ento` — visibilidad en Menú de Alumnos de:
--   · Actualización de Datos Personales
--   · Imprimir Ficha de Matrícula
--
-- Un solo flag por nivel (`idNivel`): verDatosFicha = 1 muestra ambas (si el
-- tenant tiene el módulo/variante); 0 las oculta.
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_08_06_120000_add_ento_ver_datos_ficha_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- ADVERTENCIA: solo agrega la columna si falta. Default 1 (visible).
-- Colegios que antes ocultaban por `niveles_deshabilitados` en config deben
-- poner verDatosFicha = 0 en los niveles correspondientes (ver bloque final).
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'verDatosFicha'
);
SET @after := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'imprBoleOff'
);
SET @ddl := IF(
  @add = 0,
  IF(
    @after > 0,
    'ALTER TABLE `ento` ADD COLUMN `verDatosFicha` tinyint(1) NOT NULL DEFAULT 1 AFTER `imprBoleOff`',
    'ALTER TABLE `ento` ADD COLUMN `verDatosFicha` tinyint(1) NOT NULL DEFAULT 1'
  ),
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- -----------------------------------------------------------------------------
-- Alineación opcional (revisar por tenant; no ejecutar a ciegas en todos):
-- EPQ (antes niveles_deshabilitados [2,3]):
--   UPDATE ento SET verDatosFicha = 0 WHERE idNivel IN (2, 3);
-- SFQ (antes niveles_deshabilitados [2]):
--   UPDATE ento SET verDatosFicha = 0 WHERE idNivel = 2;
-- -----------------------------------------------------------------------------

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error (salvo el UPDATE opcional).
-- =============================================================================
