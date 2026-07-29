-- =============================================================================
-- Permiso IA orden 89 — Editar Observación Factura (comprobante AFIP)
-- Equivalente a migración 2026_07_29_101000_add_permiso_ia_orden_89_arca_obs_factura.php
--
-- Uso preferido: php artisan se:migrate-legacy --force
--
-- ADVERTENCIA: inserta en permisos_ia y extiende profesores.permisos_ia con '0'.
-- Revisar y asignar el bit 89 a los usuarios que deban editar la observación.
-- =============================================================================

SET NAMES utf8mb4;

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(89, 89, 'ARCA', 'Editar la observación que aparece en el impreso de factura AFIP.')
ON DUPLICATE KEY UPDATE
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

UPDATE `profesores`
SET `permisos_ia` = CONCAT(
    IFNULL(`permisos_ia`, ''),
    REPEAT('0', GREATEST(0, 90 - CHAR_LENGTH(IFNULL(`permisos_ia`, ''))))
)
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 90;
