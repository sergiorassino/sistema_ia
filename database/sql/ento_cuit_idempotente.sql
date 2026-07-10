-- =============================================================================
-- Tabla `ento` — CUIT institucional (idempotente, re-ejecutable)
-- Equivalente a migración 2026_07_09_110000_add_cuit_to_ento_if_missing.php
-- =============================================================================

CALL sp_add_column_if_missing('ento', 'cuit', 'VARCHAR(13) NULL DEFAULT NULL AFTER `insti`');
