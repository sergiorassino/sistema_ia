-- Permiso IA orden 99 — Permisos por Tarea
-- Menú de Secretaría / Administración → Configuración → Permisos del sistema
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Equivalente (solo INSERT catálogo): database/migrations/2026_08_31_120000_add_permiso_ia_orden_99_permisos_por_tarea.php
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Asignación de Permisos de Usuario.

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(99, 99, 'ADMINISTRACIÓN', 'Consultar usuarios con permiso por módulo o función (módulo Permisos por Tarea).')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 99 → longitud mínima 100 (carácter en posición 100, índice 99)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 100 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 100;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 99;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
