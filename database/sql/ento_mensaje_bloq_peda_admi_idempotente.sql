-- =============================================================================
-- Tabla `ento` — mensajes de bloqueo pedagógico / administrativo
--
-- Se muestran en el Menú de Alumnos cuando la matrícula del ciclo de
-- autogestión tiene bloqmatr y/o bloqadmi: impide entrar a
-- Actualización de Datos Personales e Imprimir Ficha de Matrícula.
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_08_13_090000_add_ento_mensaje_bloq_peda_admi_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- ADVERTENCIA: solo agrega las columnas si faltan. No pisa textos existentes.
-- =============================================================================

SET NAMES utf8mb4;

SET @add_peda := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'mensajeBloqPeda'
);
SET @after_ficha := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'verDatosFicha'
);
SET @ddl_peda := IF(
  @add_peda = 0,
  IF(
    @after_ficha > 0,
    'ALTER TABLE `ento` ADD COLUMN `mensajeBloqPeda` varchar(500) NULL AFTER `verDatosFicha`',
    'ALTER TABLE `ento` ADD COLUMN `mensajeBloqPeda` varchar(500) NULL'
  ),
  'SELECT 1'
);
PREPARE s FROM @ddl_peda; EXECUTE s; DEALLOCATE PREPARE s;

SET @add_admi := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'mensajeBloqAdmi'
);
SET @after_peda := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'mensajeBloqPeda'
);
SET @ddl_admi := IF(
  @add_admi = 0,
  IF(
    @after_peda > 0,
    'ALTER TABLE `ento` ADD COLUMN `mensajeBloqAdmi` varchar(500) NULL AFTER `mensajeBloqPeda`',
    'ALTER TABLE `ento` ADD COLUMN `mensajeBloqAdmi` varchar(500) NULL'
  ),
  'SELECT 1'
);
PREPARE s FROM @ddl_admi; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
