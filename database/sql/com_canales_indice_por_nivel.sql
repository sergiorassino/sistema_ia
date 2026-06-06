-- =============================================================================
-- Canales por nivel: reemplazar índice uq_canal_par → uq_canal_nivel_par
-- Ejecutar en la BD del colegio (revisar backup antes).
-- Idempotente en la medida posible (revisar mensajes si un paso ya se aplicó).
-- =============================================================================

-- 1) Si falta id_nivel, ejecutar antes:
-- CALL sp_add_column_if_missing('com_canales', 'id_nivel', 'INT UNSIGNED NULL AFTER `id`');
-- (incluido en actualizacion_schema_idempotente.sql)

-- 2) Asignar nivel a filas huérfanas (primer id de niveles)
UPDATE `com_canales` c
SET c.`id_nivel` = (SELECT MIN(n.`id`) FROM `niveles` n)
WHERE c.`id_nivel` IS NULL;

-- 3) Quitar índice único antiguo (solo emisor + receptor)
ALTER TABLE `com_canales` DROP INDEX `uq_canal_par`;

-- 4) Índice único por nivel + par de roles
ALTER TABLE `com_canales`
  ADD UNIQUE KEY `uq_canal_nivel_par` (`id_nivel`, `rol_emisor`, `rol_receptor`);

-- 5) Obligatorio
ALTER TABLE `com_canales`
  MODIFY `id_nivel` INT UNSIGNED NOT NULL;
