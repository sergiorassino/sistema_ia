-- Permiso IA orden 98 — Estado de Deuda por Estudiante
-- Menú de Administración → GESTIÓN DE MORA
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Equivalente (solo INSERT catálogo): database/migrations/2026_08_27_160000_add_permiso_ia_orden_98_estado_deuda_estudiante.php
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Permisos por usuario.

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(98, 98, 'GESTIÓN DE MORA', 'Estado de deuda por estudiante: listado de estudiantes (con o sin familia) y deuda.')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 98 → longitud mínima 99 (carácter en posición 99, índice 98)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 99 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 99;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 98;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
