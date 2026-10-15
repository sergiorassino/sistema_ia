-- =============================================================================
-- Tabla `ento` — matrícula del año siguiente en autogestión del estudiante.
--
-- Un flag por nivel (`idNivel`), a continuación de `idTerlecVerNotas`:
--   matriAnioSiguiente = 0  ficha y aceptación usan la matrícula del ciclo abierto
--   matriAnioSiguiente = 1  ficha y aceptación usan la matrícula del año siguiente
--
-- Notas, inasistencias y el resto del portal siguen en `idTerlecVerNotas`.
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_10_15_143000_add_ento_matri_anio_siguiente_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
--
-- ADVERTENCIA: solo agrega la columna si falta. Default 0 (ciclo abierto).
-- Para activarlo en un nivel: UPDATE ento SET matriAnioSiguiente = 1 WHERE idNivel = N;
-- o marcar el checkbox en Parametrización → Parámetros (nivel activo).
-- =============================================================================

SET NAMES utf8mb4;

SET @add := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'matriAnioSiguiente'
);
SET @after := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'idTerlecVerNotas'
);
SET @ddl := IF(
  @add = 0,
  IF(
    @after > 0,
    'ALTER TABLE `ento` ADD COLUMN `matriAnioSiguiente` tinyint(1) NOT NULL DEFAULT 0 AFTER `idTerlecVerNotas`',
    'ALTER TABLE `ento` ADD COLUMN `matriAnioSiguiente` tinyint(1) NOT NULL DEFAULT 0'
  ),
  'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
