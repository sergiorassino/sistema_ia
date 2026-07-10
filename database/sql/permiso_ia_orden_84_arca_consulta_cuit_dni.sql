-- =============================================================================
-- Permiso IA orden 84 — Consulta CUIT por DNI (ARCA Padrón A13)
-- Equivalente a migración 2026_07_09_100000_add_permiso_ia_orden_84_arca_consulta_cuit_dni.php
--
-- Uso preferido: php artisan se:migrate-legacy --force
--
-- ADVERTENCIA: inserta en permisos_ia y extiende profesores.permisos_ia con '0'.
-- Revisar y asignar el bit 84 a los usuarios que deban usar el módulo.
-- =============================================================================

SET NAMES utf8mb4;

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(84, 84, 'ARCA', 'Consultar CUIT/CUIL asociado a un DNI en ARCA (Padrón Alcance 13).')
ON DUPLICATE KEY UPDATE
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

UPDATE `profesores`
SET `permisos_ia` = CONCAT(
    IFNULL(`permisos_ia`, ''),
    REPEAT('0', GREATEST(0, 85 - CHAR_LENGTH(IFNULL(`permisos_ia`, ''))))
)
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 85;
