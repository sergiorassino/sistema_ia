-- =============================================================================
-- Tabla `ento` — visibilidad en Menú de Alumnos de Libre Deuda.
--
-- Un flag por nivel (`idNivel`): verLibreDeuda = 1 muestra el ítem (si el
-- tenant tiene el módulo y Áulica); 0 lo oculta.
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_09_15_120000_add_ento_ver_libre_deuda_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- ADVERTENCIA: solo agrega la columna si falta. Default 1 (visible).
-- Para ocultar en un nivel: UPDATE ento SET verLibreDeuda = 0 WHERE idNivel = N;
-- o desmarcar el checkbox en Parametrización → Parámetros (nivel activo).
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'verLibreDeuda'
);
SET @after := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'verDatosFicha'
);
SET @ddl := IF(
  @add = 0,
  IF(
    @after > 0,
    'ALTER TABLE `ento` ADD COLUMN `verLibreDeuda` tinyint(1) NOT NULL DEFAULT 1 AFTER `verDatosFicha`',
    'ALTER TABLE `ento` ADD COLUMN `verLibreDeuda` tinyint(1) NOT NULL DEFAULT 1'
  ),
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
