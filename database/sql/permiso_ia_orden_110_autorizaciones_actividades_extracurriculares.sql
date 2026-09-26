-- Permiso IA orden 110 — Autorizaciones y notificaciones de actividades extracurriculares
-- Menú de Secretaría → PROYECTOS EXTRACURRICULARES → Autorizaciones y notificaciones
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Equivalente (solo INSERT catálogo): database/migrations/2026_09_26_150000_add_permiso_ia_orden_110_autorizaciones_actividades_extracurriculares.php
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Permisos por usuario.
-- ADVERTENCIA: el UPDATE alarga profesores.permisos_ia. Irreversible en la práctica (no recorta la cadena).

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(110, 110, 'PROYECTOS EXTRACURRICULARES', 'Ver actividades extracurriculares aprobadas e imprimir autorizaciones de padres o tutores para los alumnos involucrados.')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 110 → longitud mínima 111 (carácter en posición 111, índice 110)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 111 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 111;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 110;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
