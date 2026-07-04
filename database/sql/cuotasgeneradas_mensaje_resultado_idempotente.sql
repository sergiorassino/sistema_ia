-- =============================================================================
-- Tabla `cuotasgeneradas` — mensaje auxiliar de facturación AFIP
-- Referencia: resultado de emisión (CAE, simulación, errores) sin tocar nroComp
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_07_04_120000_add_mensaje_resultado_to_cuotasgeneradas_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- Columna: mensajeResultado varchar(500) NULL
--
-- ADVERTENCIA: solo agrega la columna si falta. No altera tipos ni datos existentes.
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cuotasgeneradas'
    AND COLUMN_NAME = 'mensajeResultado'
);
SET @after := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cuotasgeneradas'
    AND COLUMN_NAME = 'nroComp'
);
SET @ddl := IF(
  @add = 0,
  IF(
    @after > 0,
    'ALTER TABLE `cuotasgeneradas` ADD COLUMN `mensajeResultado` varchar(500) NULL DEFAULT NULL AFTER `nroComp`',
    'ALTER TABLE `cuotasgeneradas` ADD COLUMN `mensajeResultado` varchar(500) NULL DEFAULT NULL'
  ),
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
