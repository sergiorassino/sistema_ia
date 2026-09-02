-- Permiso IA orden 102 — Listado de familias
-- Menú de Secretaría / Administración → Estudiantes
-- Grupo: LEGAJOS ESTUDIANTES (Permisos por Usuario).
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Permisos por usuario.

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(102, 102, 'LEGAJOS ESTUDIANTES', 'Listado de familias: consultar familias con estudiantes matriculados en el ciclo lectivo activo; exportar PDF y Excel.')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 102 → longitud mínima 103 (carácter en posición 103, índice 102)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 103 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 103;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 102;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
