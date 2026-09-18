-- =============================================================================
-- Tabla `ento` — textos del comunicado de bloqueo / desbloqueo de matrícula
--
-- Cuerpo de Notif. Bloqueo / Notif. Desbloqueo (comunicación institucional
-- y refuerzo por mail). Distinto de mensajeBloqPeda / mensajeBloqAdmi, que
-- son el cartel en autogestión (ficha y datos personales).
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_09_18_120000_add_ento_mensaje_com_bloq_desbloq_matricula_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- ADVERTENCIA: solo agrega las columnas si faltan. No pisa textos existentes.
-- =============================================================================

SET NAMES utf8mb4;

SET @add_bloq := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'mensajeComBloqMatricula'
);
SET @after_admi := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'mensajeBloqAdmi'
);
SET @ddl_bloq := IF(
  @add_bloq = 0,
  IF(
    @after_admi > 0,
    'ALTER TABLE `ento` ADD COLUMN `mensajeComBloqMatricula` varchar(2000) NULL AFTER `mensajeBloqAdmi`',
    'ALTER TABLE `ento` ADD COLUMN `mensajeComBloqMatricula` varchar(2000) NULL'
  ),
  'SELECT 1'
);
PREPARE s FROM @ddl_bloq; EXECUTE s; DEALLOCATE PREPARE s;

SET @add_desbloq := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'mensajeComDesbloqMatricula'
);
SET @after_com_bloq := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'mensajeComBloqMatricula'
);
SET @ddl_desbloq := IF(
  @add_desbloq = 0,
  IF(
    @after_com_bloq > 0,
    'ALTER TABLE `ento` ADD COLUMN `mensajeComDesbloqMatricula` varchar(2000) NULL AFTER `mensajeComBloqMatricula`',
    'ALTER TABLE `ento` ADD COLUMN `mensajeComDesbloqMatricula` varchar(2000) NULL'
  ),
  'SELECT 1'
);
PREPARE s FROM @ddl_desbloq; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
