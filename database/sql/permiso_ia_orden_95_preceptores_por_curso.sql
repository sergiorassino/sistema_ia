-- Permiso IA orden 95 — Preceptores por curso
-- Menú de Secretaría / Administración → DOCENTES / USUARIOS
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Equivalente (solo INSERT catálogo): database/migrations/2026_08_26_200000_add_permiso_ia_orden_95_preceptores_por_curso.php
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Permisos por usuario.

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(95, 95, 'LEGAJOS DOCENTES', 'Asignar y quitar preceptores por curso y año lectivo (tabla preceptoresporcurso).')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 95 → longitud mínima 96 (carácter en posición 96, índice 95)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 96 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 96;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 95;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
