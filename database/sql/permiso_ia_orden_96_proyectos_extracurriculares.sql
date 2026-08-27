-- Permiso IA orden 96 — Proyectos extracurriculares (aprobación y comunicación)
-- Menú de Secretaría → PROYECTOS EXTRACURRICULARES
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Equivalente (solo INSERT catálogo): database/migrations/2026_08_27_101000_add_permiso_ia_orden_96_proyectos_extracurriculares.php
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Permisos por usuario.

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(96, 96, 'PROYECTOS EXTRACURRICULARES', 'Aprobar proyectos extracurriculares presentados por docentes y comunicar a los involucrados (organizadores, docentes del curso y preceptores).')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 96 → longitud mínima 97 (carácter en posición 97, índice 96)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 97 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 97;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 96;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
