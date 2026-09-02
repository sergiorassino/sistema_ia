-- =============================================================================
-- Tabla `sanciontipo` — columna `enResumenComunicado` (TINYINT NOT NULL DEFAULT 0)
--
-- 1 = el tipo aparece en «Hasta la fecha registra un total de» del comunicado PDF.
-- 0 = no aparece (p. ej. Registro de Situación Áulica u otros tipos internos).
--
-- Uso preferido: php artisan migrate
--   (migración 2026_09_02_170000_add_en_resumen_comunicado_to_sanciontipo.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- ADVERTENCIA: solo agrega la columna si falta. Las filas existentes quedan en 0
-- (hay que marcar los tipos en Parametrización → Tipos de sanción).
-- Irreversible solo si luego se hace DROP COLUMN.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sanciontipo'
    AND COLUMN_NAME = 'enResumenComunicado'
);
SET @has_permite := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sanciontipo'
    AND COLUMN_NAME = 'permiteNotifPadres'
);
SET @ddl := IF(
  @add > 0,
  'SELECT 1',
  IF(
    @has_permite > 0,
    'ALTER TABLE `sanciontipo` ADD COLUMN `enResumenComunicado` TINYINT NOT NULL DEFAULT 0 AFTER `permiteNotifPadres`',
    'ALTER TABLE `sanciontipo` ADD COLUMN `enResumenComunicado` TINYINT NOT NULL DEFAULT 0'
  )
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- Después: en Parametrización → Tipos de sanción, tildar «Incluir en el resumen
-- del comunicado» en los tipos que deban listarse (p. ej. Apercibimientos y
-- Amonestaciones).
-- =============================================================================
