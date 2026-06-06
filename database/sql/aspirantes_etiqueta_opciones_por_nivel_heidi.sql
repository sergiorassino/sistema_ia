-- =============================================================================
-- Aspirantes: etiqueta y opciones por nivel (HeidiSQL / phpMyAdmin)
-- Ejecutar con la base del colegio seleccionada.
--
-- Si un paso da "Duplicate column name" → esa columna ya existe, seguir al siguiente.
-- Si el paso 3 no aplica (no hay columnas legacy) → saltarlo.
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Paso 1 — Agregar columnas en campos_aspirantes_nivel
-- -----------------------------------------------------------------------------

ALTER TABLE `campos_aspirantes_nivel`
  ADD COLUMN `etiqueta` varchar(100) NULL DEFAULT NULL AFTER `obligatorio`;

ALTER TABLE `campos_aspirantes_nivel`
  ADD COLUMN `opciones` varchar(500) NULL DEFAULT NULL AFTER `etiqueta`;

-- -----------------------------------------------------------------------------
-- Paso 2 — Copiar datos solo si campos_aspirantes AÚN tiene etiqueta/opciones
-- (Si da error "Unknown column 'ca.etiqueta'" → saltar este paso entero)
-- -----------------------------------------------------------------------------

UPDATE `campos_aspirantes_nivel` AS cn
INNER JOIN `campos_aspirantes` AS ca ON ca.`id` = cn.`campo_aspirante_id`
SET
  cn.`etiqueta` = IFNULL(cn.`etiqueta`, ca.`etiqueta`),
  cn.`opciones` = IFNULL(cn.`opciones`, ca.`opciones`)
WHERE ca.`etiqueta` IS NOT NULL OR ca.`opciones` IS NOT NULL;

-- -----------------------------------------------------------------------------
-- Paso 3 — Quitar columnas globales de campos_aspirantes (si existen)
-- Si da "Can't DROP COLUMN" / columna desconocida → ya estaban quitadas, OK.
-- -----------------------------------------------------------------------------

ALTER TABLE `campos_aspirantes` DROP COLUMN `opciones`;
ALTER TABLE `campos_aspirantes` DROP COLUMN `etiqueta`;

-- Opcional: columnas legacy que ya no se usan
-- ALTER TABLE `campos_aspirantes` DROP COLUMN `visible`;
-- ALTER TABLE `campos_aspirantes` DROP COLUMN `obligatorio`;
